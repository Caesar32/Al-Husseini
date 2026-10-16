// public/assets/js/admin/sales/credit.js (frontend F4).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createBrowser } from './dom-stub.mjs';

const EVIL = '<img src=x onerror=alert(1)>';

function boot() {
    const b = createBrowser({ fetchResponses: { '/credit/payments': { data: [], meta: { last_page: 1 } } } });
    b.load('public/assets/js/admin/sales/credit.js');
    const config = {
        data: { totalOutstanding: 1500, customersCount: 1, exceededLimitCount: 0, collectedThisMonth: 0, collectionsCount: 0, totalCustomers: 3,
            customers: [{ id: 5, name: EVIL, phone: '010', tier: 'standard', credit_balance: 1500, credit_limit: 5000, vehicle: null }] },
        routes: { settle: '/credit/settle', payments: '/credit/payments', statement: '/credit/statement/__ID__' },
    };
    const root = { dataset: { page: 'sales.credit', config: JSON.stringify(config) } };
    b.document.querySelectorAll = (sel) => (sel === '[data-page]' ? [root] : []);
    b.Admin.page.boot();
    return b;
}

test('the credit table renders escaped customers with data-action buttons', async () => {
    const b = boot();
    await b.flush();
    const html = b.element('tbodyCreditCustomers').innerHTML;
    assert.ok(html.includes('data-action="credit.pay" data-id="5"'));
    assert.ok(html.includes('data-action="credit.statement" data-id="5"'));
    assert.ok(!html.includes('<img src=x'));
    assert.ok(!/\bonclick=/.test(html));
});

test('the pay action opens the payment modal for that customer; presets cap at the balance', async () => {
    const b = boot();
    await b.flush();
    b.click({ action: 'credit.pay', id: '5' });
    assert.deepEqual(b.calls.modal.at(-1), ['show', 'recordPaymentModal']);
    assert.equal(b.element('payCustName').textContent, EVIL);

    b.click({ action: 'credit.pay-amount', amount: '2000' });
    assert.equal(b.element('payAmountInput').value, 1500);
});
