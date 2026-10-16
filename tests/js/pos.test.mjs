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

function boot() {
    const b = createBrowser({ globals: { localStorage: { getItem: () => null, setItem() {} } } });
    b.load('public/assets/js/admin/pos/math.js');
    b.context.PosMath = b.window.PosMath;
    b.load('public/assets/js/admin/pos/pos.js');
    const root = { dataset: { page: 'pos', config: JSON.stringify(CONFIG) } };
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

// Every element's innerHTML, concatenated (the module renders into several containers).
function collectAll(b) {
    const seen = [];
    for (const id of b.ids()) seen.push(b.element(id).innerHTML || '');
    return seen.join('\n');
}
