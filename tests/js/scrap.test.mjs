// public/assets/js/admin/scrap.js (frontend F4).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createBrowser } from './dom-stub.mjs';

function fireChange(b, target) {
    (b.document.listeners.change || []).forEach((fn) => fn({
        type: 'change',
        target,
        preventDefault() {},
    }));
}

function boot() {
    const b = createBrowser();
    const checkboxes = [
        { checked: false, value: '101', getAttribute: (name) => name === 'data-value' ? '10.5' : null },
        { checked: false, value: '102', getAttribute: (name) => name === 'data-value' ? '20.25' : null },
    ];
    const root = { dataset: { page: 'scrap.index' }, querySelectorAll: () => [] };
    const hiddenInputs = b.element('hiddenInputsContainer');
    const appendedInputs = [];

    hiddenInputs.appendChild = (input) => appendedInputs.push(input);
    b.document.createElement = () => ({});
    b.element('openSellModalBtn').attributes = { disabled: 'disabled' };
    b.element('openSellModalBtn').setAttribute = (name, value) => { b.element('openSellModalBtn').attributes[name] = value; };
    b.element('openSellModalBtn').removeAttribute = (name) => { delete b.element('openSellModalBtn').attributes[name]; };
    b.element('checkAllMaster').checked = false;
    b.document.querySelectorAll = (selector) => selector === '[data-page]' ? [root] : selector === '.scrap-checkbox' ? checkboxes : [];
    b.load('public/assets/js/admin/scrap.js');
    b.Admin.page.boot();

    return { b, checkboxes, appendedInputs };
}

test('boot starts with no selected scrap batteries', () => {
    const { b, appendedInputs } = boot();

    assert.equal(b.element('selectedCountDisplay').textContent, '0');
    assert.equal(b.element('modalBatchCount').textContent, '0');
    assert.equal(b.element('modalBatchCost').textContent, '0.00');
    assert.equal(b.element('openSellModalBtn').attributes.disabled, 'disabled');
    assert.equal(appendedInputs.length, 0);
});

test('select-all selects every battery and enables the sale action', () => {
    const { b, checkboxes, appendedInputs } = boot();

    b.click({ action: 'scrap.select-all' });

    assert.deepEqual(checkboxes.map((checkbox) => checkbox.checked), [true, true]);
    assert.deepEqual(appendedInputs.map((input) => ({ name: input.name, value: input.value })), [
        { name: 'scrap_battery_ids[]', value: '101' },
        { name: 'scrap_battery_ids[]', value: '102' },
    ]);
    assert.equal(b.element('selectedCountDisplay').textContent, '2');
    assert.equal(b.element('modalBatchCount').textContent, '2');
    assert.equal(b.element('modalBatchCost').textContent, '30.75');
    assert.equal(b.element('openSellModalBtn').attributes.disabled, undefined);
    assert.equal(b.element('checkAllMaster').checked, true);
});

test('unchecking the master checkbox clears every selected battery', () => {
    const { b, checkboxes } = boot();
    const master = b.element('checkAllMaster');

    b.click({ action: 'scrap.select-all' });
    master.checked = false;
    master.dataset = { action: 'scrap.check-all', actionOn: 'change' };
    master.closest = (selector) => selector === '[data-action]' ? master : null;
    fireChange(b, master);

    assert.deepEqual(checkboxes.map((checkbox) => checkbox.checked), [false, false]);
    assert.equal(b.element('selectedCountDisplay').textContent, '0');
    assert.equal(b.element('modalBatchCount').textContent, '0');
    assert.equal(b.element('modalBatchCost').textContent, '0.00');
    assert.equal(b.element('openSellModalBtn').attributes.disabled, 'disabled');
});
