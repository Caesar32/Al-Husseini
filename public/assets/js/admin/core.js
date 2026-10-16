/**
 * Al-Husseini admin core (frontend roadmap F4).
 *
 * Loaded once by the layout, before page scripts. Gives page modules:
 *   Admin.esc(value)                 the single HTML escaper
 *   Admin.fetchJson(url, options)    CSRF + JSON headers; rejects with {status, message, errors, data}
 *   Admin.form.*                     show/clear validation errors, busy buttons
 *   Admin.actions.register(name, fn) delegated [data-action] handlers (click/change/input/submit)
 *   Admin.page.register(name, init)  page modules; init(root, config) runs for [data-page="name"]
 *   Admin.page.onLeave(fn)           cleanup (timers, pollers) before the next AJAX navigation
 *   Admin.toast / Admin.confirm      thin SweetAlert2 wrappers
 *
 * Seamless navigation: the layout engine loads a page's <script src> once, swaps <main>, then calls
 * Admin.page.boot(). Modules therefore only *register*; they never wait for DOMContentLoaded.
 */
(function () {
    'use strict';

    if (window.Admin) {
        return;
    }

    const ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, (c) => ESCAPES[c]);
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function meta(name) {
        return document.querySelector(`meta[name="${name}"]`)?.content;
    }

    /** "route-x" meta tag URL with __ID__ replaced. */
    function route(name, id) {
        const url = meta('route-' + name);
        return id === undefined || !url ? url : url.replace('__ID__', encodeURIComponent(id));
    }

    async function fetchJson(url, options = {}) {
        const method = (options.method || 'GET').toUpperCase();
        const headers = Object.assign({
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        }, options.headers || {});
        let body = options.body;

        if (method !== 'GET' && method !== 'HEAD') {
            headers['X-CSRF-TOKEN'] = csrfToken();
            if (body && !(body instanceof FormData) && typeof body !== 'string') {
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify(body);
            }
        }

        const response = await fetch(url, { method, headers, body, signal: options.signal, credentials: 'same-origin' });
        let data = null;
        try {
            data = await response.json();
        } catch (e) {
            data = null;
        }

        if (!response.ok) {
            const error = new Error((data && data.message) || ('HTTP ' + response.status));
            error.status = response.status;
            error.errors = (data && data.errors) || {};
            error.data = data;
            throw error;
        }

        return data;
    }

    const form = {
        clearErrors(formEl) {
            formEl.querySelectorAll('.is-invalid').forEach((el) => {
                el.classList.remove('is-invalid');
                el.removeAttribute('aria-invalid');
            });
            formEl.querySelectorAll('[data-error-for]').forEach((el) => el.remove());
        },
        showErrors(formEl, errors) {
            form.clearErrors(formEl);
            Object.entries(errors || {}).forEach(([field, messages]) => {
                const name = field.replace(/\.(\w+)/g, '[$1]');
                const input = formEl.querySelector(`[name="${name}"]`) || formEl.querySelector(`#${CSS.escape(field)}`);
                if (!input) {
                    return;
                }
                input.classList.add('is-invalid');
                input.setAttribute('aria-invalid', 'true');
                const feedback = document.createElement('div');
                feedback.className = 'invalid-feedback d-block';
                feedback.dataset.errorFor = field;
                feedback.textContent = [].concat(messages)[0];
                (input.closest('.input-group') || input).insertAdjacentElement('afterend', feedback);
            });
        },
        setBusy(button, busy, busyHtml) {
            if (!button) {
                return;
            }
            if (busy) {
                button.dataset.idleHtml = button.innerHTML;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                if (busyHtml) {
                    button.innerHTML = busyHtml;
                }
            } else {
                button.disabled = false;
                button.removeAttribute('aria-busy');
                if (button.dataset.idleHtml !== undefined) {
                    button.innerHTML = button.dataset.idleHtml;
                    delete button.dataset.idleHtml;
                }
            }
        },
    };

    /* Delegated actions: <button data-action="name" data-x="..."> -> handler(element, event). */
    const handlers = {};
    const actions = {
        register(name, handler) {
            handlers[name] = handler;
        },
        registerAll(map) {
            Object.entries(map).forEach(([name, handler]) => actions.register(name, handler));
        },
    };

    function dispatch(event) {
        const el = event.target.closest('[data-action]');
        if (!el) {
            return;
        }
        // An element may declare the event it reacts to (data-action-on="change"); default:
        // submit for forms, change for selects/checkboxes/radios, input for text fields, else click.
        const tag = el.tagName;
        const type = (el.getAttribute('type') || '').toLowerCase();
        const expected = el.dataset.actionOn || (tag === 'FORM' ? 'submit'
            : (tag === 'SELECT' || type === 'checkbox' || type === 'radio' || type === 'date') ? 'change'
            : (tag === 'INPUT' || tag === 'TEXTAREA') ? 'input' : 'click');
        if (expected !== event.type) {
            return;
        }
        const handler = handlers[el.dataset.action];
        if (!handler) {
            return;
        }
        if (event.type === 'submit' || el.tagName === 'A') {
            event.preventDefault();
        }
        handler(el, event);
    }

    ['click', 'change', 'input', 'submit', 'keyup'].forEach((type) => document.addEventListener(type, dispatch));

    /* Page modules. */
    const pages = {};
    let leaveHooks = [];
    const page = {
        register(name, init) {
            pages[name] = init;
        },
        onLeave(fn) {
            leaveHooks.push(fn);
        },
        leave() {
            const hooks = leaveHooks;
            leaveHooks = [];
            hooks.forEach((fn) => {
                try {
                    fn();
                } catch (e) {
                    console.warn('Page cleanup failed:', e);
                }
            });
        },
        boot(scope) {
            (scope || document).querySelectorAll('[data-page]').forEach((root) => {
                if (root.dataset.pageBooted === '1') {
                    return;
                }
                const init = pages[root.dataset.page];
                if (!init) {
                    return;
                }
                root.dataset.pageBooted = '1';
                let config = {};
                try {
                    config = root.dataset.config ? JSON.parse(root.dataset.config) : {};
                } catch (e) {
                    console.warn('Invalid page config for', root.dataset.page, e);
                }
                try {
                    init(root, config);
                } catch (e) {
                    console.warn('Page init failed:', root.dataset.page, e);
                }
            });
        },
    };

    function toast(icon, title, text) {
        if (window.Swal) {
            return window.Swal.fire({ icon, title, text, confirmButtonText: 'حسناً' });
        }
        window.alert([title, text].filter(Boolean).join('\n'));
        return Promise.resolve();
    }

    function confirm(options) {
        if (window.Swal) {
            return window.Swal.fire(Object.assign({ icon: 'warning', showCancelButton: true, cancelButtonText: 'إلغاء' }, options))
                .then((result) => result.isConfirmed);
        }
        return Promise.resolve(window.confirm(options.text || options.title || ''));
    }

    window.Admin = { esc, meta, route, fetchJson, form, actions, page, toast, confirm };

    // Shared actions.
    actions.register('print', () => window.print());

    // <button data-action="toggle-password" data-target="inputId"><i class="ri-eye-line"></i></button>
    actions.register('toggle-password', (button) => {
        const input = document.getElementById(button.dataset.target);
        if (!input) {
            return;
        }
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        const icon = button.querySelector('i');
        if (icon) {
            icon.classList.toggle('ri-eye-line', !show);
            icon.classList.toggle('ri-eye-off-line', show);
        }
        button.setAttribute('aria-pressed', show ? 'true' : 'false');
    });

    // Generic small actions (replace one-line inline handlers).
    actions.registerAll({
        // <select data-action="submit-form"> / <input type="file" data-action="submit-form" data-action-on="change" data-form="id">
        'submit-form': (el) => {
            const form = el.dataset.form ? document.getElementById(el.dataset.form) : el.form;
            form?.submit();
        },
        // <form data-action="prevent">: a form that must never submit (live search boxes).
        prevent: () => {},
        // <button data-action="focus-select" data-target="inputId">
        'focus-select': (el) => {
            const input = document.getElementById(el.dataset.target);
            input?.focus();
            input?.select?.();
        },
        'scroll-top': () => window.scrollTo({ top: 0, behavior: 'smooth' }),
    });

    // data-confirm-message asks before proceeding (replaces onclick/onsubmit="return confirm(...)").
    // On a button or link it guards the click; on a form it guards the submit. Capture phase, so it
    // runs before any other handler.
    document.addEventListener('click', (event) => {
        const el = event.target.closest('[data-confirm-message]');
        if (el && el.tagName !== 'FORM' && !window.confirm(el.dataset.confirmMessage)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (form.dataset && form.dataset.confirmMessage && !window.confirm(form.dataset.confirmMessage)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => page.boot());
    } else {
        page.boot();
    }
})();
