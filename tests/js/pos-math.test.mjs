// public/assets/js/admin/pos/math.js (frontend F4): POS totals and payment split in piasters.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const window = {};
vm.runInContext(readFileSync(new URL('../../public/assets/js/admin/pos/math.js', import.meta.url), 'utf8'), vm.createContext({ window }));
const { toCents, fromCents, totals, payment } = window.PosMath;
const formatCents = (c) => `${fromCents(c)} ج.م`;
const plain = (o) => JSON.parse(JSON.stringify(o));

test('piaster conversion is exact for typical prices', () => {
    assert.equal(toCents('0.1') + toCents('0.2'), toCents('0.3'));
    assert.equal(toCents(3199.99), 319999);
    assert.equal(toCents('12.345'), 1235); // rounds half up
    assert.equal(toCents(''), 0);
    assert.equal(fromCents(319999), 3199.99);
});

test('totals subtract discount and scrap deduction and never go below zero', () => {
    const lines = [{ product: { price: 3200 }, qty: 1 }, { product: { price: 250 }, qty: 2 }];
    assert.deepEqual(plain(totals(lines, 100, 800)), { subtotal: 3700, total: 2800 });
    assert.deepEqual(plain(totals(lines, 5000, 0)), { subtotal: 3700, total: 0 });
    assert.deepEqual(plain(totals([], 0, 0)), { subtotal: 0, total: 0 });
});

test('full mode pays the whole amount due', () => {
    assert.deepEqual(plain(payment({ due: 280000, mode: 'full', raw: '', lineCount: 2, formatCents })),
        { due: 280000, paid: 280000, remaining: 0, error: null, typed: false });
});

test('remaining mode pays nothing now', () => {
    const r = payment({ due: 280000, mode: 'remaining', raw: '', lineCount: 2, formatCents });
    assert.equal(r.paid, 0);
    assert.equal(r.remaining, 280000);
    assert.equal(r.error, null);
});

test('partial mode accepts 0 < paid <= due and rejects the rest with the same messages', () => {
    const ok = payment({ due: 280000, mode: 'partial', raw: '1000.50', lineCount: 1, formatCents });
    assert.equal(ok.paid, 100050);
    assert.equal(ok.remaining, 179950);
    assert.equal(ok.error, null);
    assert.equal(ok.typed, true);

    assert.match(payment({ due: 280000, mode: 'partial', raw: '', lineCount: 1, formatCents }).error, /^أدخل المبلغ المدفوع الآن/);
    assert.equal(payment({ due: 280000, mode: 'partial', raw: '0', lineCount: 1, formatCents }).error, 'المبلغ المدفوع يجب أن يكون أكبر من الصفر.');
    assert.equal(payment({ due: 280000, mode: 'partial', raw: '2800.01', lineCount: 1, formatCents }).error, 'لا يمكن أن يتجاوز المبلغ المدفوع المبلغ المستحق (2800 ج.م).');
    assert.equal(payment({ due: 280000, mode: 'partial', raw: '2800.01', lineCount: 1, formatCents }).paid, 0);
});

test('a cart whose amount due is zero cannot be invoiced', () => {
    assert.match(payment({ due: 0, mode: 'full', raw: '', lineCount: 1, formatCents }).error, /المبلغ المستحق صفر/);
    assert.equal(payment({ due: 0, mode: 'full', raw: '', lineCount: 0, formatCents }).error, null);
});
