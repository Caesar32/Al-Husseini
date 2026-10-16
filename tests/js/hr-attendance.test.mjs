// public/assets/js/admin/hr/attendance.js (frontend F4).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createBrowser } from './dom-stub.mjs';

const ROUTES = { attendance: '/admin/hr/attendance', punch: '/admin/hr/attendance/punch', markAbsent: '/admin/hr/attendance/mark-absent', deductions: '/admin/hr/deductions' };
const EVIL = `<img src=x onerror=alert(1)>"'`;

function boot(attendances = []) {
    const b = createBrowser({ fetchResponses: { '/admin/hr/attendance?': { attendances, stats: { present: 0, late: 0, absent: 1, total_expected: 1 } } } });
    b.load('public/assets/js/admin/hr/attendance.js');
    b.element('attendanceDateFilter').value = '2026-10-16';
    const root = { dataset: { page: 'hr.attendance', config: JSON.stringify({ routes: ROUTES, employees: [{ id: 7, full_name: EVIL, employee_code: 'EMP-7', status: 'active', branch: { name: 'MAIN' } }] }) } };
    b.document.querySelectorAll = (sel) => (sel === '[data-page]' ? [root] : []);
    b.Admin.page.boot();
    return b;
}

test('rows escape server values and use data-action buttons instead of inline handlers', async () => {
    const b = boot();
    await b.flush();
    const html = b.element('attendanceTableBody').innerHTML;

    assert.ok(html.includes('&lt;img src=x onerror=alert(1)&gt;&quot;&#39;'), 'name is escaped');
    assert.ok(!html.includes('<img src=x'), 'no raw markup from data');
    assert.ok(!/\bonclick=/.test(html), 'no inline handlers');
    assert.ok(html.includes('data-action="hr.attendance.punch" data-employee-id="7" data-punch-type="check_in"'));
    assert.ok(html.includes('data-action="hr.attendance.deduct" data-employee-id="7"'));
    assert.equal(b.element('statCountAbsent').textContent, 1);
});

test('a punch button posts the employee, state and a timestamp for the selected date', async () => {
    const b = boot();
    await b.flush();
    b.click({ action: 'hr.attendance.punch', employeeId: '7', punchType: 'check_in' });
    await b.flush();

    const post = b.calls.fetch.find((c) => c.method === 'POST');
    assert.equal(post.url, ROUTES.punch);
    assert.equal(post.body.employee_id, '7');
    assert.equal(post.body.punch_state, 'check_in');
    assert.match(post.body.timestamp, /^2026-10-16 \d{2}:\d{2}:\d{2}$/);
});

test('the deduct action opens the modal for that employee; presets set the amount', async () => {
    const b = boot();
    await b.flush();
    b.click({ action: 'hr.attendance.deduct', employeeId: '7' });
    assert.deepEqual(b.calls.modal.at(-1), ['show', 'quickDeductionModal']);
    assert.equal(b.element('quickDedEmployeeName').textContent, EVIL); // textContent: no escaping needed

    b.click({ action: 'hr.attendance.preset', amount: '350' });
    assert.equal(b.element('quickDedAmount').value, '350');
});

test('the live clock interval is cleared when the page is left', () => {
    const b = boot();
    assert.deepEqual(b.calls.intervals, [1000]);
    b.Admin.page.leave();
    assert.deepEqual(b.calls.cleared, [1]);
});
