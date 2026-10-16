// public/assets/js/admin/hr/employees.js (frontend F4).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createBrowser } from './dom-stub.mjs';

const ROUTES = { employees: '/admin/hr/employees', employee: '/admin/hr/employees/__ID__' };
const EVIL = `<img src=x onerror=alert(1)>"'`;
const EMP = { id: 3, full_name: EVIL, employee_code: 'EMP-3', phone: '010', status: 'active', branch: { name: EVIL } };

function boot(lastPage = 1) {
    const b = createBrowser({ fetchResponses: {
        '/admin/hr/employees?': { employees: { data: [EMP], current_page: 1, last_page: lastPage, from: 1, to: 1, total: 1 }, stats: { total: 1 } },
        '/admin/hr/employees/3': { ...EMP, attendances: [], deductions: [{ reason: EVIL, amount: 5, deduction_date: '2026-10-01', status: 'pending' }], shift_start_time: '09:00:00', shift_end_time: '17:00:00' },
    } });
    b.load('public/assets/js/admin/hr/employees.js');
    const root = { dataset: { page: 'hr.employees', config: JSON.stringify({ routes: ROUTES }) } };
    b.document.querySelectorAll = (sel) => (sel === '[data-page]' ? [root] : []);
    b.Admin.page.boot();
    return b;
}

test('table and grid escape server values and use data-action buttons', async () => {
    const b = boot();
    await b.flush();
    for (const id of ['employeesTableBody', 'gridViewContainer']) {
        const html = b.element(id).innerHTML;
        assert.ok(!html.includes('<img src=x'), `${id}: no raw markup`);
        assert.ok(html.includes('&lt;img src=x onerror=alert(1)&gt;&quot;&#39;'), `${id}: escaped`);
        assert.ok(html.includes('data-action="hr.employees.delete" data-id="3" data-name="&lt;img'), `${id}: delete button`);
        assert.ok(!/\bonclick=/.test(html), `${id}: no inline handlers`);
    }
});

test('pagination links call the page action and fetch that page', async () => {
    const b = boot(3);
    await b.flush();
    assert.ok(b.element('paginationContainer').innerHTML.includes('data-action="hr.employees.page" data-page="2"'));

    b.click({ action: 'hr.employees.page', page: '2' });
    await b.flush();
    assert.match(b.calls.fetch.at(-1).url, /^\/admin\/hr\/employees\?page=2/);
});

test('the profile modal escapes the employee and deduction details', async () => {
    const b = boot();
    await b.flush();
    b.click({ action: 'hr.employees.profile', id: '3' });
    await b.flush();

    const html = b.element('profileModalBody').innerHTML;
    assert.equal(b.calls.fetch.at(-1).url, '/admin/hr/employees/3');
    assert.ok(!html.includes('<img src=x'));
    assert.ok(html.includes('&lt;img src=x onerror=alert(1)&gt;'));
});
