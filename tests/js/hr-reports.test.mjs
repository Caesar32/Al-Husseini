// public/assets/js/admin/hr/reports.js (frontend F4).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createBrowser } from './dom-stub.mjs';

const URLS = { daily: '/r/daily', monthly: '/r/monthly', range: '/r/range', employee: '/r/employees/__ID__' };
const EVIL = '<img src=x onerror=alert(1)>';
const EMPLOYEE = { id: 9, name: EVIL, code: 'E9', role: 'فني', department: 'الورشة', startTime: '09:00', endTime: '17:00' };
const DAILY = {
    summary: { attendanceRate: 100, presentCount: 1, totalStaff: 1, totalLateMins: 0, lateCount: 0, absentCount: 0, leaveCount: 0, onTimeCount: 1, totalDeductionsAmount: 0, deductionsCount: 0 },
    departments: ['الورشة'],
    employees: [{ employee: EMPLOYEE, date: '2026-10-16', status: 'on_time', punchIn: '09:00', punchOut: '-', latenessMinutes: 0, totalDeductions: 0 }],
    deductionsList: [],
};

function boot() {
    const charts = [];
    const ApexCharts = function () { charts.push(this); this.render = () => {}; this.updateOptions = () => {}; this.destroy = () => { this.destroyed = true; }; };
    const b = createBrowser({ fetchResponses: { '/r/daily': DAILY, '/r/employees/9': { employee: EMPLOYEE, deductions: [], presentDays: 1, lateDays: 0, totalLateMinutes: 0, totalDeductionsAmount: 0 } }, globals: { ApexCharts, Blob: function () {}, URL: { createObjectURL: () => '', revokeObjectURL() {} } } });
    b.load('public/assets/js/admin/hr/reports.js');
    const root = { dataset: { page: 'hr.reports', config: JSON.stringify({ urls: URLS, defaultAvatar: '/a.jpg' }) } };
    b.document.querySelectorAll = (sel) => (sel === '[data-page]' ? [root] : []);
    b.Admin.page.boot();
    return { b, charts };
}

test('boot loads the daily report and renders escaped rows with a data-action details button', async () => {
    const { b, charts } = boot();
    await b.flush();
    await b.flush();

    assert.match(b.calls.fetch[0].url, /^\/r\/daily\?date=\d{4}-\d{2}-\d{2}$/);
    const staff = b.element('tbody-staff-summary').innerHTML;
    assert.ok(staff.includes('data-action="hr.reports.employee" data-id="9"'));
    assert.ok(!staff.includes('<img src=x onerror'), 'employee name escaped');
    assert.ok(!/\bonclick=/.test(staff));
    assert.equal(charts.length, 2);
});

test('the employee action fetches that employee; leaving the page destroys both charts', async () => {
    const { b, charts } = boot();
    await b.flush();
    await b.flush();

    b.click({ action: 'hr.reports.employee', id: '9' });
    await b.flush();
    assert.equal(b.calls.fetch.at(-1).url, '/r/employees/9');

    b.Admin.page.leave();
    assert.ok(charts.every((c) => c.destroyed));
});
