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

<!-- Al-Husseini Core Stores (Available to all views) -->
<script src="{{ asset('assets/js/sales-store.js') }}"></script>
<script src="{{ asset('assets/js/hr-store.js') }}"></script>

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

