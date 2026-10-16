// Behaviour tests for public/assets/js/admin/core.js (frontend F4). Run: node --test tests/js
// A tiny DOM stub is enough: core only needs listeners, closest(), dataset and querySelectorAll.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function makeElement({ tag = 'BUTTON', attrs = {}, dataset = {}, parent = null } = {}) {
    const el = {
        tagName: tag,
        dataset: { ...dataset },
        parent,
        getAttribute: (name) => attrs[name] ?? null,
        closest(selector) {
            let node = this;
            while (node) {
                if (selector === '[data-action]' && node.dataset.action) return node;
                node = node.parent;
            }
            return null;
        },
    };
    return el;
}

function loadCore({ roots = [] } = {}) {
    const listeners = {};
    const document = {
        readyState: 'complete',
        addEventListener: (type, fn) => { (listeners[type] ||= []).push(fn); },
        querySelector: () => null,
        querySelectorAll: (selector) => (selector === '[data-page]' ? roots : []),
    };
    const window = { console };
    const context = vm.createContext({ window, document, console, CSS: { escape: (s) => s } });
    vm.runInContext(readFileSync(new URL('../../public/assets/js/admin/core.js', import.meta.url), 'utf8'), context);
    const fire = (type, target) => {
        const event = { type, target, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
        (listeners[type] || []).forEach((fn) => fn(event));
        return event;
    };
    return { Admin: window.Admin, fire };
}

test('esc escapes the five HTML-significant characters and nullish values', () => {
    const { Admin } = loadCore();
    assert.equal(Admin.esc(`<a href="x">'&'</a>`), '&lt;a href=&quot;x&quot;&gt;&#39;&amp;&#39;&lt;/a&gt;');
    assert.equal(Admin.esc(null), '');
    assert.equal(Admin.esc(0), '0');
});

test('a click on a nested element runs the closest data-action handler with that element', () => {
    const { Admin, fire } = loadCore();
    const calls = [];
    Admin.actions.register('cart.remove', (el) => calls.push(el.dataset.index));
    const button = makeElement({ dataset: { action: 'cart.remove', index: '2' } });
    const icon = makeElement({ tag: 'I', parent: button });

    fire('click', icon);
    assert.deepEqual(calls, ['2']);
});

test('the default event depends on the element: change for selects, input for text fields, submit for forms', () => {
    const { Admin, fire } = loadCore();
    const seen = [];
    Admin.actions.register('a', (el, e) => seen.push(e.type));
    const select = makeElement({ tag: 'SELECT', dataset: { action: 'a' } });
    const input = makeElement({ tag: 'INPUT', attrs: { type: 'text' }, dataset: { action: 'a' } });
    const form = makeElement({ tag: 'FORM', dataset: { action: 'a' } });

    fire('click', select); fire('change', select);
    fire('change', input); fire('input', input);
    const submit = fire('submit', form);
    assert.deepEqual(seen, ['change', 'input', 'submit']);
    assert.equal(submit.defaultPrevented, true);
});

test('data-action-on overrides the default event', () => {
    const { Admin, fire } = loadCore();
    let count = 0;
    Admin.actions.register('search', () => count++);
    const input = makeElement({ tag: 'INPUT', dataset: { action: 'search', actionOn: 'keyup' } });
    fire('input', input); fire('keyup', input);
    assert.equal(count, 1);
});

test('the print action is registered', () => {
    const { Admin, fire } = loadCore();
    let printed = 0;
    // core captured `window` at load; replace print on the same object.
    Admin.actions.register('print', () => printed++);
    fire('click', makeElement({ dataset: { action: 'print' } }));
    assert.equal(printed, 1);
});

test('boot runs each registered page once with its parsed config; leave runs cleanups once', () => {
    const root = { dataset: { page: 'demo', config: '{"url":"/x","n":2}' } };
    const { Admin } = loadCore({ roots: [root] });
    const runs = [];
    let cleaned = 0;
    Admin.page.register('demo', (el, config) => {
        runs.push(config);
        Admin.page.onLeave(() => cleaned++);
    });

    Admin.page.boot();
    Admin.page.boot();
    // config comes from the VM realm: compare by value.
    assert.deepEqual(JSON.parse(JSON.stringify(runs)), [{ url: '/x', n: 2 }]);

    Admin.page.leave();
    Admin.page.leave();
    assert.equal(cleaned, 1);
});
