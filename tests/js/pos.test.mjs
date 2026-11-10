// public/assets/js/admin/pos/pos.js (frontend F4): the POS module boots from data-config, its
// data-action buttons drive the cart, and its document/window listeners are released on leave.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createBrowser } from './dom-stub.mjs';

const EVIL = '<img src=x onerror=alert(1)>';
const PRODUCTS = [
    { id: 11, name: EVIL, price: 500, stock: 4, is_battery: false, category: 'oils', category_name: 'زيوت', sku: 'O-1', barcode: null },
];
const CONFIG = {
    data: { products: PRODUCTS, customers: [], scrapTiers: [] },
    routes: { store: '/pos', customerStore: '/customers', invoiceShow: '/invoices/__ID__', productsSearch: '/pos/products/search' },
};

function boot(products = PRODUCTS, globals = {}) {
    const config = { ...CONFIG, data: { ...CONFIG.data, products } };
    const b = createBrowser({ globals: { localStorage: { getItem: () => null, setItem() {} }, ...globals } });
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

test('wheel down moves the category ribbon forward in both directions', () => {
    const rtl = boot(PRODUCTS, { getComputedStyle: () => ({ direction: 'rtl' }) });
    rtl.element('categoryTabsContainer').fire('wheel', { deltaY: 1 });
    assert.equal(rtl.element('categoryTabsContainer').scrollLeft, -120);

    const fallback = boot();
    fallback.element('categoryTabsContainer').fire('wheel', { deltaY: 1 });
    assert.equal(fallback.element('categoryTabsContainer').scrollLeft, -120);

    const ltr = boot(PRODUCTS, { getComputedStyle: () => ({ direction: 'ltr' }) });
    ltr.element('categoryTabsContainer').fire('wheel', { deltaY: 1 });
    assert.equal(ltr.element('categoryTabsContainer').scrollLeft, 120);
});

test('category ribbon actions use semantic forward and back directions', () => {
    const b = boot(PRODUCTS, { getComputedStyle: () => ({ direction: 'rtl' }) });
    const ribbon = b.element('categoryTabsContainer');

    b.click({ action: 'pos.ribbon', direction: 'forward' });
    assert.equal(ribbon.scrollByCalls.at(-1).left, -160);
    assert.equal(ribbon.scrollByCalls.at(-1).behavior, 'smooth');

    b.click({ action: 'pos.ribbon', direction: 'back' });
    assert.equal(ribbon.scrollByCalls.at(-1).left, 160);
    assert.equal(ribbon.scrollByCalls.at(-1).behavior, 'smooth');
});

test('category ribbon arrows reflect overflow and scroll boundaries', () => {
    const b = boot(PRODUCTS, { getComputedStyle: () => ({ direction: 'rtl' }) });
    const ribbon = b.element('categoryTabsContainer');
    const back = b.element('catScrollBackBtn');
    const forward = b.element('catScrollForwardBtn');
    ribbon.scrollWidth = 1000;
    ribbon.clientWidth = 400;

    ribbon.scrollLeft = 0;
    ribbon.fire('scroll');
    assert.ok(back.classList.contains('invisible'));
    assert.ok(!forward.classList.contains('invisible'));

    ribbon.scrollLeft = -600;
    ribbon.fire('scroll');
    assert.ok(forward.classList.contains('invisible'));
    assert.ok(!back.classList.contains('invisible'));

    ribbon.scrollWidth = 400;
    ribbon.fire('scroll');
    assert.ok(back.classList.contains('d-none'));
    assert.ok(forward.classList.contains('d-none'));
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

// Settings page, allow_negative_stock: off keeps every limit, on lets the cashier sell ahead of the stock.
function bootWithSetting(products, allowNegativeStock) {
    const config = { ...CONFIG, allowNegativeStock, data: { ...CONFIG.data, products } };
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

const EMPTY_FILTER = { id: 31, name: 'فلتر', price: 80, stock: 0, is_battery: false, category: 'filters', unit: 'piece', unit_label: 'قطعة', step: 1, allows_fraction: false };

test('selling below zero off: an empty product shows as out of stock and cannot be added', () => {
    const b = bootWithSetting([EMPTY_FILTER], false);
    assert.ok(collectAll(b).includes('نفذ من المخزن (0)'));
    assert.ok(collectAll(b).includes('disabled'), 'add button disabled');

    b.click({ action: 'pos.add', id: '31' });
    assert.ok(!collectAll(b).includes('data-action="pos.qty"'), 'nothing added to the cart');
});

test('selling below zero on: an empty product shows as available to order and can be added', () => {
    const b = bootWithSetting([EMPTY_FILTER], true);
    assert.ok(collectAll(b).includes('متاح للطلب (0)'));
    assert.ok(!collectAll(b).includes('نفذ من المخزن'));

    b.click({ action: 'pos.add', id: '31' });
    const html = collectAll(b);
    assert.ok(html.includes('data-action="pos.qty"'), 'added to the cart');

    const key = html.match(/data-action="pos.qty" data-key="(\d+)" data-delta="1"/)[1];
    b.click({ action: 'pos.qty', key, delta: '1' });
    assert.ok(cartHtml(b).includes('value="2"'), 'a second unit is accepted too');
});

test('selling below zero on: a typed quantity is not capped at the stock', () => {
    const b = bootWithSetting([{ ...EMPTY_FILTER, stock: 1 }], true);
    b.click({ action: 'pos.add', id: '31' });
    const key = collectAll(b).match(/data-action="pos.qty" data-key="(\d+)" data-delta="1"/)[1];

    typeQty(b, key, '5');
    assert.ok(cartHtml(b).includes('value="5"'), 'typed quantity kept');
});

// Legacy customers have no phone; the list must read cleanly and be searchable by name.
function bootWithCustomers(customers) {
    const config = { ...CONFIG, data: { ...CONFIG.data, customers } };
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

function searchCustomers(b, value) {
    const target = {
        tagName: 'INPUT', value, dataset: { action: 'pos.customer-search' },
        getAttribute: (name) => (name === 'type' ? 'search' : null),
        closest(sel) { return sel === '[data-action]' ? this : null; },
    };
    (b.document.listeners.input || []).forEach((fn) => fn({ type: 'input', target, preventDefault() {} }));
}

const customerOptions = (b) => b.element('posCustomerSelect').innerHTML;

function typeCatalogSearch(b, value) {
    const input = b.element('catalogSearchInput');
    input.focus();
    input.value = value;
    const target = {
        tagName: 'INPUT',
        value,
        dataset: { action: 'pos.search' },
        getAttribute: (name) => (name === 'type' ? 'text' : null),
        closest(sel) {
            return sel === '[data-action]' ? this : null;
        },
    };
    (b.document.listeners.input || []).forEach((fn) => fn({ type: 'input', target, preventDefault() {} }));
    return input;
}

function bootWithSearchResponse(products = PRODUCTS, customers = CUSTOMERS) {
    const response = {
        data: products,
        meta: { last_page: 1, has_more: false },
        category_counts: {},
    };
    const config = { ...CONFIG, data: { ...CONFIG.data, products, customers } };
    const b = createBrowser({
        fetchResponses: { '/pos/products/search': response },
        globals: { localStorage: { getItem: () => null, setItem() {} } },
    });
    b.load('public/assets/js/admin/pos/math.js');
    b.context.PosMath = b.window.PosMath;
    b.load('public/assets/js/admin/pos/pos.js');
    const root = { dataset: { page: 'pos', config: JSON.stringify(config) } };
    const original = b.document.querySelectorAll;
    b.document.querySelectorAll = (sel) => (sel === '[data-page]' ? [root] : original(sel));
    b.Admin.page.boot();
    return b;
}
const CUSTOMERS = [
    { id: 41, name: 'أحمد الورشة', phone: null, credit_limit: 0, credit_balance: 250, vehicles: [] },
    { id: 42, name: 'شركة النور', phone: '01099990004', credit_limit: 0, credit_balance: -100, vehicles: [] },
];

test('catalog typing opens reusable suggestions and ArrowDown plus Enter adds the highlighted item', async () => {
    const product = { ...FILTER, id: 91 };
    const b = bootWithSearchResponse([product]);
    const input = typeCatalogSearch(b, 'فلتر');
    await b.flush();

    assert.match(b.element('quickAddResultsDropdown').innerHTML, /فلتر/);
    input.fire('keydown', { key: 'ArrowDown' });
    input.fire('keydown', { key: 'Enter' });

    assert.match(cartHtml(b), /data-action="pos.qty"/);
    assert.equal(input.value, '');
});

test('Enter with no highlighted suggestion keeps the barcode path', async () => {
    const product = { ...FILTER, id: 92, barcode: '123456' };
    const b = bootWithSearchResponse([product]);
    const input = typeCatalogSearch(b, '123456');
    await b.flush();

    input.fire('keydown', { key: 'Enter' });
    await b.flush();

    assert.match(cartHtml(b), /data-action="pos.qty"/);
    assert.ok(b.calls.fetch.some((call) => call.url.includes('exact_barcode=1')));
});

test('a completed sale clears the catalog input without a removed quick-add control', async () => {
    const product = { ...FILTER, id: 93 };
    const config = { ...CONFIG, data: { ...CONFIG.data, products: [product] } };
    const b = createBrowser({
        fetchResponses: {
            '/pos': {
                success: true,
                invoice_id: 1,
                invoice_number: 'INV-1',
                receipt_url: '/receipt/1',
                warranty_cert_url: '/warranty/1',
            },
        },
        globals: { localStorage: { getItem: () => null, setItem() {} } },
    });
    b.load('public/assets/js/admin/pos/math.js');
    b.context.PosMath = b.window.PosMath;
    b.load('public/assets/js/admin/pos/pos.js');
    const root = { dataset: { page: 'pos', config: JSON.stringify(config) } };
    const original = b.document.querySelectorAll;
    b.document.querySelectorAll = (sel) => (sel === '[data-page]' ? [root] : original(sel));
    b.Admin.page.boot();
    b.window.crypto = { randomUUID: () => 'checkout-key' };
    b.element('posTechnicianSelect').value = '1';
    b.element('catalogSearchInput').value = 'ready';

    b.click({ action: 'pos.add', id: '93' });
    b.click({ action: 'pos.submit' });
    await b.flush();

    assert.equal(b.element('catalogSearchInput').value, '');
    assert.equal(b.calls.swal.at(-1)[0].icon, 'success');
});

test('F2 focuses catalog search and F4 clears the cart', () => {
    const b = boot([FILTER]);
    b.click({ action: 'pos.add', id: '22' });

    (b.document.listeners.keydown || []).forEach((fn) => fn({ key: 'F2', preventDefault() {} }));
    (b.document.listeners.keydown || []).forEach((fn) => fn({ key: 'F4', preventDefault() {} }));

    assert.equal(b.element('catalogSearchInput').focused, true);
    assert.match(cartHtml(b), /السلة فارغة/);
});

test('the customer combobox shows the walk-in customer by default', () => {
    const b = bootWithCustomers(CUSTOMERS);

    assert.equal(b.element('posCustomerSelect').value, 'WALK_IN');
    assert.match(b.element('posCustomerSearch').value, /عميل نقدي/);
});

test('typing in the customer combobox filters the listbox', () => {
    const b = bootWithCustomers(CUSTOMERS);
    const input = b.element('posCustomerSearch');
    input.value = 'احمد';
    searchCustomers(b, input.value);

    assert.match(b.element('posCustomerOptions').innerHTML, /أحمد الورشة/);
    assert.doesNotMatch(b.element('posCustomerOptions').innerHTML, /شركة النور/);
});

test('ArrowDown then Enter selects a filtered customer and updates its vehicle control', () => {
    const customers = [{ ...CUSTOMERS[0], vehicles: [{ id: 71, car: 'Toyota', plate_number: 'ABC-71' }] }];
    const b = bootWithCustomers(customers);
    const input = b.element('posCustomerSearch');
    input.value = 'احمد';
    searchCustomers(b, input.value);

    input.fire('keydown', { key: 'ArrowDown' });
    input.fire('keydown', { key: 'Enter' });

    assert.equal(b.element('posCustomerSelect').value, '41');
    assert.match(input.value, /أحمد الورشة/);
    assert.match(b.element('posVehicleSelect').innerHTML, /Toyota/);
});

test('Escape restores the selected customer label', () => {
    const b = bootWithCustomers(CUSTOMERS);
    const input = b.element('posCustomerSearch');
    input.value = 'احمد';
    searchCustomers(b, input.value);

    input.fire('keydown', { key: 'Escape' });

    assert.match(input.value, /عميل نقدي/);
});

test('a customer with no phone shows no phone text, and balances say who owes whom', () => {
    const html = customerOptions(bootWithCustomers(CUSTOMERS));
    assert.ok(html.includes('أحمد الورشة'));
    assert.ok(!html.includes('null') && !html.includes('أحمد الورشة —'), 'no empty phone text');
    assert.ok(html.includes('شركة النور — 01099990004'));
    assert.ok(html.includes('عليه آجل'));
    assert.ok(html.includes('له رصيد'));
});

test('the customer search narrows the list by name, ignoring spelling variants, and keeps the walk-in option', () => {
    const b = bootWithCustomers(CUSTOMERS);

    searchCustomers(b, 'احمد');
    assert.ok(customerOptions(b).includes('أحمد الورشة'), 'alef variant still matches');
    assert.ok(!customerOptions(b).includes('شركة النور'));
    assert.ok(customerOptions(b).includes('value="WALK_IN"'), 'walk-in stays available');

    searchCustomers(b, '0109999');
    assert.ok(customerOptions(b).includes('شركة النور'), 'search by phone');

    searchCustomers(b, '');
    assert.ok(customerOptions(b).includes('أحمد الورشة') && customerOptions(b).includes('شركة النور'));
});

test('typing a name and pressing Enter takes the first match, not the walk-in row', () => {
    const b = bootWithCustomers(CUSTOMERS);
    const input = b.element('posCustomerSearch');
    searchCustomers(b, 'شركة');
    input.fire('focus');
    searchCustomers(b, 'شركة');
    input.fire('keydown', { key: 'Enter' });
    assert.equal(b.element('posCustomerSelect').value, '42');
});

test('suggestions open only for what the cashier is still typing', async () => {
    const product = { ...FILTER, id: 93 };
    const b = bootWithSearchResponse([product]);
    const dropdown = b.element('quickAddResultsDropdown');

    // A category click reloads the catalog with an empty box: no suggestions.
    b.click({ action: 'pos.category', category: 'filters' });
    await b.flush();
    assert.ok(!dropdown.innerHTML.includes('فلتر'), 'no suggestions on a category click');

    // The reply arrives after the cashier left the box: no suggestions.
    typeCatalogSearch(b, 'فلتر');
    b.element('posCustomerSearch').focus();
    await b.flush();
    assert.ok(!dropdown.innerHTML.includes('quick-add-result-item'), 'no suggestions once focus moved on');

    typeCatalogSearch(b, 'فلتر');
    await b.flush();
    assert.match(dropdown.innerHTML, /فلتر/, 'still typing: suggestions open');
});

test('Enter takes the typed match even when the current customer is listed before it', () => {
    const b = bootWithCustomers(CUSTOMERS);
    const input = b.element('posCustomerSearch');

    input.fire('focus');
    searchCustomers(b, 'احمد');
    input.fire('keydown', { key: 'Enter' });
    assert.equal(b.element('posCustomerSelect').value, '41');

    input.fire('focus');
    searchCustomers(b, 'شركة');
    input.fire('keydown', { key: 'Enter' });
    assert.equal(b.element('posCustomerSelect').value, '42');
});

// Default view and seamless loading: there is no "load more" button, so scrolling must always work.
function product(id, name = `صنف ${id}`) {
    return { id, name, price: 10, stock: 5, is_battery: false, category: 'oils', unit: 'piece', unit_label: 'قطعة', step: 1, allows_fraction: false };
}

function pageResponse(data, hasMore = false, lastPage = 3) {
    return { ok: true, json: async () => ({ data, meta: { last_page: lastPage, has_more: hasMore }, category_counts: {} }) };
}

function bootInfinite(fetchImpl, products = [product(1), product(2)]) {
    const observers = [];
    class FakeObserver {
        constructor(callback) {
            this.callback = callback;
            observers.push(this);
        }
        observe() {}
        disconnect() {}
    }
    const b = createBrowser({
        globals: {
            localStorage: { getItem: () => null, setItem() {} },
            IntersectionObserver: FakeObserver,
        },
    });
    b.context.fetch = fetchImpl;
    b.window.IntersectionObserver = FakeObserver;
    b.load('public/assets/js/admin/pos/math.js');
    b.context.PosMath = b.window.PosMath;
    b.load('public/assets/js/admin/pos/pos.js');
    const config = { ...CONFIG, data: { ...CONFIG.data, products } };
    const root = { dataset: { page: 'pos', config: JSON.stringify(config) } };
    const original = b.document.querySelectorAll;
    b.document.querySelectorAll = (sel) => (sel === '[data-page]' ? [root] : original(sel));
    b.Admin.page.boot();
    return { b, scrollToEnd: () => observers[0].callback([{ isIntersecting: true }]) };
}

const gridHtml = (b) => b.element('posCatalogGrid').innerHTML;

test('the catalog opens as the list view', () => {
    const { b } = bootInfinite(async () => pageResponse([]));
    assert.match(gridHtml(b), /pos-catalog-table/);
    assert.match(gridHtml(b), /صنف 1/);
});

test('the view toggle still switches to cards and back', () => {
    const { b } = bootInfinite(async () => pageResponse([]));
    b.click({ action: 'pos.view', mode: 'grid' });
    assert.ok(!gridHtml(b).includes('pos-catalog-table'));
    assert.ok(b.element('viewModeGridBtn').classList.contains('active'));
    b.click({ action: 'pos.view', mode: 'list' });
    assert.match(gridHtml(b), /pos-catalog-table/);
    assert.ok(b.element('viewModeListBtn').classList.contains('active'));
    assert.ok(!b.element('viewModeGridBtn').classList.contains('active'));
});

test('there is no load more button, and the sentinel is observable from the first screen', () => {
    const { b } = bootInfinite(async () => pageResponse([]));
    const blade = readFileSync('resources/views/admin/sales/pos/partials/catalog-panel.blade.php', 'utf8');
    assert.ok(!blade.includes('catalogLoadMoreBtn') && !blade.includes('<button'));
    assert.ok(!b.element('catalogLoadMoreSentinel').classList.contains('d-none'), 'a hidden sentinel never intersects');
});

test('scrolling past the first screen continues under the same rows, without duplicates', async () => {
    const urls = [];
    const { b, scrollToEnd } = bootInfinite(async (url) => {
        urls.push(url);
        return pageResponse([product(2), product(3)], true);
    });

    scrollToEnd();
    assert.match(gridHtml(b), /صنف 1/, 'no skeleton replaces the list while loading');
    assert.ok(!gridHtml(b).includes('pos-skeleton'));
    await b.flush();

    assert.match(urls[0], /page=1/);
    assert.match(gridHtml(b), /صنف 1/);
    assert.match(gridHtml(b), /صنف 3/);
    assert.equal(gridHtml(b).match(/صنف 2/g).length, gridHtml(b).match(/صنف 1/g).length, 'a repeated product is listed once');

    scrollToEnd();
    await b.flush();
    assert.match(urls[1], /page=2/);
});

test('a failed page is requested again, not skipped', async () => {
    const urls = [];
    let fail = true;
    const { b, scrollToEnd } = bootInfinite(async (url) => {
        urls.push(url);
        if (fail) return { ok: false, json: async () => ({}) };
        return pageResponse([product(3)], true);
    });

    scrollToEnd();
    await b.flush();
    assert.match(b.element('catalogLoadMoreStatus').innerHTML, /تعذر/);
    assert.match(gridHtml(b), /صنف 1/, 'the list stays on screen');

    fail = false;
    scrollToEnd();
    await b.flush();
    assert.match(urls[1], /page=1/);
    assert.match(gridHtml(b), /صنف 3/);
    assert.equal(b.element('catalogLoadMoreStatus').innerHTML, '');
});

test('a category click wins over a page still loading in the background', async () => {
    const pending = [];
    const { b, scrollToEnd } = bootInfinite((url) => new Promise((resolve) => pending.push({ url, resolve })));

    scrollToEnd();
    b.click({ action: 'pos.category', category: 'filters' });
    assert.equal(pending.length, 2, 'the click was not dropped');
    assert.match(pending[1].url, /category=filters/);

    pending[1].resolve(pageResponse([product(50, 'صنف الفلاتر')]));
    await b.flush();
    pending[0].resolve(pageResponse([product(60, 'صنف قديم')], true));
    await b.flush();

    assert.match(gridHtml(b), /صنف الفلاتر/);
    assert.ok(!gridHtml(b).includes('صنف قديم'), 'the late background page is discarded');
    assert.ok(b.element('catalogLoadMoreSentinel').classList.contains('d-none'), 'no more pages in this category');
});

test('search and F2 still work with the list view as default', async () => {
    const { b } = bootInfinite(async () => pageResponse([product(70, 'زيت البحث')]));
    typeCatalogSearch(b, 'زيت');
    await b.flush();
    assert.match(gridHtml(b), /pos-catalog-table/);
    assert.match(gridHtml(b), /زيت البحث/);

    (b.document.listeners.keydown || []).forEach((fn) => fn({ key: 'F2', preventDefault() {} }));
    assert.equal(b.document.activeElement, b.element('catalogSearchInput'));
});

// Oil sold by what was poured: a liter product from sealed 5 L jerrycans.
const SHELL = { id: 81, name: 'شيل أصفر 15W-50', price: 195, stock: 931.5, is_battery: false, category: 'oils', unit: 'liter', unit_label: 'لتر', step: 0.25, allows_fraction: true, package_size: 5, package_label: 'جركن' };

test('catalog stock shows liters and the jerrycans they equal', () => {
    const b = boot([SHELL]);
    const html = collectAll(b);
    assert.match(html, /931\.5/);
    assert.ok(html.includes('(ما يعادل 186 جركن و 1.5 لتر)'));
});

test('no breakdown below one package, or for a product without a package size', () => {
    assert.ok(!collectAll(boot([{ ...SHELL, stock: 4 }])).includes('ما يعادل'));
    assert.ok(!collectAll(boot([OIL])).includes('ما يعادل'));
});

test('adding oil starts as one full package and shows the liter presets', () => {
    const b = boot([SHELL]);
    b.click({ action: 'pos.add', id: '81' });
    const html = cartHtml(b);
    assert.ok(html.includes('value="5"'), 'a scan means one sealed jerrycan');
    assert.ok(html.includes('اللترات المستخدمة (لتر)'));
    for (const label of ['3.5 لتر', '4 لتر', '4.5 لتر', '5 لتر', 'جركن كامل']) {
        assert.ok(html.includes(label), label);
    }
});

test('a preset sets the liters poured, and the line total follows', () => {
    const b = boot([SHELL]);
    b.click({ action: 'pos.add', id: '81' });
    const key = lineKey(b);

    b.click({ action: 'pos.qty-preset', key, qty: '3.5' });
    assert.ok(cartHtml(b).includes('value="3.5"'));
    assert.ok(cartHtml(b).includes('682.5'), '3.5 × 195');

    typeQty(b, key, '4.25');
    assert.ok(cartHtml(b).includes('value="4.25"'), 'a typed decimal works too');
    assert.ok(cartHtml(b).includes('828.75'), '4.25 × 195');
});

test('the stepper fine-tunes liters, adding from the catalog again adds another package', () => {
    const b = boot([SHELL]);
    b.click({ action: 'pos.add', id: '81' });
    const key = lineKey(b);

    b.click({ action: 'pos.qty', key, delta: '1' });
    assert.ok(cartHtml(b).includes('value="5.25"'));
    b.click({ action: 'pos.qty', key, delta: '-1' });
    assert.ok(cartHtml(b).includes('value="5"'));

    b.click({ action: 'pos.add', id: '81' });
    assert.ok(cartHtml(b).includes('value="10"'));
});

test('liters are capped at the stock when selling below zero is off', () => {
    const b = boot([{ ...SHELL, stock: 3 }]);
    b.click({ action: 'pos.add', id: '81' });
    assert.ok(cartHtml(b).includes('value="3"'), 'less than a full package left');
    b.click({ action: 'pos.qty-preset', key: lineKey(b), qty: '4.5' });
    assert.ok(cartHtml(b).includes('value="3"'));
});

test('a product still sold by the piece gets no liter presets', () => {
    const b = boot([FILTER]);
    b.click({ action: 'pos.add', id: '22' });
    assert.ok(!cartHtml(b).includes('اللترات المستخدمة'));
});

test('a liter product without a package size has presets but no full package choice', () => {
    const b = boot([OIL]);
    b.click({ action: 'pos.add', id: '21' });
    assert.ok(cartHtml(b).includes('اللترات المستخدمة'));
    assert.ok(!cartHtml(b).includes('كامل'));
    assert.ok(cartHtml(b).includes('value="0.25"'), 'still starts at one step');
});
