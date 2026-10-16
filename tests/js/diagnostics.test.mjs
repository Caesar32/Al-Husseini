// public/assets/js/admin/diagnostics.js (frontend F4).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createBrowser } from './dom-stub.mjs';

test('the audit button posts to the configured URL and renders escaped check rows', async () => {
    const EVIL = '<img src=x onerror=alert(1)>';
    const b = createBrowser({ fetchResponses: { '/diag/audit': { status: 'success', data: {
        health_score: 90, passed_count: 8, total_checks: 9, duration_ms: 12, audited_at: 'now',
        checks: [{ status: 'warning', severity: 'high', name: EVIL, details: EVIL }],
    } } } });
    b.load('public/assets/js/admin/diagnostics.js');
    const root = { dataset: { page: 'diagnostics', config: JSON.stringify({ auditUrl: '/diag/audit', simulationUrl: '/diag/sim' }) } };
    b.document.querySelectorAll = (sel) => (sel === '[data-page]' ? [root] : []);
    b.Admin.page.boot();

    b.element('btnRefreshAudit').fire('click');
    await b.flush();
    await b.flush();

    assert.equal(b.calls.fetch[0].url, '/diag/audit');
    assert.equal(b.calls.fetch[0].method, 'POST');
    const html = b.element('auditTbody').innerHTML;
    assert.ok(html.includes('&lt;img src=x onerror=alert(1)&gt;'));
    assert.ok(!html.includes('<img src=x'));
});
