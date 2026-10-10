/**
 * Al-Husseini Admin Real-Time Notifications Handler
 */
(function () {
    const listEl = document.getElementById('topbar-notification-list');
    const badgeEl = document.getElementById('topbar-notification-badge');
    const countEl = document.getElementById('topbar-notification-count');

    if (!listEl) return;

    window.refreshAdminNotifications = async function () {
        try {
            const res = await fetch('/admin/hr/notifications', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!res.ok) return;
            const data = await res.json();
            const notifications = data.notifications || [];
            const unreadCount = data.unread_count || 0;

            // تحديث شارة العداد
            if (badgeEl) {
                badgeEl.textContent = unreadCount;
                badgeEl.style.display = unreadCount > 0 ? 'inline-block' : 'none';
            }
            if (countEl) {
                countEl.textContent = `${unreadCount} جديد`;
            }

            // رسم القائمة
            if (notifications.length === 0) {
                listEl.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="ri-notification-off-line fs-24 d-block mb-1"></i>
                        <span>لا توجد تنبيهات جديدة</span>
                    </div>
                `;
                return;
            }

            let html = '';
            notifications.forEach(item => {
                const isUnread = item.read_at === null;
                const d = item.data || {};
                const icon = d.icon || 'ri-notification-line';
                const color = d.color || 'primary';
                const bgClass = isUnread ? 'bg-light-subtle fw-semibold' : '';

                html += `
                    <div class="text-reset notification-item d-block dropdown-item position-relative ${bgClass} py-2 border-bottom" style="cursor: pointer;" onclick="markSingleAsRead('${item.id}', '${d.url || '#'}')">
                        <div class="d-flex align-items-center">
                            <div class="avatar-xs me-3">
                                <span class="avatar-title bg-${color}-subtle text-${color} rounded-circle fs-16">
                                    <i class="${icon}"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <h6 class="mt-0 mb-1 fs-13 ${isUnread ? 'fw-bold' : ''}">${d.title || 'إشعار إداري'}</h6>
                                <div class="fs-12 text-muted text-truncate">
                                    <p class="mb-0 text-wrap">${d.message || ''}</p>
                                </div>
                                <small class="text-muted fs-11">${d.time || ''} - ${d.work_date || ''}</small>
                            </div>
                            ${isUnread ? '<span class="badge bg-danger rounded-pill fs-9 ms-2">جديد</span>' : ''}
                        </div>
                    </div>
                `;
            });

            listEl.innerHTML = html;
        } catch (err) {
            console.error('Failed to load notifications:', err);
        }
    };

    window.markSingleAsRead = async function (id, redirectUrl) {
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            await fetch(`/admin/hr/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (redirectUrl && redirectUrl !== '#') {
                window.location.href = redirectUrl;
            } else {
                window.refreshAdminNotifications();
            }
        } catch (err) {
            console.error(err);
        }
    };

    window.markAllNotificationsRead = async function () {
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            await fetch('/admin/hr/notifications/read-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            window.refreshAdminNotifications();
        } catch (err) {
            console.error(err);
        }
    };

    // تحميل التنبيهات عند تشغيل الصفحة
    document.addEventListener('DOMContentLoaded', () => {
        // The bell is only rendered for users with notifications.view; everyone else must not
        // poll an endpoint that answers 403.
        if (!document.getElementById('notificationDropdown')) return;

        window.refreshAdminNotifications();
        // فحص دوري كل 30 ثانية
        setInterval(window.refreshAdminNotifications, 30000);
    });
})();
