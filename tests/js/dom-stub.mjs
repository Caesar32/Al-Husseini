// Minimal browser stand-in for running admin page modules under node:test (frontend F4).
// Elements are created on demand by id; fetch, Swal, bootstrap.Modal and timers are recorded.
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

export function createBrowser({ fetchResponses = {} } = {}) {
    const listeners = {};
    const elements = {};
    const calls = { fetch: [], swal: [], modal: [], intervals: [], cleared: [] };

    function element(id) {
        if (!elements[id]) {
            const handlers = {};
            elements[id] = {
                id, value: '', textContent: '', innerHTML: '', dataset: {}, style: {},
                classList: { add() {}, remove() {}, toggle() {}, contains: () => false },
                addEventListener: (type, fn) => { (handlers[type] ||= []).push(fn); },
                fire: (type, extra = {}) => (handlers[type] || []).forEach((fn) => fn({ type, preventDefault() {}, ...extra })),
                querySelector: () => null,
                querySelectorAll: () => [],
            };
        }
        return elements[id];
    }

    const document = {
        readyState: 'complete',
        addEventListener: (type, fn) => { (listeners[type] ||= []).push(fn); },
        getElementById: element,
        querySelector: (sel) => (sel.startsWith('meta[name="csrf-token"]') ? { getAttribute: () => 'csrf-test' } : null),
        querySelectorAll: () => [],
    };

    const window = {
        location: { search: '' },
        console,
    };

    const context = vm.createContext({
        window, document, console, URLSearchParams, Promise, JSON, Math, Date, String, Number, Object, Array,
        CSS: { escape: (s) => s },
        setTimeout: (fn) => { fn(); return 0; },
        setInterval: (fn, ms) => { calls.intervals.push(ms); return calls.intervals.length; },
        clearInterval: (id) => calls.cleared.push(id),
        bootstrap: { Modal: function (el) { this.show = () => calls.modal.push(['show', el?.id]); this.hide = () => calls.modal.push(['hide', el?.id]); } },
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
        const target = { tagName: 'BUTTON', dataset, getAttribute: () => null, closest() { return this; } };
        (listeners.click || []).forEach((fn) => fn({ type: 'click', target, preventDefault() {} }));
    }

    const flush = () => new Promise((resolve) => setImmediate(resolve));

    return { window, document, Admin: window.Admin, element, calls, load, click, flush };
}
