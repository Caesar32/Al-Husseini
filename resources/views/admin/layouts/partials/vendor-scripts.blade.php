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

<!-- Theme Icon & Sidebar State Sync (Non-conflicting) -->
<script>
(function () {
    // 1. Theme Icon Synchronization (Dark / Light Mode)
    function syncThemeIcon() {
        var currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
        var isDark = currentTheme === 'dark';
        var modeIcons = document.querySelectorAll('.light-dark-mode i');
        modeIcons.forEach(function (icon) {
            if (isDark) {
                icon.className = 'bx bx-sun fs-22';
            } else {
                icon.className = 'bx bx-moon fs-22';
            }
        });
    }

    // Run on initial load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncThemeIcon);
    } else {
        syncThemeIcon();
    }

    // 2. Observe changes to data-bs-theme and data-sidebar-size on <html>
    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.attributeName === 'data-bs-theme') {
                syncThemeIcon();
                var theme = document.documentElement.getAttribute('data-bs-theme');
                if (theme) {
                    sessionStorage.setItem('data-bs-theme', theme);
                    localStorage.setItem('data-bs-theme', theme);
                }
            }
            if (mutation.attributeName === 'data-sidebar-size') {
                var size = document.documentElement.getAttribute('data-sidebar-size');
                if (size) {
                    sessionStorage.setItem('data-sidebar-size', size);
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

