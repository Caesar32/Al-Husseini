// public/assets/js/admin/pos/pos.js (frontend F4): the POS module boots from data-config, its
// data-action buttons drive the cart, and its document/window listeners are released on leave.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createBrowser } from './dom-stub.mjs';

const EVIL = '<img src=x onerror=alert(1)>';
const PRODUCTS = [
    { id: 11, name: EVIL, price: 500, stock: 4, is_battery: false, category: 'oils', category_name: 'زيوت', sku: 'O-1', barcode: null },
];
const CONFIG = {
    data: { products: PRODUCTS, customers: [], scrapTiers: [] },
    routes: { store: '/pos', customerStore: '/customers', invoiceShow: '/invoices/__ID__', productsSearch: '/pos/products/search' },
};

function boot(products = PRODUCTS) {
    const config = { ...CONFIG, data: { ...CONFIG.data, products } };
    const b = createBrowser({ globals: { localStorage: { getItem: () => null, setItem() {} } } });
    b.load('public/assets/js/admin/pos/math.js');
    b.context.PosMath = b.window.PosMath;
    b.load('public/assets/js/admin/pos/pos.js');
    const root = { dataset: { page: 'pos', config: JSON.stringify(config) } };
    const original = b.document.querySelectorAll;
    b.document.querySelectorAll = (sel) => (sel === '[data-page]' ? [root] : original(sel));
    b.Admin.page.boot();
    return b;
}

test('boot paints the catalog without inline handlers and with escaped names', () => {
    const b = boot();
    const html = collectAll(b);
    assert.ok(html.includes('data-action="pos.add" data-id="11"'), 'catalog card uses data-action');
    assert.ok(!html.includes('<img src=x'), 'product name escaped');
    assert.ok(!/\bonclick=/.test(html));
});

test('pos.add puts the product in the cart; pos.qty and pos.remove change it', () => {
    const b = boot();
    b.click({ action: 'pos.add', id: '11' });
    const cartHtml = collectAll(b);
    assert.ok(cartHtml.includes('data-action="pos.qty"'), 'cart line rendered with quantity buttons');

    const key = cartHtml.match(/data-action="pos.qty" data-key="(\d+)" data-delta="1"/)[1];
    b.click({ action: 'pos.qty', key, delta: '1' });
    b.click({ action: 'pos.remove', key });
    assert.ok(!collectAll(b).includes(`data-key="${key}"`), 'line removed');
});

test('leaving the page removes the document and window listeners it added', () => {
    const b = boot();
    const before = (b.document.listeners.keydown || []).length;
    assert.ok(before >= 1, 'F2/F4 keydown listener registered');
    assert.ok((b.window.listeners.resize || []).length >= 1, 'ribbon resize listener registered');

    b.Admin.page.leave();
    assert.equal((b.document.listeners.keydown || []).length, before - 1);
    assert.equal((b.window.listeners.resize || []).length, 0);
    assert.equal((b.window.listeners.mousemove || []).length, 0);
});

const OIL = { id: 21, name: 'زيت محرك', price: 120.5, stock: 2.5, is_battery: false, category: 'oils', unit: 'liter', unit_label: 'لتر', step: 0.25, allows_fraction: true };
const FILTER = { id: 22, name: 'فلتر', price: 80, stock: 3, is_battery: false, category: 'filters', unit: 'piece', unit_label: 'قطعة', step: 1, allows_fraction: false };

// A cart quantity typed by the cashier (the input reacts on change).
function typeQty(b, key, value) {
    const target = {
        tagName: 'INPUT', value, dataset: { action: 'pos.qty-input', actionOn: 'change', key },
        getAttribute: (name) => (name === 'type' ? 'number' : null),
        closest(sel) { return sel === '[data-action]' ? this : null; },
    };
    (b.document.listeners.change || []).forEach((fn) => fn({ type: 'change', target, preventDefault() {} }));
}

const cartHtml = (b) => b.element('cartItemsContainer').innerHTML;
const lineKey = (b) => cartHtml(b).match(/data-action="pos.qty-input" data-action-on="change" data-key="(\d+)"/)[1];

test('a liter product adds by its step, shows its unit and accepts a typed fraction', () => {
    const b = boot([OIL]);
    assert.ok(collectAll(b).includes('متبقي 2.5 لتر فقط'), 'catalog stock shows the unit');

    b.click({ action: 'pos.add', id: '21' });
    assert.ok(cartHtml(b).includes('value="0.25"'), 'first add is one 0.25 L step');
    assert.ok(cartHtml(b).includes('step="any"'));

    const key = lineKey(b);
    typeQty(b, key, '1.5');
    assert.ok(cartHtml(b).includes('value="1.5"'));
    assert.ok(cartHtml(b).includes('180.75 ج.م'), 'line total is 1.5 × 120.5 in piasters');

    b.click({ action: 'pos.qty', key, delta: '-1' });
    assert.ok(cartHtml(b).includes('value="1.25"'), 'minus moves one step');
});

test('a typed quantity is capped at the stock and invalid input is reverted', () => {
    const b = boot([OIL]);
    b.click({ action: 'pos.add', id: '21' });
    const key = lineKey(b);

    typeQty(b, key, '3');
    assert.ok(cartHtml(b).includes('value="2.5"'), 'capped at 2.5 L in stock');
    assert.match(b.calls.swal.at(-1)[0].text, /2\.5 لتر/);

    typeQty(b, key, '1.2345');
    assert.ok(cartHtml(b).includes('value="2.5"'), 'more than 3 decimals is rejected');
    assert.equal(b.calls.swal.at(-1)[0].title, 'كمية غير صالحة');

    b.click({ action: 'pos.add', id: '21' });
    assert.ok(cartHtml(b).includes('value="2.5"'), 'nothing left to add');
});

test('a piece product only takes whole quantities', () => {
    const b = boot([FILTER]);
    b.click({ action: 'pos.add', id: '22' });
    const key = lineKey(b);
    assert.ok(cartHtml(b).includes('step="1"'));

    typeQty(b, key, '1.5');
    assert.ok(cartHtml(b).includes('value="1"'));
    assert.equal(b.calls.swal.at(-1)[0].title, 'كمية غير صالحة');

    typeQty(b, key, '2');
    assert.ok(cartHtml(b).includes('value="2"'));
});

// Every element's innerHTML, concatenated (the module renders into several containers).
function collectAll(b) {
    const seen = [];
    for (const id of b.ids()) seen.push(b.element(id).innerHTML || '');
    return seen.join('\n');
}
