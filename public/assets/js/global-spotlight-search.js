/**
 * Al-Husseini Management System - Global Spotlight Search Engine
 * بحث فوري شامل عالي الأداء مع تنقل بالأسهم واختصارات لوحة المفاتيح
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const desktopInput = document.getElementById('search-options');
        const desktopDropdown = document.getElementById('search-dropdown');
        const desktopCloseBtn = document.getElementById('search-close-options');

        const mobileInput = document.getElementById('search-options-mobile');
        const mobileDropdown = document.getElementById('search-dropdown-mobile');

        if (!desktopInput && !mobileInput) return;

        let debounceTimer = null;
        let selectedIndex = -1;
        let currentItems = [];

        // 1. اختصار لوحة المفاتيح العالمي (Ctrl + K / Cmd + K)
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                if (desktopInput && window.innerWidth >= 768) {
                    desktopInput.focus();
                    desktopInput.select();
                } else if (mobileInput) {
                    const mobileBtn = document.getElementById('page-header-search-dropdown');
                    if (mobileBtn) {
                        mobileBtn.click();
                        setTimeout(() => mobileInput.focus(), 200);
                    }
                }
            }

            if (e.key === 'Escape') {
                hideDropdown(desktopDropdown, desktopCloseBtn);
            }
        });

        // 2. إعداد البحث المكتبي (Desktop Search)
        if (desktopInput && desktopDropdown) {
            desktopInput.addEventListener('input', function () {
                const val = this.value.trim();
                if (val.length > 0) {
                    desktopCloseBtn?.classList.remove('d-none');
                } else {
                    desktopCloseBtn?.classList.add('d-none');
                    hideDropdown(desktopDropdown, desktopCloseBtn);
                    return;
                }

                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    executeSearch(val, desktopDropdown);
                }, 200);
            });

            desktopInput.addEventListener('focus', function () {
                if (this.value.trim().length >= 2) {
                    executeSearch(this.value.trim(), desktopDropdown);
                }
            });

            // التنقل بالأسهم واختيار النتيجة
            desktopInput.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    navigateItems(1, desktopDropdown);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    navigateItems(-1, desktopDropdown);
                } else if (e.key === 'Enter') {
                    if (selectedIndex >= 0 && currentItems[selectedIndex]) {
                        e.preventDefault();
                        const url = currentItems[selectedIndex].getAttribute('href');
                        if (url && url !== '#') {
                            window.location.href = url;
                        }
                    }
                }
            });

            desktopCloseBtn?.addEventListener('click', function () {
                desktopInput.value = '';
                desktopInput.focus();
                desktopCloseBtn.classList.add('d-none');
                hideDropdown(desktopDropdown, desktopCloseBtn);
            });
        }

        // 3. إعداد البحث عبر الموبايل (Mobile Search)
        if (mobileInput && mobileDropdown) {
            mobileInput.addEventListener('input', function () {
                const val = this.value.trim();
                if (val.length < 2) {
                    mobileDropdown.innerHTML = '';
                    return;
                }

                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    executeSearch(val, mobileDropdown);
                }, 250);
            });
        }

        // إغلاق القائمة عند النقر خارجها
        document.addEventListener('click', function (e) {
            if (desktopDropdown && !desktopDropdown.contains(e.target) && e.target !== desktopInput) {
                hideDropdown(desktopDropdown, desktopCloseBtn);
            }
        });

        // 4. تنفيذ استعلام البحث عبر الـ Backend API
        function executeSearch(query, dropdownEl) {
            if (!query || query.length < 2) {
                hideDropdown(dropdownEl);
                return;
            }

            renderLoading(dropdownEl);
            showDropdown(dropdownEl);

            fetch(`/admin/global-search?q=${encodeURIComponent(query)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error('Search request failed');
                return res.json();
            })
            .then(res => {
                const data = res.data;
                selectedIndex = -1;
                renderResults(data, dropdownEl);
            })
            .catch(err => {
                console.error('Search error:', err);
                renderError(dropdownEl);
            });
        }

        function showDropdown(dropdownEl) {
            dropdownEl.classList.add('show');
            dropdownEl.style.display = 'block';
        }

        function hideDropdown(dropdownEl, closeBtnEl = null) {
            if (!dropdownEl) return;
            dropdownEl.classList.remove('show');
            dropdownEl.style.display = 'none';
            selectedIndex = -1;
        }

        function renderLoading(dropdownEl) {
            dropdownEl.innerHTML = `
                <div class="p-4 text-center">
                    <div class="spinner-border text-primary spinner-border-sm mb-2" role="status"></div>
                    <p class="text-muted mb-0 fs-13">جارِ البحث اللحظي في فهارس النظام...</p>
                </div>
            `;
        }

        function renderError(dropdownEl) {
            dropdownEl.innerHTML = `
                <div class="p-3 text-center text-danger fs-13">
                    <i class="ri-error-warning-line me-1"></i> تعذر إتمام البحث، يرجى المحاولة لاحقاً.
                </div>
            `;
        }

        function renderResults(data, dropdownEl) {
            if (!data || data.total_count === 0) {
                dropdownEl.innerHTML = `
                    <div class="p-4 text-center">
                        <div class="avatar-sm mx-auto mb-3">
                            <div class="avatar-title bg-light text-muted rounded-circle fs-20">
                                <i class="ri-search-line"></i>
                            </div>
                        </div>
                        <h6 class="fs-14 text-dark mb-1">لا توجد نتائج مطابقة</h6>
                        <p class="text-muted fs-12 mb-0">لم يتم العثور على أي نتائج لكلمة "<strong>${escapeHtml(data.query)}</strong>"</p>
                    </div>
                `;
                currentItems = [];
                return;
            }

            let html = `
                <div class="dropdown-header text-muted px-3 py-2 border-bottom bg-light bg-opacity-50 d-flex justify-content-between align-items-center">
                    <span class="fs-12 fw-semibold">نتائج البحث (${data.total_count})</span>
                    <span class="badge bg-primary-subtle text-primary fs-11">فهارس سريعة</span>
                </div>
                <div class="search-results-list" style="max-height: 380px; overflow-y: auto;">
            `;

            for (const [key, section] of Object.entries(data.sections)) {
                html += `
                    <div class="search-section-header px-3 py-2 bg-light-subtle text-muted fs-11 fw-bold text-uppercase border-bottom">
                        <i class="${section.icon} me-1 align-middle text-primary"></i> ${section.title} (${section.count})
                    </div>
                `;

                section.items.forEach(item => {
                    html += `
                        <a href="${item.url}" class="dropdown-item px-3 py-2 border-bottom border-light search-result-item d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center text-truncate pe-2">
                                <div class="flex-shrink-0 me-2">
                                    <div class="avatar-xs">
                                        <div class="avatar-title rounded bg-light text-primary fs-16">
                                            <i class="${item.icon}"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex-grow-1 text-truncate">
                                    <h6 class="fs-13 mb-0 text-dark text-truncate">${escapeHtml(item.title)}</h6>
                                    <p class="fs-11 text-muted mb-0 text-truncate">${escapeHtml(item.subtitle)}</p>
                                </div>
                            </div>
                            <div class="flex-shrink-0 text-end">
                                <span class="${item.badge_class} fs-11">${item.badge}</span>
                            </div>
                        </a>
                    `;
                });
            }

            html += `
                </div>
                <div class="dropdown-footer px-3 py-2 bg-light text-center border-top fs-11 text-muted">
                    <span>استخدم <kbd class="px-1 text-dark bg-white border rounded">↑</kbd> <kbd class="px-1 text-dark bg-white border rounded">↓</kbd> للتنقل، و <kbd class="px-1 text-dark bg-white border rounded">Enter</kbd> للاختيار</span>
                </div>
            `;

            dropdownEl.innerHTML = html;
            currentItems = Array.from(dropdownEl.querySelectorAll('.search-result-item'));
        }

        function navigateItems(direction, dropdownEl) {
            if (!currentItems || currentItems.length === 0) return;

            if (selectedIndex >= 0 && currentItems[selectedIndex]) {
                currentItems[selectedIndex].classList.remove('active');
            }

            selectedIndex += direction;

            if (selectedIndex < 0) {
                selectedIndex = currentItems.length - 1;
            } else if (selectedIndex >= currentItems.length) {
                selectedIndex = 0;
            }

            const activeItem = currentItems[selectedIndex];
            if (activeItem) {
                activeItem.classList.add('active');
                activeItem.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    });
})();
