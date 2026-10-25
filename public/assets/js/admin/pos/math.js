/**
 * Point-of-sale money math (frontend F4), loaded before pos.js. Pure functions, no DOM.
 * Amounts that are compared or split are integer piasters, so decimals never drift (0.1 + 0.2).
 * The server validates everything again (StorePosInvoiceRequest).
 */
(function () {
    'use strict';

    const toCents = (value) => Math.round((Number(value) || 0) * 100);
    const fromCents = (cents) => Math.round(cents) / 100;

    // Quantities are compared and summed as integer thousandths (3 decimals, like App\Support\Quantity).
    const toMilli = (value) => Math.round((Number(value) || 0) * 1000);
    const fromMilli = (milli) => Math.round(milli) / 1000;
    const formatQty = (value) => String(fromMilli(toMilli(value)));

    /** Line total in piasters, rounded half up like Quantity::lineTotal() on the server. */
    function lineCents(qty, price) {
        return Math.round(toMilli(qty) * toCents(price) / 1000);
    }

    /**
     * @param {Array<{product: {price: number}, qty: number}>} lines
     * @returns {{subtotal: number, total: number}} total = subtotal - discount - scrap deduction, never below 0
     */
    function totals(lines, discount, scrapDeduction) {
        const subtotal = fromCents(lines.reduce((sum, l) => sum + lineCents(l.qty, l.product.price), 0));
        // Tax: no tax rule is configured in the system (VAT setting unused), so none is applied.
        const total = Math.max(0, subtotal - discount - scrapDeduction);
        return { subtotal, total };
    }

    /**
     * What is paid now / what remains, for a payment mode:
     *   full: pay exactly the amount due; partial: 0 < paid <= due, the rest goes on credit;
     *   remaining: pay nothing now, the whole amount goes on credit.
     * @param {{due: number, mode: string, raw: string, lineCount: number, formatCents: function(number): string}} input  due in piasters
     * @returns {{due: number, paid: number, remaining: number, error: (string|null), typed: boolean}}
     */
    function payment({ due, mode, raw, lineCount, formatCents }) {
        let paid = 0;
        let error = null;

        if (mode === 'full') {
            paid = due;
        } else if (mode === 'partial') {
            const entered = raw === '' ? null : toCents(raw);
            if (entered === null) {
                error = 'أدخل المبلغ المدفوع الآن (أكبر من صفر وحتى ' + formatCents(due) + ').';
            } else if (entered <= 0) {
                error = 'المبلغ المدفوع يجب أن يكون أكبر من الصفر.';
            } else if (entered > due) {
                error = 'لا يمكن أن يتجاوز المبلغ المدفوع المبلغ المستحق (' + formatCents(due) + ').';
            } else {
                paid = entered;
            }
        }
        if (due <= 0 && lineCount > 0) {
            error = 'المبلغ المستحق صفر؛ لا يمكن إصدار فاتورة بدون مبلغ مستحق. راجع الخصم.';
        }

        return { due, paid, remaining: Math.max(0, due - paid), error, typed: raw !== '' };
    }

    window.PosMath = { toCents, fromCents, toMilli, fromMilli, formatQty, lineCents, totals, payment };
})();
