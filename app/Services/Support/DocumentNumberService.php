<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Issues sequential document numbers from the document_sequences table.
 *
 * Must be called inside the caller's DB transaction: the sequence row is locked
 * (lockForUpdate) until that transaction commits, so concurrent callers are serialized
 * and a rolled-back document does not consume a number.
 */
class DocumentNumberService
{
    public function next(string $key): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('DocumentNumberService::next() must run inside a database transaction.');
        }

        DB::table('document_sequences')->insertOrIgnore([
            'key'        => $key,
            'last_value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $current = (int) DB::table('document_sequences')
            ->where('key', $key)
            ->lockForUpdate()
            ->value('last_value');

        $next = $current + 1;

        DB::table('document_sequences')
            ->where('key', $key)
            ->update(['last_value' => $next, 'updated_at' => now()]);

        return $next;
    }

    /**
     * Next number for "{$prefix}" formatted as "{$prefix}-{zero padded value}", e.g. INV-20261001-000042.
     */
    public function nextFormatted(string $prefix, int $pad = 6): string
    {
        return $prefix . '-' . str_pad((string) $this->next($prefix), $pad, '0', STR_PAD_LEFT);
    }
}
