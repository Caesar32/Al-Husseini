<?php

namespace App\Console\Commands;

use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Repairs supplier ledgers written before the ledger fix (BIZ-06).
 *
 * Old behaviour: a purchase paid (partly) on reception posted `purchase_invoice` for the unpaid
 * remainder only (none at all when fully paid) followed by the payment, so the running ledger
 * balance drifted below Supplier.current_balance. Correct posting: `purchase_invoice` for the
 * full final_amount, then the payment.
 *
 * Dry-run by default. --apply writes a JSON backup of every affected supplier's ledger first.
 * Supplier.current_balance is never modified: it is reported when it still differs from the
 * repaired ledger (e.g. opening balances entered without ledger entries).
 */
class RebuildSupplierLedgerBalances extends Command
{
    protected $signature = 'suppliers:rebuild-ledger-balances
                            {--supplier= : Only this supplier id}
                            {--apply : Write the corrections (default is a dry run)}';

    protected $description = 'Report (and optionally repair) supplier ledger entries whose amounts or running balances are inconsistent';

    private const EPSILON = 0.01;

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $suppliers = Supplier::withTrashed()
            ->when($this->option('supplier'), fn ($q, $id) => $q->where('id', $id))
            ->orderBy('id')
            ->get();

        $plans = $suppliers->map(fn (Supplier $supplier) => $this->plan($supplier));
        $affected = $plans->filter(fn (array $plan) => $plan['changes'] > 0);

        $this->table(
            ['Supplier', 'Amount fixes', 'Missing entries', 'Chain fixes', 'Ledger final', 'current_balance', 'Unexplained diff', 'Anomalies'],
            $plans->map(fn (array $p) => [
                $p['supplier']->id . ' ' . $p['supplier']->company_name,
                count($p['amount_fixes']),
                count($p['missing']),
                count($p['chain_fixes']),
                number_format($p['ledger_final'], 2, '.', ''),
                number_format($p['current_balance'], 2, '.', ''),
                number_format($p['unexplained_diff'], 2, '.', ''),
                implode('; ', $p['anomalies']),
            ])->all()
        );

        if (!$apply) {
            $this->info(sprintf('Dry run: %d supplier(s) need repair. Re-run with --apply to write changes.', $affected->count()));

            return self::SUCCESS;
        }

        if ($affected->isEmpty()) {
            $this->info('Nothing to repair.');

            return self::SUCCESS;
        }

        $backupPath = $this->backup($affected);
        $this->info("Backup written: {$backupPath}");

        foreach ($affected as $plan) {
            DB::transaction(fn () => $this->applyPlan($plan));
        }

        $this->info(sprintf('Repaired %d supplier ledger(s).', $affected->count()));

        return self::SUCCESS;
    }

    /**
     * Computes the corrections for one supplier without writing anything.
     */
    private function plan(Supplier $supplier): array
    {
        $entries = SupplierLedgerEntry::where('supplier_id', $supplier->id)->get();
        $invoices = PurchaseInvoice::where('supplier_id', $supplier->id)->get();

        $amountFixes = [];
        $missing = [];
        $anomalies = [];

        foreach ($invoices as $invoice) {
            $final = round((float) $invoice->final_amount, 2);
            if ($final <= 0) {
                continue;
            }

            $postings = $entries->where('purchase_invoice_id', $invoice->id)->where('entry_type', 'purchase_invoice');

            if ($postings->count() > 1) {
                $anomalies[] = "invoice {$invoice->invoice_number}: {$postings->count()} purchase_invoice entries";
                continue;
            }

            if ($postings->isEmpty()) {
                // Place the missing posting just before the earliest entry of this invoice
                // (the on-reception payment), or at the invoice time.
                $firstLinked = $entries->where('purchase_invoice_id', $invoice->id)->sortBy(fn ($e) => [$e->created_at, $e->id])->first();
                $at = ($firstLinked?->created_at ?? $invoice->created_at)->copy()->subSecond();
                $missing[] = [
                    'purchase_invoice_id' => $invoice->id,
                    'invoice_number'      => $invoice->invoice_number,
                    'amount'              => $final,
                    'created_at'          => $at,
                    'paid_by'             => $firstLinked?->paid_by ?? $invoice->received_by,
                ];
                continue;
            }

            $posting = $postings->first();
            if (abs((float) $posting->amount - $final) > self::EPSILON) {
                $amountFixes[$posting->id] = $final;
            }
        }

        // Recompute the running balance chain with the corrected amounts, in chronological order.
        $rows = $entries->map(fn (SupplierLedgerEntry $e) => [
            'id'         => $e->id,
            'type'       => $e->entry_type,
            'amount'     => $amountFixes[$e->id] ?? round((float) $e->amount, 2),
            'created_at' => $e->created_at,
            'before'     => round((float) $e->balance_before, 2),
            'after'      => round((float) $e->balance_after, 2),
        ]);
        foreach ($missing as $m) {
            $rows->push(['id' => null, 'type' => 'purchase_invoice', 'amount' => $m['amount'], 'created_at' => $m['created_at'], 'before' => null, 'after' => null, 'missing' => $m]);
        }
        $rows = $rows->sortBy(fn ($r) => [$r['created_at']?->getTimestamp() ?? 0, $r['id'] ?? 0])->values();

        $running = 0.0;
        $chainFixes = [];
        $missingWithBalances = [];
        foreach ($rows as $row) {
            $before = round($running, 2);
            $running = round($running + ($row['type'] === 'purchase_invoice' ? $row['amount'] : -$row['amount']), 2);

            if ($row['id'] === null) {
                $missingWithBalances[] = $row['missing'] + ['balance_before' => $before, 'balance_after' => $running];
            } elseif (abs($row['before'] - $before) > self::EPSILON || abs($row['after'] - $running) > self::EPSILON) {
                $chainFixes[$row['id']] = ['balance_before' => $before, 'balance_after' => $running];
            }
        }

        $currentBalance = round((float) $supplier->current_balance, 2);

        return [
            'supplier'         => $supplier,
            'amount_fixes'     => $amountFixes,
            'missing'          => $missingWithBalances,
            'chain_fixes'      => $chainFixes,
            'ledger_final'     => $running,
            'current_balance'  => $currentBalance,
            'unexplained_diff' => round($currentBalance - $running, 2),
            'anomalies'        => $anomalies,
            'changes'          => count($amountFixes) + count($missingWithBalances) + count($chainFixes),
        ];
    }

    private function applyPlan(array $plan): void
    {
        Supplier::withTrashed()->where('id', $plan['supplier']->id)->lockForUpdate()->first();

        foreach ($plan['amount_fixes'] as $entryId => $amount) {
            SupplierLedgerEntry::where('id', $entryId)->update(['amount' => $amount]);
        }

        foreach ($plan['chain_fixes'] as $entryId => $balances) {
            SupplierLedgerEntry::where('id', $entryId)->update($balances);
        }

        foreach ($plan['missing'] as $m) {
            $entry = new SupplierLedgerEntry([
                'supplier_id'         => $plan['supplier']->id,
                'purchase_invoice_id' => $m['purchase_invoice_id'],
                'entry_type'          => 'purchase_invoice',
                'amount'              => $m['amount'],
                'balance_before'      => $m['balance_before'],
                'balance_after'       => $m['balance_after'],
                'payment_method'      => 'cash',
                'paid_by'             => $m['paid_by'],
                'notes'               => "استحقاق فاتورة توريد رقم {$m['invoice_number']} (قيد تصحيحي لإعادة بناء الكشف)",
            ]);
            $entry->created_at = $m['created_at'];
            $entry->updated_at = now();
            $entry->save();
        }
    }

    private function backup(Collection $plans): string
    {
        $payload = [
            'created_at' => now()->toIso8601String(),
            'suppliers'  => $plans->map(fn (array $p) => [
                'supplier_id'     => $p['supplier']->id,
                'current_balance' => $p['current_balance'],
                'entries'         => SupplierLedgerEntry::where('supplier_id', $p['supplier']->id)->orderBy('id')->get()->toArray(),
            ])->values()->all(),
        ];

        $path = 'ledger-backups/supplier-ledger-' . now()->format('Ymd-His-u') . '.json';
        Storage::disk('local')->put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return Storage::disk('local')->path($path);
    }
}
