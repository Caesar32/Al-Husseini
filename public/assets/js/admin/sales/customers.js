/**
 * Customers directory (resources/views/admin/sales/customers.blade.php), frontend F4.
 * Config (data-config on [data-page="sales.customers"]): { urls: {store, update, show, vehicleStore}, tierLabels }
 * Action: customers.new. Moved from the page's inline script.
 */
(function () {
    'use strict';

    Admin.page.register('sales.customers', function (root, config) {
        let openCustomerModal = () => {};
        Admin.actions.register('customers.new', () => openCustomerModal(null));

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const urls = config.urls;
        const tierLabels = config.tierLabels;

        const escapeHtml = Admin.esc;

        function money(value) {
            return Number(value || 0).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ج.م';
        }

        function formPayload(form) {
            const payload = {};
            new FormData(form).forEach(function (value, key) {
                const match = key.match(/^(\w+)\[(\w+)\]$/);
                if (match) {
                    payload[match[1]] = payload[match[1]] || {};
                    payload[match[1]][match[2]] = value === '' ? null : value;
                } else {
                    payload[key] = value === '' ? null : value;
                }
            });
            return payload;
        }

        async function send(url, method, payload, errorsEl) {
            errorsEl.classList.add('d-none');
            try {
                const res = await fetch(url, {
                    method: method,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload),
                });
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok) {
                    const messages = data.errors ? Object.values(data.errors).flat() : [data.message || 'تعذر حفظ البيانات.'];
                    errorsEl.textContent = messages.join(' | ');
                    errorsEl.classList.remove('d-none');
                    return false;
                }
                return true;
            } catch (err) {
                errorsEl.textContent = 'تعذر الاتصال بالخادم. حاول مرة أخرى.';
                errorsEl.classList.remove('d-none');
                return false;
            }
        }

        // ─── Customer create / edit ───
        const customerForm = document.getElementById('customerForm');
        if (customerForm) {
            const errorsEl = document.getElementById('customerFormErrors');
            const modalEl = document.getElementById('customerModal');

            openCustomerModal = function (customer) {
                customerForm.reset();
                errorsEl.classList.add('d-none');
                document.getElementById('custFormId').value = customer ? customer.id : '';
                document.getElementById('customerModalTitle').textContent = customer ? 'تعديل بيانات العميل' : 'إضافة عميل جديد';
                document.getElementById('custActiveGroup').classList.toggle('d-none', !customer);
                document.getElementById('custVehicleGroup').classList.toggle('d-none', !!customer);
                if (customer) {
                    Object.keys(customer).forEach(function (key) {
                        const field = customerForm.elements[key];
                        if (!field) return;
                        let value = customer[key];
                        if (typeof value === 'boolean') value = value ? '1' : '0';
                        field.value = value === null || value === undefined ? '' : value;
                    });
                }
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            };

            document.querySelectorAll('.js-edit-customer').forEach(function (btn) {
                btn.addEventListener('click', function () { openCustomerModal(JSON.parse(btn.dataset.customer)); });
            });

            customerForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const id = document.getElementById('custFormId').value;
                const payload = formPayload(customerForm);
                if (id) {
                    delete payload.vehicle;
                } else {
                    delete payload.is_active;
                    if (payload.vehicle && !payload.vehicle.plate_number) delete payload.vehicle;
                }
                if ('credit_limit' in payload && payload.credit_limit === null) delete payload.credit_limit;

                const submitBtn = document.getElementById('customerFormSubmit');
                submitBtn.disabled = true;
                const ok = await send(id ? urls.update.replace('__ID__', encodeURIComponent(id)) : urls.store, id ? 'PUT' : 'POST', payload, errorsEl);
                submitBtn.disabled = false;
                if (ok) window.location.reload();
            });
        }

        // ─── Add vehicle ───
        const vehicleForm = document.getElementById('vehicleForm');
        if (vehicleForm) {
            const errorsEl = document.getElementById('vehicleFormErrors');
            document.querySelectorAll('.js-add-vehicle').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    vehicleForm.reset();
                    errorsEl.classList.add('d-none');
                    document.getElementById('vehicleCustomerId').value = btn.dataset.id;
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('vehicleModal')).show();
                });
            });
            vehicleForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const id = document.getElementById('vehicleCustomerId').value;
                const ok = await send(urls.vehicleStore.replace('__ID__', encodeURIComponent(id)), 'POST', formPayload(vehicleForm), errorsEl);
                if (ok) window.location.reload();
            });
        }

        // ─── Customer history (server data) ───
        document.querySelectorAll('.js-customer-history').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const body = document.getElementById('customerHistoryModalBody');
                body.innerHTML = '<div class="text-center py-4 text-muted">جاري التحميل...</div>';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('customerHistoryModal')).show();

                try {
                    const res = await fetch(urls.show.replace('__ID__', encodeURIComponent(btn.dataset.id)), { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const data = await res.json();
                    const c = data.customer;
                    const vehicles = (c.vehicles || []).map(function (v) {
                        return escapeHtml(v.car_brand + ' ' + v.car_model) + ' - لوحة: <strong>' + escapeHtml(v.plate_number) + '</strong>';
                    }).join('<br>') || '-';

                    const invoiceRows = (data.invoices || []).map(function (inv) {
                        const items = (inv.items || []).map(function (it) {
                            const serial = it.battery_serial_number ? ' <span class="font-monospace text-primary">[' + escapeHtml(it.battery_serial_number) + ']</span>' : '';
                            return escapeHtml(it.product ? it.product.name : '-') + ' × ' + escapeHtml(it.quantity) + serial;
                        }).join('<br>');
                        return '<tr><td class="font-monospace fw-bold">' + escapeHtml(inv.invoice_number) + '</td>'
                            + '<td>' + escapeHtml(String(inv.created_at || '').slice(0, 10)) + '</td>'
                            + '<td>' + items + '</td>'
                            + '<td class="font-monospace">' + money(inv.final_amount) + '</td>'
                            + '<td class="font-monospace">' + money(inv.remaining_amount) + '</td>'
                            + '<td><span class="badge bg-light text-dark border">' + escapeHtml(inv.status) + '</span></td></tr>';
                    }).join('') || '<tr><td colspan="6" class="text-center py-3 text-muted">لا توجد فواتير مسجلة</td></tr>';

                    const warrantyRows = (data.warranties || []).map(function (w) {
                        return '<tr><td class="font-monospace text-primary">' + escapeHtml(w.serial_number) + '</td>'
                            + '<td>' + escapeHtml(w.invoice_item && w.invoice_item.product ? w.invoice_item.product.name : '-') + '</td>'
                            + '<td class="font-monospace">' + escapeHtml(String(w.end_date || '').slice(0, 10)) + '</td>'
                            + '<td><span class="badge bg-light text-dark border">' + escapeHtml(w.status) + '</span></td></tr>';
                    }).join('') || '<tr><td colspan="4" class="text-center py-3 text-muted">لا توجد ضمانات مسجلة</td></tr>';

                    body.innerHTML =
                        '<div class="p-3 bg-light rounded border mb-3"><div class="d-flex justify-content-between align-items-center">'
                        + '<div><h5 class="fw-bold mb-1 text-dark">' + escapeHtml(c.name) + '</h5>'
                        + '<p class="text-muted mb-0 fs-13">هاتف: ' + escapeHtml(c.phone) + ' | التصنيف: ' + escapeHtml(tierLabels[c.tier] || c.tier) + '</p>'
                        + '<p class="text-muted mb-0 fs-12"><i class="ri-car-line me-1"></i>' + vehicles + '</p></div>'
                        + '<div class="text-end"><span class="fs-12 text-muted d-block">رصيد الآجل المتبقي:</span>'
                        + '<span class="badge ' + (Number(c.current_credit_balance) > 0 ? 'bg-danger' : 'bg-success') + ' fs-14 font-monospace">' + money(c.current_credit_balance) + '</span>'
                        + '<span class="fs-11 text-muted d-block mt-1">سقف الائتمان: ' + money(c.credit_limit) + '</span></div>'
                        + '</div></div>'
                        + '<h6 class="fw-bold fs-14 text-dark mb-2">آخر الفواتير</h6>'
                        + '<div class="table-responsive mb-3"><table class="table table-bordered table-sm fs-12 mb-0"><thead class="table-light"><tr><th>الفاتورة</th><th>التاريخ</th><th>الأصناف</th><th>الصافي</th><th>المتبقي</th><th>الحالة</th></tr></thead><tbody>' + invoiceRows + '</tbody></table></div>'
                        + '<h6 class="fw-bold fs-14 text-dark mb-2">شهادات الضمان</h6>'
                        + '<div class="table-responsive"><table class="table table-bordered table-sm fs-12 mb-0"><thead class="table-light"><tr><th>السيريال</th><th>الصنف</th><th>انتهاء الضمان</th><th>الحالة</th></tr></thead><tbody>' + warrantyRows + '</tbody></table></div>';
                } catch (err) {
                    body.innerHTML = '<div class="text-center py-4 text-danger">تعذر تحميل سجل العميل.</div>';
                }
            });
        });
    });
})();
