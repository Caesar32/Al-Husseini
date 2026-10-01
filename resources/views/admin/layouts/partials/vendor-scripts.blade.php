<!-- JAVASCRIPT -->
<script src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="{{ asset('assets/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ asset('assets/libs/node-waves/waves.min.js') }}"></script>
<script src="{{ asset('assets/libs/feather-icons/feather.min.js') }}"></script>
<script src="{{ asset('assets/js/pages/plugins/lord-icon-2.1.0.js') }}"></script>
<script src="{{ asset('assets/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ asset('assets/libs/choices.js/public/assets/scripts/choices.min.js') }}"></script>
<script src="{{ asset('assets/js/plugins.js') }}"></script>


<!-- App js -->
<script src="{{ asset('assets/js/app.js') }}"></script>

<!-- Global Spotlight Search Engine -->
<script src="{{ asset('assets/js/global-spotlight-search.js') }}"></script>

@yield('script')
@stack('scripts')

<!-- Theme Icon & Sidebar State Sync (Bulletproof & Immediate) -->
<script>
(function () {
    // 1. Theme State Helper
    function getStoredTheme() {
        return localStorage.getItem('data-bs-theme') || sessionStorage.getItem('data-bs-theme') || document.documentElement.getAttribute('data-bs-theme') || 'light';
    }

    // 2. Synchronize all theme icons across the DOM
    function syncThemeUI(theme) {
        var isDark = theme === 'dark';
        var icons = document.querySelectorAll('.light-dark-mode i, #light-dark-mode-toggle i');
        icons.forEach(function (icon) {
            icon.className = isDark ? 'bx bx-sun fs-22' : 'bx bx-moon fs-22';
        });

        // Also sync customizer radio if present
        var radio = document.querySelector('input[name="data-bs-theme"][value="' + theme + '"]');
        if (radio) {
            radio.checked = true;
        }
    }

    // 3. Centralized theme applicator
    window.applyThemeMode = function (targetTheme) {
        var theme = targetTheme || (document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark');
        
        document.documentElement.setAttribute('data-bs-theme', theme);
        document.documentElement.setAttribute('data-topbar', theme === 'dark' ? 'dark' : 'light');

        try {
            localStorage.setItem('data-bs-theme', theme);
            sessionStorage.setItem('data-bs-theme', theme);
        } catch (err) {}

        syncThemeUI(theme);

        // Notify charts and responsive components
        window.dispatchEvent(new Event('resize'));
        window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: theme } }));
    };

    // 4. Setup clean toggle listeners (replaces elements to discard conflicting legacy listeners from app.js)
    function attachThemeToggleListener() {
        var buttons = document.querySelectorAll('.light-dark-mode, #light-dark-mode-toggle');
        buttons.forEach(function (btn) {
            // Clone node without existing listeners
            var cleanBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(cleanBtn, btn);

            cleanBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();

                var current = document.documentElement.getAttribute('data-bs-theme') || 'light';
                var next = current === 'dark' ? 'light' : 'dark';
                window.applyThemeMode(next);
            }, true); // Capture phase ensures execution before bubbling
        });
    }

    // 5. Initial Run
    var initialTheme = getStoredTheme();
    document.documentElement.setAttribute('data-bs-theme', initialTheme);
    syncThemeUI(initialTheme);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            attachThemeToggleListener();
            syncThemeUI(document.documentElement.getAttribute('data-bs-theme'));
        });
    } else {
        attachThemeToggleListener();
    }

    // Re-verify after full window load (when app.js has completed)
    window.addEventListener('load', function () {
        attachThemeToggleListener();
        syncThemeUI(document.documentElement.getAttribute('data-bs-theme'));
    });

    // 6. MutationObserver for external changes (like sidebar size)
    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.attributeName === 'data-bs-theme') {
                var current = document.documentElement.getAttribute('data-bs-theme');
                syncThemeUI(current);
                try {
                    localStorage.setItem('data-bs-theme', current);
                    sessionStorage.setItem('data-bs-theme', current);
                } catch (e) {}
            }
            if (mutation.attributeName === 'data-sidebar-size') {
                var size = document.documentElement.getAttribute('data-sidebar-size');
                if (size) {
                    try {
                        sessionStorage.setItem('data-sidebar-size', size);
                    } catch (e) {}
                }
            }
        });
    });

    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme', 'data-sidebar-size']
    });
})();
</script>

<script>
// Global Toast Alert Helper for HR System
window.showHrToast = function(title, message, type) {
    const isLate = type === 'lateness';
    const bgClass = isLate ? '#e63946' : '#2a9d8f';
    
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: `<span class="fs-15 fw-bold">${title}</span>`,
            html: `<div class="fs-13 text-muted text-start">${message}</div>`,
            icon: isLate ? 'warning' : 'info',
            toast: true,
            position: 'top-start',
            showConfirmButton: false,
            timer: 4500,
            timerProgressBar: true,
            background: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? '#212529' : '#ffffff',
            color: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? '#f8f9fa' : '#212529'
        });
    }
};

</script>
<!-- Al-Husseini Real-Time Admin Notifications -->
<script src="{{ asset('assets/js/admin-notifications.js') }}"></script>

<!-- Seamless Fullscreen Engine (نظام ملء الشاشة المستمر والتنقل السلس الاحترافي) -->
<style>
#alhusseini-top-progress {
    position: fixed;
    top: 0;
    left: 0;
    width: 0%;
    height: 3px;
    background: linear-gradient(90deg, #3577f1, #0ab39c);
    box-shadow: 0 0 10px rgba(53, 119, 241, 0.7);
    z-index: 9999999;
    transition: width 0.2s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.25s ease;
    pointer-events: none;
    opacity: 0;
}
</style>
<div id="alhusseini-top-progress"></div>

<script>
(function() {
    'use strict';

    // Clear any legacy storage
    try { localStorage.removeItem('alhusseini-fullscreen'); } catch(e) {}

    const btnSelector = '[data-toggle="fullscreen"]';
    const progressBar = document.getElementById('alhusseini-top-progress');

    // Progress bar controls
    let progressTimer = null;
    function startProgress() {
        if (!progressBar) return;
        clearTimeout(progressTimer);
        progressBar.style.opacity = '1';
        progressBar.style.width = '35%';
        progressTimer = setTimeout(function() {
            progressBar.style.width = '75%';
        }, 150);
    }

    function finishProgress() {
        if (!progressBar) return;
        clearTimeout(progressTimer);
        progressBar.style.width = '100%';
        setTimeout(function() {
            progressBar.style.opacity = '0';
            setTimeout(function() {
                progressBar.style.width = '0%';
            }, 250);
        }, 150);
    }

    function isFullscreen() {
        return !!(
            document.fullscreenElement ||
            document.webkitFullscreenElement ||
            document.mozFullScreenElement ||
            document.msFullscreenElement ||
            (window.innerHeight === screen.height && window.innerWidth === screen.width)
        );
    }

    function updateIcons(active) {
        const btns = document.querySelectorAll(btnSelector);
        btns.forEach(function(btn) {
            const icon = btn.querySelector('i');
            if (!icon) return;
            if (active) {
                icon.className = 'bx bx-exit-fullscreen fs-22';
                btn.setAttribute('title', 'الخروج من وضع ملء الشاشة');
            } else {
                icon.className = 'bx bx-fullscreen fs-22';
                btn.setAttribute('title', 'وضع ملء الشاشة المستمر');
            }
        });
    }

    function enterFullscreen() {
        const el = document.documentElement;
        const req = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
        if (req) {
            try {
                const p = req.call(el, Element.ALLOW_KEYBOARD_INPUT || undefined);
                if (p && p.catch) p.catch(function(){});
            } catch(e) {}
        }
        updateIcons(true);
    }

    function exitFullscreen() {
        const exit = document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen || document.msExitFullscreen;
        if (exit && (document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement)) {
            try {
                const p = exit.call(document);
                if (p && p.catch) p.catch(function(){});
            } catch(e) {}
        }
        updateIcons(false);
    }

    // Attach button listener
    function attachButtonListeners() {
        const btns = document.querySelectorAll(btnSelector);
        btns.forEach(function(btn) {
            if (btn.dataset.fsAttached) return;
            btn.dataset.fsAttached = '1';

            // Clone to strip any conflicting legacy listeners
            const clone = btn.cloneNode(true);
            btn.parentNode.replaceChild(clone, btn);

            clone.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                if (isFullscreen()) {
                    exitFullscreen();
                } else {
                    enterFullscreen();
                }
            });
        });
        updateIcons(isFullscreen());
    }

    // ==============================================================
    // Seamless Navigation Engine (Prevents Document Unload in Fullscreen)
    // ==============================================================
    let navAbort = null;

    async function seamlessNavigate(url, pushState = true) {
        if (navAbort) {
            navAbort.abort();
        }
        navAbort = new AbortController();

        startProgress();

        try {
            const res = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Al-Husseini-Seamless': '1'
                },
                signal: navAbort.signal
            });

            if (!res.ok) {
                window.location.href = url;
                return;
            }

            const html = await res.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            const currentMain = document.querySelector('.main-content');
            const newMain = doc.querySelector('.main-content');

            if (!currentMain || !newMain) {
                window.location.href = url;
                return;
            }

            // Update title
            if (doc.title) {
                document.title = doc.title;
            }

            // Update URL in browser
            if (pushState) {
                window.history.pushState({ seamless: true, url: url }, doc.title, url);
            }

            // Content Swap with subtle micro-transition
            currentMain.style.opacity = '0.4';
            currentMain.style.transition = 'opacity 0.08s ease';

            setTimeout(function() {
                currentMain.innerHTML = newMain.innerHTML;
                currentMain.style.opacity = '1';

                // Sync sidebar active link
                syncSidebar(url);

                // Run page-specific scripts
                runPageScripts(doc, currentMain);

                // Re-initialize core UI widgets
                reinitWidgets();

                finishProgress();
            }, 80);

        } catch (err) {
            if (err.name !== 'AbortError') {
                console.warn('Seamless navigation fell back to native:', err);
                window.location.href = url;
            }
        }
    }

    function syncSidebar(targetUrl) {
        try {
            const urlObj = new URL(targetUrl, window.location.origin);
            const path = urlObj.pathname;
            document.querySelectorAll('#scrollbar .nav-link, .app-menu .nav-link').forEach(function(link) {
                const linkHref = link.getAttribute('href');
                if (!linkHref) return;
                try {
                    const lUrl = new URL(linkHref, window.location.origin);
                    if (lUrl.pathname === path) {
                        link.classList.add('active');
                        const collapse = link.closest('.collapse');
                        if (collapse) {
                            collapse.classList.add('show');
                        }
                    } else {
                        link.classList.remove('active');
                    }
                } catch(e) {}
            });
        } catch(e) {}
    }

    function runPageScripts(doc, container) {
        // Collect scripts: inside new .main-content and any trailing scripts after #layout-wrapper
        const scriptList = [];
        container.querySelectorAll('script').forEach(function(s) { scriptList.push(s); });
        
        doc.querySelectorAll('body > script').forEach(function(s) {
            const src = s.getAttribute('src') || '';
            // Skip core vendor scripts that are already loaded globally in parent
            if (src.includes('bootstrap') || src.includes('app.js') || src.includes('plugins.js') || src.includes('vendor-scripts') || src.includes('admin-notifications')) {
                return;
            }
            scriptList.push(s);
        });

        scriptList.forEach(function(s) {
            if (s.src) {
                if (!document.querySelector(`script[src="${s.src}"]`)) {
                    const el = document.createElement('script');
                    el.src = s.src;
                    el.async = false;
                    document.body.appendChild(el);
                }
            } else if (s.textContent.trim()) {
                const rawCode = s.textContent;
                // Convert top-level let/const to var to prevent SyntaxError on repeat visits
                const safeCode = rawCode
                    .replace(/(^|\n|\r|\;)\s*let\s+([a-zA-Z0-9_$]+)/g, '$1var $2')
                    .replace(/(^|\n|\r|\;)\s*const\s+([a-zA-Z0-9_$]+)/g, '$1var $2');

                try {
                    (1, eval)(safeCode);
                } catch(e) {
                    console.warn('Script execution fallback:', e);
                }
            }
        });
    }

    function reinitWidgets() {
        if (typeof feather !== 'undefined') {
            try { feather.replace(); } catch(e){}
        }
        if (typeof SimpleBar !== 'undefined') {
            document.querySelectorAll('[data-simplebar]').forEach(function(el) {
                try { new SimpleBar(el); } catch(e){}
            });
        }
        try {
            document.dispatchEvent(new Event('DOMContentLoaded'));
            window.dispatchEvent(new Event('load'));
            window.dispatchEvent(new Event('resize'));
        } catch(e){}
    }

    // Intercept link clicks when in fullscreen
    document.addEventListener('click', function(e) {
        if (!isFullscreen()) return; // Standard behavior when not in fullscreen

        const link = e.target.closest('a');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
        if (link.hasAttribute('download') || link.getAttribute('target') === '_blank') return;
        if (link.dataset.noPjax || link.dataset.native || link.dataset.toggle === 'fullscreen') return;

        const url = new URL(link.href, window.location.origin);
        if (url.origin !== window.location.origin) return;
        if (url.pathname.includes('/logout') || url.pathname.includes('/api/')) return;
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;

        e.preventDefault();
        seamlessNavigate(url.href, true);
    }, true);

    // Intercept GET filter/search forms when in fullscreen
    document.addEventListener('submit', function(e) {
        if (!isFullscreen()) return;

        const form = e.target;
        if (!form || (form.method || '').toUpperCase() !== 'GET') return;
        if (form.dataset.noPjax || form.target === '_blank') return;

        const action = form.action || window.location.href;
        const url = new URL(action, window.location.origin);
        if (url.origin !== window.location.origin) return;

        e.preventDefault();
        const formData = new FormData(form);
        const searchParams = new URLSearchParams(formData);
        const targetUrl = url.pathname + (searchParams.toString() ? '?' + searchParams.toString() : '');
        seamlessNavigate(targetUrl, true);
    }, true);

    // Intercept Refresh (F5 and Ctrl+R) when in fullscreen
    window.addEventListener('keydown', function(e) {
        if (isFullscreen()) {
            if (e.key === 'F5' || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'r')) {
                e.preventDefault();
                seamlessNavigate(window.location.href, false);
            }
        }
    });

    // Handle browser Back / Forward buttons
    window.addEventListener('popstate', function() {
        if (isFullscreen()) {
            seamlessNavigate(window.location.href, false);
        }
    });

    // Monitor fullscreen changes (Esc key, F11, etc.)
    ['fullscreenchange','webkitfullscreenchange','mozfullscreenchange','MSFullscreenChange'].forEach(function(ev) {
        document.addEventListener(ev, function() {
            updateIcons(isFullscreen());
        });
    });

    window.addEventListener('resize', function() {
        updateIcons(isFullscreen());
    });

    function init() {
        attachButtonListeners();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    window.addEventListener('load', init);
})();
</script>

<!-- CSRF & Session Keep-Alive for Long Running Center Shifts -->
<script>
(function() {
    window.getCsrfToken = function() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
    };

    function refreshCsrfToken() {
        fetch("{{ route('refresh_csrf') }}", {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data && data.csrf_token) {
                const metaToken = document.querySelector('meta[name="csrf-token"]');
                if (metaToken) {
                    metaToken.setAttribute('content', data.csrf_token);
                }
                document.querySelectorAll('input[name="_token"]').forEach(function(input) {
                    input.value = data.csrf_token;
                });
            }
        })
        .catch(function(err) {
            // silent catch on network hiccups
        });
    }

    // Refresh every 15 minutes to guarantee session stays active all day
    setInterval(refreshCsrfToken, 15 * 60 * 1000);

    // Refresh automatically when tab is focused / restored
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            refreshCsrfToken();
        }
    });
})();
</script>

