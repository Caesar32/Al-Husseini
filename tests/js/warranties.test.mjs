// public/assets/js/admin/warranties.js (frontend F4).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createBrowser } from './dom-stub.mjs';

const EVIL = `x');alert(1);('<b>`;

function boot(response) {
    const b = createBrowser({ fetchResponses: { '/admin/warranties/verify': response } });
    b.load('public/assets/js/admin/warranties.js');
    const root = { dataset: { page: 'warranties.index', config: JSON.stringify({ verifyUrl: '/admin/warranties/verify', settleUrl: '/admin/warranties/claims/__ID__/settle' }) }, querySelectorAll: () => [] };
    b.document.querySelectorAll = (sel) => (sel === '[data-page]' ? [root] : []);
    b.Admin.page.boot();
    return b;
}

test('a verified serial renders escaped results and a data-action claim button', async () => {
    const b = boot({ exists: true, is_valid: true, message: '<i>ok</i>', days_remaining: 30, warranty: { end_date: '2027-01-01', customer: { name: EVIL, phone: '010' } } });
    b.element('serialSearchInput').value = EVIL;
    b.element('serialSearchBtn').fire('click');
    await b.flush();

    const html = b.element('verificationResultBox').innerHTML;
    assert.equal(b.calls.fetch[0].url, '/admin/warranties/verify?serial_number=' + encodeURIComponent(EVIL));
    assert.ok(html.includes('data-action="warranties.open-claim" data-serial="x&#39;);alert(1);(&#39;&lt;b&gt;"'));
    assert.ok(!html.includes('<b>') && !html.includes('<i>ok'), 'server values escaped');
    assert.ok(!/\bonclick=/.test(html));
});

test('the claim action opens the modal with the serial; samples submit the verify form', () => {
    const b = boot({});
    b.click({ action: 'warranties.open-claim', serial: 'SN-1' });
    assert.equal(b.element('modalDefectiveSerial').value, 'SN-1');
    assert.deepEqual(b.calls.modal.at(-1), ['show', 'newClaimModal']);

    let submitted = 0;
    b.element('verifyForm').submit = () => submitted++;
    b.click({ action: 'warranties.sample', serial: 'SN-2' });
    assert.equal(b.element('serialInput').value, 'SN-2');
    assert.equal(submitted, 1);
});
