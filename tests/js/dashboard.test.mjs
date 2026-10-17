// public/assets/js/admin/dashboard.js (frontend F4).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createBrowser } from './dom-stub.mjs';

const PERIODS = {
    all: { key: 'all', total_compact: '3.8 ألف ج.م', total_formatted: '3,800.00 ج.م', count: 6, revenue_compact: '3.5 ألف ج.م', revenue_formatted: '3,500.00 ج.م', badge: 'الإجمالي', sublabel: 'منذ البداية', invoices_url: '/i', credit_collected: 0, credit_collected_compact: '0 ج.م', credit_collected_fmt: '0.00 ج.م' },
    week: { key: 'week', total_compact: '1.7 ألف ج.م', total_formatted: '1,700.00 ج.م', count: 3, revenue_compact: '1.9 ألف ج.م', revenue_formatted: '1,900.00 ج.م', badge: 'آخر 7 أيام', sublabel: 'هذا الأسبوع', invoices_url: '/i?w', credit_collected: 150, credit_collected_compact: '150 ج.م', credit_collected_fmt: '150.00 ج.م' },
};

function boot() {
    const charts = [];
    const ApexCharts = function () { charts.push(this); this.render = () => {}; this.updateOptions = () => {}; this.destroy = () => { this.destroyed = true; }; };
    const store = {};
    const localStorage = { getItem: (k) => store[k] ?? null, setItem: (k, v) => { store[k] = String(v); } };
    const b = createBrowser({ globals: { ApexCharts, localStorage } });
    b.load('public/assets/js/admin/dashboard.js');
    b.document.querySelector = (sel) => (sel.startsWith('#') ? b.element(sel.slice(1)) : null);
    const config = { salesPeriods: PERIODS, selectedPeriodKey: 'all', trend: { categories: ['أ'], batteries: [1], oils: [2], services: [3] }, categories: { batteries: 10, oils: 5, greases: 1, services: 2 } };
    const root = { dataset: { page: 'dashboard', config: JSON.stringify(config) } };
    const original = b.document.querySelectorAll;
    b.document.querySelectorAll = (sel) => (sel === '[data-page]' ? [root] : original(sel));
    b.Admin.page.boot();
    return { b, charts, store };
}

test('boot renders both charts from the page config', () => {
    const { charts } = boot();
    assert.equal(charts.length, 2);
});

test('the period action shows that period and remembers it; leaving destroys the charts', () => {
    const { b, charts, store } = boot();
    b.click({ action: 'dashboard.period', period: 'week' });

    assert.equal(store.alhusseini_dashboard_sales_period, 'week');
    assert.ok(String(b.element('dashInvoicesCount').textContent).includes('3'));

    b.Admin.page.leave();
    assert.ok(charts.every((c) => c.destroyed));
});

test('the period action toggles d-none on the credit-collected line by the period amount', () => {
    const { b } = boot();
    const classes = new Set(['d-none']);
    b.element('dashCreditCollectedWrap').classList = {
        add: (c) => classes.add(c),
        remove: (c) => classes.delete(c),
        toggle: (c, force) => (force ?? !classes.has(c) ? classes.add(c) : classes.delete(c)),
        contains: (c) => classes.has(c),
    };

    b.click({ action: 'dashboard.period', period: 'week' });
    assert.equal(classes.has('d-none'), false);
    assert.equal(b.element('dashCreditCollected').textContent, '150 ج.م');

    b.click({ action: 'dashboard.period', period: 'all' });
    assert.equal(classes.has('d-none'), true);
});
