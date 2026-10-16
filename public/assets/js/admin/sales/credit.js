/**
 * Credit (آجل) accounts and collections (resources/views/admin/sales/credit.blade.php), frontend F4.
 * Config (data-config on [data-page="sales.credit"]):
 *   { data: {totalOutstanding, customersCount, exceededLimitCount, collectedThisMonth, collectionsCount, totalCustomers, customers}, routes: {settle, payments, statement (__ID__)} }
 * Moved from the page's inline script; uses the shared Admin.esc.
 */
(function () {
    'use strict';

    Admin.page.register('sales.credit', function (root, config) {
        // Server data only (no browser mock store).
        const CREDIT_DATA = config.data;
        const CREDIT_ROUTES = config.routes;
        const TIER_LABELS = { standard: 'عادي', vip: 'مميز (VIP)', fleet: 'أساطيل وشركات' };

        let currentTargetCustomer = null;
        let paymentsPage = 1;

        const esc = Admin.esc;

        function fmt(amount) {
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(Number(amount) || 0) + ' ج.م';
        }

        /** Compact form for page-header KPI totals only (e.g. 26781945.84 -> "26.78 مليون ج.م");
         *  mirrors App\Support\MoneyHelper::formatCompactCurrency(). Table/row amounts keep using fmt(). */
        function fmtCompact(amount) {
            const n = Number(amount) || 0;
            if (n >= 1e6) return (n / 1e6).toFixed(2) + ' مليون ج.م';
            if (n >= 1e3) return (n / 1e3).toFixed(1) + ' ألف ج.م';
            return Math.round(n).toLocaleString('ar-EG') + ' ج.م';
        }

        /** Initializes (once) or live-updates a Bootstrap tooltip's text without losing its instance. */
        function initOrUpdateTooltip(el, title) {
            if (!el || typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
            el.setAttribute('title', title);
            el.setAttribute('data-bs-original-title', title);
            const existing = bootstrap.Tooltip.getInstance(el);
            if (existing) {
                existing.setContent({ '.tooltip-inner': title });
            } else {
                new bootstrap.Tooltip(el);
            }
        }

        function setKpiCompact(elId, amount) {
            const el = document.getElementById(elId);
            if (!el) return;
            el.textContent = fmtCompact(amount);
            initOrUpdateTooltip(el, fmt(amount));
        }

        function csrf() {
            return typeof window.getCsrfToken === 'function'
                ? window.getCsrfToken()
                : (document.querySelector('meta[name="csrf-token"]')?.content || '');
        }

        function statementUrl(id) {
            return CREDIT_ROUTES.statement.replace('__ID__', encodeURIComponent(id));
        }

        function findCreditCustomer(id) {
            return CREDIT_DATA.customers.find(c => c.id === Number(id)) || null;
        }

        // Initial render (was the DOMContentLoaded handler); called at the end of the module init.
        function init() {
            const el = document.getElementById('printCreditDate');
            if (el) el.textContent = new Date().toLocaleDateString('ar-EG', { dateStyle: 'full' });

            renderCreditDashboard();
            loadPayments(1);
        }

        function renderCreditDashboard() {
            setKpiCompact('kpiTotalOutstanding', CREDIT_DATA.totalOutstanding);
            document.getElementById('kpiCreditCustomersCount').textContent = `${CREDIT_DATA.customersCount} عميل`;
            setKpiCompact('kpiCollectedThisMonth', CREDIT_DATA.collectedThisMonth);

            const ratio = CREDIT_DATA.totalCustomers > 0
                ? Math.round((CREDIT_DATA.customersCount / CREDIT_DATA.totalCustomers) * 100)
                : 0;
            document.getElementById('kpiCreditRatio').textContent = `${ratio}%`;
            document.getElementById('kpiTotalCustomersText').textContent = `من إجمالي ${CREDIT_DATA.totalCustomers} عميل مسجل`;

            document.getElementById('badgeCreditCustCount').textContent = CREDIT_DATA.customersCount;
            document.getElementById('badgeCreditPayCount').textContent = CREDIT_DATA.collectionsCount;

            renderCreditTable();
        }

        function renderCreditTable() {
            const searchVal = (document.getElementById('searchCreditInput')?.value || '').trim().toLowerCase();
            const tierVal = document.getElementById('filterCustType')?.value || 'all';
            const tbody = document.getElementById('tbodyCreditCustomers');
            if (!tbody) return;

            const filtered = CREDIT_DATA.customers.filter(c => {
                if (tierVal !== 'all' && c.tier !== tierVal) return false;
                if (!searchVal) return true;
                return [c.name, c.phone, c.vehicle?.car, c.vehicle?.plate_number]
                    .some(v => v && String(v).toLowerCase().includes(searchVal));
            });

            document.getElementById('visibleCreditCount').textContent = filtered.length;

            if (filtered.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted fs-13"><i class="ri-checkbox-circle-line fs-24 text-success d-block mb-1"></i>لا توجد مديونيات آجلة مطابقة لبحثك! كل الحسابات خالصة.</td></tr>`;
                return;
            }

            tbody.innerHTML = filtered.map(c => {
                const exceeded = c.credit_limit > 0 && c.credit_balance > c.credit_limit;
                return `
                    <tr>
                        <td><div class="d-flex align-items-center"><div class="avatar-xs me-2"><span class="avatar-title rounded-circle bg-warning-subtle text-warning fw-bold fs-13">${esc(c.name.charAt(0))}</span></div><div><h6 class="fs-13 mb-0 fw-bold text-dark">${esc(c.name)}</h6><small class="text-muted font-monospace">#${c.id}</small></div></div></td>
                        <td><span class="font-monospace fw-semibold text-dark fs-12">${esc(c.phone)}</span></td>
                        <td>${c.vehicle ? `<span class="fw-bold fs-12 text-dark d-block">${esc(c.vehicle.car)}</span><span class="badge bg-light text-secondary border font-monospace fs-11">${esc(c.vehicle.plate_number)}</span>` : '<span class="text-muted fs-12">-</span>'}</td>
                        <td><span class="badge bg-primary-subtle text-primary fs-11">${esc(TIER_LABELS[c.tier] || c.tier)}</span></td>
                        <td><span class="font-monospace text-muted fs-12">${esc(fmt(c.purchases_total))}</span></td>
                        <td><span class="badge bg-danger text-white fs-13 font-monospace px-2 py-1 shadow-sm">${esc(fmt(c.credit_balance))}</span></td>
                        <td>${exceeded
                            ? '<span class="badge bg-danger-subtle text-danger fs-11 fw-bold"><i class="ri-error-warning-line me-1"></i>تجاوز سقف الائتمان</span>'
                            : '<span class="badge bg-warning-subtle text-warning fs-11 fw-bold"><i class="ri-time-line me-1"></i>آجل مستحق السداد</span>'}</td>
                        <td class="text-center no-print"><div class="d-inline-flex gap-1">
                            <button type="button" class="btn btn-sm btn-success fw-bold" data-action="credit.pay" data-id="${Number(c.id)}"><i class="ri-hand-coin-line me-1"></i> تحصيل دفعة</button>
                            <button type="button" class="btn btn-sm btn-soft-primary" data-action="credit.statement" data-id="${Number(c.id)}" title="كشف الفواتير"><i class="ri-file-text-line"></i> الفواتير</button>
                        </div></td>
                    </tr>
                `;
            }).join('');
        }

        async function loadPayments(page) {
            const tbody = document.getElementById('tbodyCreditPayments');
            const pager = document.getElementById('paymentsPager');
            if (!tbody) return;

            try {
                const url = new URL(CREDIT_ROUTES.payments, window.location.origin);
                url.searchParams.set('payments_page', page);
                const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) throw new Error();
                const data = await res.json();
                paymentsPage = data.meta.current_page;

                CREDIT_DATA.collectedThisMonth = data.collected_this_month;
                CREDIT_DATA.collectionsCount = data.meta.total;
                setKpiCompact('kpiCollectedThisMonth', data.collected_this_month);
                document.getElementById('badgeCreditPayCount').textContent = data.meta.total;

                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted fs-13">لا توجد سندات تحصيل مسجلة بعد.</td></tr>';
                } else {
                    tbody.innerHTML = data.data.map(p => `
                        <tr>
                            <td><span class="font-monospace fw-bold text-dark fs-12">${esc(p.receipt_number || '-')}</span></td>
                            <td>${p.customer ? `<a href="${esc(statementUrl(p.customer.id))}" target="_blank" class="fw-semibold">${esc(p.customer.name)}</a>` : '-'}</td>
                            <td><span class="font-monospace fs-12">${esc((p.created_at || '').substring(0, 16))}</span></td>
                            <td><span class="fw-bold text-success font-monospace fs-13">+ ${esc(fmt(p.amount))}</span></td>
                            <td><span class="fs-12">${esc(p.collected_by || '-')}</span></td>
                            <td><span class="text-muted fs-12">${esc(p.notes || '')}</span></td>
                        </tr>
                    `).join('');
                }

                if (pager) {
                    pager.innerHTML = data.meta.last_page > 1 ? `
                        <button type="button" class="btn btn-sm btn-light" ${paymentsPage <= 1 ? 'disabled' : ''} data-action="credit.payments-page" data-page-number="${paymentsPage - 1}">السابق</button>
                        <span class="align-self-center fs-12 text-muted">صفحة ${paymentsPage} من ${data.meta.last_page}</span>
                        <button type="button" class="btn btn-sm btn-light" ${paymentsPage >= data.meta.last_page ? 'disabled' : ''} data-action="credit.payments-page" data-page-number="${paymentsPage + 1}">التالي</button>
                    ` : '';
                }
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger fs-13">تعذر تحميل سجل التحصيلات من الخادم.</td></tr>';
            }
        }

        function openPaymentModal(custId) {
            const cust = findCreditCustomer(custId);
            if (!cust) return;
            currentTargetCustomer = cust;

            document.getElementById('payCustId').value = cust.id;
            document.getElementById('payCustName').textContent = cust.name;
            document.getElementById('payCustCarInfo').textContent = cust.vehicle
                ? `${cust.vehicle.car} — لوحة: ${cust.vehicle.plate_number} | هاتف: ${cust.phone}`
                : `هاتف: ${cust.phone}`;
            document.getElementById('payCustBalanceBadge').textContent = `الآجل المستحق: ${fmt(cust.credit_balance)}`;
            document.getElementById('payAmountInput').value = Math.min(cust.credit_balance, 500);
            document.getElementById('payAmountInput').max = cust.credit_balance;
            document.getElementById('payReceiptInput').value = '';
            document.getElementById('payNotesInput').value = '';

            new bootstrap.Modal(document.getElementById('recordPaymentModal')).show();
        }

        function setPayAmount(amount) {
            if (!currentTargetCustomer) return;
            document.getElementById('payAmountInput').value = Math.min(currentTargetCustomer.credit_balance, amount);
        }

        function setPayFullAmount() {
            if (!currentTargetCustomer) return;
            document.getElementById('payAmountInput').value = currentTargetCustomer.credit_balance;
        }

        async function submitCreditPayment(e) {
            e.preventDefault();
            const custId = Number(document.getElementById('payCustId').value);
            const cust = findCreditCustomer(custId);
            if (!cust) {
                Swal.fire('خطأ في التحصيل', 'العميل غير موجود. أعد تحميل الصفحة.', 'error');
                return;
            }

            const body = {
                customer_id: cust.id,
                amount: Number(document.getElementById('payAmountInput').value),
                payment_method: document.getElementById('payMethodSelect').value,
                notes: document.getElementById('payNotesInput').value.trim() || null,
            };
            const receipt = document.getElementById('payReceiptInput').value.trim();
            if (receipt) body.receipt_number = receipt;

            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جاري التسجيل...';
            }

            try {
                const res = await fetch(CREDIT_ROUTES.settle, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify(body)
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data.errors ? Object.values(data.errors).flat().join(' - ') : (data.message || 'فشل تسجيل التحصيل من الخادم.'));
                }

                // Reflect the server result (authoritative new balance).
                const newBalance = Number(data.new_balance || 0);
                CREDIT_DATA.totalOutstanding = Math.max(0, CREDIT_DATA.totalOutstanding - (cust.credit_balance - newBalance));
                cust.credit_balance = newBalance;
                if (newBalance <= 0) {
                    CREDIT_DATA.customers = CREDIT_DATA.customers.filter(c => c.id !== cust.id);
                    CREDIT_DATA.customersCount = CREDIT_DATA.customers.length;
                }

                bootstrap.Modal.getInstance(document.getElementById('recordPaymentModal'))?.hide();
                renderCreditDashboard();
                loadPayments(1);

                Swal.fire({
                    icon: 'success',
                    title: 'تم تسجيل سند التحصيل بنجاح!',
                    html: `
                        <div class="p-2 mb-2 bg-light rounded text-center">
                            <strong class="text-success fs-15">${esc(data.message || '')}</strong>
                            ${data.receipt_number ? `<div class="text-muted fs-12 mt-1">رقم سند القبض: <span class="font-monospace fw-bold">${esc(data.receipt_number)}</span></div>` : ''}
                            <div class="text-dark fs-13 mt-1">الرصيد المتبقي الجديد: <strong class="font-monospace text-danger">${esc(fmt(newBalance))}</strong></div>
                        </div>
                    `,
                    confirmButtonText: 'حسناً',
                    confirmButtonColor: '#198754'
                });
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'خطأ في التحصيل', text: err.message || 'حدث خطأ أثناء السداد', confirmButtonText: 'موافق' });
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }
            }
        }

        async function openStatementModal(custId) {
            const cust = findCreditCustomer(custId);
            if (!cust) return;

            const modalEl = document.getElementById('customerStatementModal');
            const modalBody = document.getElementById('statementModalBody');
            document.getElementById('statementModalTitle').textContent = `كشف حساب آجل: ${cust.name}`;
            modalBody.innerHTML = '<div class="text-center py-4"><span class="spinner-border text-primary"></span><p class="text-muted fs-13 mt-2">جاري تحميل كشف الحساب من الخادم...</p></div>';
            new bootstrap.Modal(modalEl).show();

            const fullUrl = statementUrl(cust.id);
            try {
                const res = await fetch(fullUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) throw new Error();
                const data = await res.json();
                const invoices = Array.isArray(data.invoices) ? data.invoices : (data.invoices?.data || []);
                const ledgers = Array.isArray(data.credit_ledgers) ? data.credit_ledgers : (data.credit_ledgers?.data || []);
                const vehicleText = cust.vehicle ? `${cust.vehicle.car} - لوحة: ${cust.vehicle.plate_number} | ` : '';

                modalBody.innerHTML = `
                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div><h5 class="fw-bold mb-1 text-dark">${esc(cust.name)}</h5><p class="text-muted mb-0 fs-12">${esc(vehicleText)}هاتف: ${esc(cust.phone)}</p></div>
                            <div class="text-end"><span class="fs-12 text-muted d-block">الرصيد المتبقي على الآجل:</span><h4 class="text-danger fw-extrabold mb-0 font-monospace">${esc(fmt(cust.credit_balance))}</h4>
                                <a href="${esc(fullUrl)}" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i class="ri-file-list-3-line me-1"></i> كشف الحساب التفصيلي المعتمد</a></div>
                        </div>
                    </div>
                    <h6 class="fw-bold fs-13 text-dark mb-2"><i class="ri-bill-line me-1 text-primary"></i> فواتير المبيعات المفتوحة للعميل:</h6>
                    <div class="table-responsive mb-3"><table class="table table-sm table-bordered fs-12 mb-0"><thead class="table-light"><tr><th>رقم الفاتورة</th><th>التاريخ</th><th>إجمالي الفاتورة</th><th>المدفوع</th><th>المتبقي بالآجل</th><th>الحالة</th></tr></thead><tbody>
                        ${invoices.length ? invoices.map(inv => `
                            <tr><td class="font-monospace fw-bold">${esc(inv.invoice_number)}</td><td>${esc((inv.created_at || '').substring(0, 10))}</td><td class="font-monospace">${esc(fmt(inv.final_amount))}</td><td class="font-monospace text-success">${esc(fmt(inv.paid_amount))}</td><td class="font-monospace text-danger fw-bold">${esc(fmt(inv.remaining_amount))}</td><td><span class="badge ${Number(inv.remaining_amount) > 0 ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success'}">${esc(inv.status)}</span></td></tr>
                        `).join('') : '<tr><td colspan="6" class="text-center py-2 text-muted">لا توجد فواتير آجل مفتوحة</td></tr>'}
                    </tbody></table></div>
                    <h6 class="fw-bold fs-13 text-dark mb-2"><i class="ri-history-line me-1 text-success"></i> حركات حساب الآجل:</h6>
                    <div class="table-responsive"><table class="table table-sm table-bordered fs-12 mb-0"><thead class="table-light"><tr><th>رقم الإيصال</th><th>التاريخ</th><th>المبلغ</th><th>نوع الحركة</th></tr></thead><tbody>
                        ${ledgers.length ? ledgers.map(l => `
                            <tr><td class="font-monospace">${esc(l.receipt_number || '-')}</td><td>${esc((l.created_at || '').substring(0, 10))}</td><td class="font-monospace fw-bold">${esc(fmt(l.amount))}</td><td>${esc(l.entry_type)}</td></tr>
                        `).join('') : '<tr><td colspan="4" class="text-center py-2 text-muted">لا توجد حركات سابقة</td></tr>'}
                    </tbody></table></div>
                `;
            } catch (e) {
                modalBody.innerHTML = `<div class="text-center py-4 text-muted">تعذر تحميل كشف الحساب. <a href="${esc(fullUrl)}" target="_blank">فتح الكشف الكامل</a></div>`;
            }
        }

        function exportCreditCSV() {
            const headers = ['كود العميل', 'اسم العميل', 'رقم الهاتف', 'السيارة', 'رقم اللوحة', 'تصنيف العميل', 'إجمالي المشتريات (ج.م)', 'رصيد الآجل المتبقي (ج.م)'];
            const rows = CREDIT_DATA.customers.map(c => [
                c.id, c.name, c.phone, c.vehicle?.car || '', c.vehicle?.plate_number || '',
                TIER_LABELS[c.tier] || c.tier, c.purchases_total, c.credit_balance
            ]);
            // Quote every cell; prefix formula-leading values so spreadsheets do not execute them.
            const cell = v => {
                let s = String(v ?? '');
                if (/^[=+\-@]/.test(s)) s = "'" + s;
                return '"' + s.replace(/"/g, '""') + '"';
            };
            const csv = '﻿' + [headers, ...rows].map(r => r.map(cell).join(',')).join('\r\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `كشف_حسابات_الآجل_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(link.href);
        }

        Admin.actions.registerAll({
            'credit.export': () => exportCreditCSV(),
            'credit.render': () => renderCreditTable(),
            'credit.submit-payment': (form, event) => submitCreditPayment(event),
            'credit.pay-amount': (el) => setPayAmount(Number(el.dataset.amount)),
            'credit.pay-full': () => setPayFullAmount(),
            'credit.pay': (el) => openPaymentModal(Number(el.dataset.id)),
            'credit.statement': (el) => openStatementModal(Number(el.dataset.id)),
            'credit.payments-page': (el) => loadPayments(Number(el.dataset.pageNumber)),
        });

        init();
    });
})();
