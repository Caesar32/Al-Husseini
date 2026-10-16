// public/assets/js/admin/hr/payroll.js (frontend F4): the module boots from data-config.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createBrowser } from './dom-stub.mjs';

const ROUTES = {
    generate: '/admin/hr/payroll/generate', deductions: '/admin/hr/deductions', payroll: '/admin/hr/payroll/__ID__',
    approve: '/admin/hr/payroll/__ID__/approve', disburse: '/admin/hr/payroll/__ID__/disburse', deductionStatus: '/admin/hr/deductions/__ID__/status',
};

test('the generate form posts branch, year and month to the configured route', async () => {
    const b = createBrowser({ fetchResponses: { '/admin/hr/payroll/generate': { message: 'ok' } } });
    b.load('public/assets/js/admin/hr/payroll.js');
    const root = { dataset: { page: 'hr.payroll', config: JSON.stringify({ routes: ROUTES }) } };
    b.document.querySelectorAll = (sel) => (sel === '[data-page]' ? [root] : []);
    b.Admin.page.boot();

    b.element('genBranchSelect').value = '1';
    b.element('genYearInput').value = '2026';
    b.element('genMonthInput').value = '10';
    b.element('generatePayrollForm').onsubmit({ preventDefault() {} });
    await b.flush();

    assert.equal(b.calls.fetch[0].url, ROUTES.generate);
    assert.equal(b.calls.fetch[0].method, 'POST');
    assert.equal(JSON.stringify(b.calls.fetch[0].body), JSON.stringify({ branch_id: '1', year: '2026', month: '10' }));
});
