// Minimal browser stand-in for running admin page modules under node:test (frontend F4).
// Elements are created on demand by id; fetch, Swal, bootstrap.Modal and timers are recorded.
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

export function createBrowser({ fetchResponses = {}, globals = {} } = {}) {
    const listeners = {};
    const elements = {};
    const calls = { fetch: [], swal: [], modal: [], intervals: [], cleared: [] };

    function element(id) {
        if (!elements[id]) {
            const handlers = {};
            const attributes = {};
            const classes = new Set();
            elements[id] = {
                id, value: '', textContent: '', innerHTML: '', dataset: {}, style: {}, options: [], scrollLeft: 0, scrollWidth: 0, clientWidth: 0, scrollByCalls: [],
                setAttribute: (name, value) => { attributes[name] = String(value); },
                getAttribute: (name) => attributes[name] || null,
                classList: {
                    add: (...names) => names.forEach((name) => classes.add(name)),
                    remove: (...names) => names.forEach((name) => classes.delete(name)),
                    toggle: (name, force) => {
                        const shouldAdd = force === undefined ? !classes.has(name) : force;
                        if (shouldAdd) classes.add(name);
                        else classes.delete(name);
                        return shouldAdd;
                    },
                    contains: (name) => classes.has(name),
                },
                addEventListener: (type, fn) => { (handlers[type] ||= []).push(fn); },
                fire: (type, extra = {}) => (handlers[type] || []).forEach((fn) => fn({ type, preventDefault() {}, ...extra })),
                querySelector: () => null,
                querySelectorAll: () => [],
                closest: () => null,
                focus() {
                    this.focused = true;
                    document.activeElement = this;
                },
                select() { this.selected = true; },
                scrollBy(options) { this.scrollByCalls.push(options); },
                remove() {},
            };
        }
        return elements[id];
    }

    const document = {
        readyState: 'complete',
        addEventListener: (type, fn) => { (listeners[type] ||= []).push(fn); },
        removeEventListener: (type, fn) => { listeners[type] = (listeners[type] || []).filter((f) => f !== fn); },
        listeners,
        getElementById: element,
        createElement: () => element(`generated-${Object.keys(elements).length}`),
        body: { appendChild() {} },
        querySelector: (sel) => (sel.startsWith('meta[name="csrf-token"]') ? { getAttribute: () => 'csrf-test' } : null),
        querySelectorAll: () => [],
    };

    const window = {
        location: { search: '', reloads: 0, reload() { this.reloads++; } },
        listeners: {},
        addEventListener(type, fn) { (this.listeners[type] ||= []).push(fn); },
        removeEventListener(type, fn) { this.listeners[type] = (this.listeners[type] || []).filter((f) => f !== fn); },
        console,
    };

    const context = vm.createContext({
        window, document, console, URLSearchParams, Promise, JSON, Math, Date, String, Number, Object, Array,
        performance: { now: () => Date.now() },
        CSS: { escape: (s) => s },
        setTimeout: (fn) => { fn(); return 0; },
        setInterval: (fn, ms) => { calls.intervals.push(ms); return calls.intervals.length; },
        clearInterval: (id) => calls.cleared.push(id),
        clearTimeout: () => {},
        bootstrap: { Modal: function (el) { this.show = () => calls.modal.push(['show', el?.id]); this.hide = () => calls.modal.push(['hide', el?.id]); } },
        ...globals,
        Swal: { fire: (...args) => { calls.swal.push(args); return Promise.resolve({ isConfirmed: false }); }, showValidationMessage() {} },
        fetch: (url, options = {}) => {
            calls.fetch.push({ url, method: options.method || 'GET', body: options.body ? JSON.parse(options.body) : null });
            const key = Object.keys(fetchResponses).find((k) => url.startsWith(k));
            const data = key ? fetchResponses[key] : {};
            return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve(data) });
        },
    });
    window.Admin = undefined;

    function load(path) {
        vm.runInContext(readFileSync(new URL('../../' + path, import.meta.url), 'utf8'), context, { filename: path });
    }

    // Admin core attaches itself to `window`; page modules reference the global `Admin`.
    load('public/assets/js/admin/core.js');
    context.Admin = window.Admin;

    function click(dataset) {
        const target = { tagName: 'BUTTON', dataset, getAttribute: () => null, closest(sel) { return sel === '[data-action]' ? this : null; } };
        (listeners.click || []).forEach((fn) => fn({ type: 'click', target, preventDefault() {} }));
    }

    const flush = () => new Promise((resolve) => setImmediate(resolve));

    return { window, document, Admin: window.Admin, element, calls, load, click, flush, ids: () => Object.keys(elements), context };
}
