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
<script src="{{ asset('assets/js/hr-store.js') }}"></script>
<script src="{{ asset('assets/js/sales-store.js') }}"></script>

<!-- App js -->
<script src="{{ asset('assets/js/app.js') }}"></script>

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

// Sync Topbar Notifications with AlHusseiniHR
document.addEventListener('DOMContentLoaded', function() {
    function renderTopbarNotifications() {
        if (!window.AlHusseiniHR) return;
        const notifs = window.AlHusseiniHR.getNotifications();
        const unreadCount = notifs.filter(n => !n.read).length;

        const badge = document.getElementById('topbar-notification-badge');
        const countHeader = document.getElementById('topbar-notification-count');
        const listContainer = document.getElementById('topbar-notification-list');

        if (badge) {
            badge.textContent = unreadCount;
            badge.style.display = unreadCount > 0 ? 'inline-block' : 'none';
        }
        if (countHeader) {
            countHeader.textContent = `${unreadCount} جديد`;
        }

        if (listContainer) {
            if (notifs.length === 0) {
                listContainer.innerHTML = '<div class="text-center py-4 text-muted fs-13"><i class="ri-notification-off-line fs-24 d-block mb-1"></i>لا توجد تنبيهات حالياً</div>';
                return;
            }

            let html = '';
            notifs.slice(0, 10).forEach(n => {
                const isLate = n.type === 'lateness';
                const iconBg = isLate ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning';
                const iconClass = isLate ? 'ri-alarm-warning-line' : 'ri-money-dollar-circle-line';
                const unreadDot = !n.read ? '<span class="badge badge-dot bg-danger me-1"></span>' : '';

                html += `
                    <div class="text-reset notification-item d-block dropdown-item position-relative ${!n.read ? 'active bg-light-subtle' : ''}">
                        <div class="d-flex align-items-start">
                            <div class="avatar-xs me-3 flex-shrink-0">
                                <span class="avatar-title ${iconBg} rounded-circle fs-16">
                                    <i class="${iconClass}"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <h6 class="mt-0 mb-1 fs-13 fw-bold">${unreadDot}${n.title}</h6>
                                <p class="mb-1 fs-12 text-muted text-truncate-2">${n.message}</p>
                                <p class="mb-0 fs-11 fw-medium text-muted">
                                    <span><i class="mdi mdi-clock-outline"></i> ${n.time}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                `;
            });
            listContainer.innerHTML = html;
        }
    }

    renderTopbarNotifications();
    window.addEventListener('alhusseini-hr-updated', renderTopbarNotifications);
});
</script>
