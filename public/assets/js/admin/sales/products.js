/**
 * Product catalogue (resources/views/admin/sales/products.blade.php), frontend F4.
 * Config (data-config on [data-page="sales.products"]): { storeUrl, updateUrl (with __ID__) }
 * Actions: products.new, products.edit (data-product = product JSON). Moved from the page's inline script.
 */
(function () {
    'use strict';

    Admin.page.register('sales.products', function (root, config) {
        const form = document.getElementById('productForm');
        if (!form) return;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const modalEl = document.getElementById('productModal');
        const errorsEl = document.getElementById('productFormErrors');

        function openProductModal(product) {
            form.reset();
            errorsEl.classList.add('d-none');
            errorsEl.textContent = '';
            document.getElementById('prodFormId').value = product ? product.id : '';
            document.getElementById('productModalTitle').textContent = product ? 'تعديل بيانات الصنف / الخدمة' : 'إضافة صنف أو خدمة جديدة';
            document.getElementById('prodCostGroup').classList.toggle('d-none', !!product);
            document.getElementById('prodActiveGroup').classList.toggle('d-none', !product);

            if (product) {
                Object.keys(product).forEach(function (key) {
                    const field = form.elements[key];
                    if (!field) return;
                    let value = product[key];
                    if (typeof value === 'boolean') value = value ? '1' : '0';
                    field.value = value === null || value === undefined ? '' : value;
                });
            }

            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        Admin.actions.registerAll({
            'products.new': () => openProductModal(null),
            'products.edit': (btn) => openProductModal(JSON.parse(btn.dataset.product)),
        });

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const id = document.getElementById('prodFormId').value;
            const payload = {};
            new FormData(form).forEach(function (value, key) {
                payload[key] = value === '' ? null : value;
            });
            if (id) {
                delete payload.cost_price;
            } else {
                delete payload.is_active;
            }

            const submitBtn = document.getElementById('productFormSubmit');
            submitBtn.disabled = true;
            try {
                const res = await fetch(id ? config.updateUrl.replace('__ID__', encodeURIComponent(id)) : config.storeUrl, {
                    method: id ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload),
                });
                const data = await res.json().catch(function () { return {}; });

                if (!res.ok) {
                    const messages = data.errors ? Object.values(data.errors).flat() : [data.message || 'تعذر حفظ الصنف.'];
                    errorsEl.textContent = messages.join(' | ');
                    errorsEl.classList.remove('d-none');
                    return;
                }

                window.location.reload();
            } catch (err) {
                errorsEl.textContent = 'تعذر الاتصال بالخادم. حاول مرة أخرى.';
                errorsEl.classList.remove('d-none');
            } finally {
                submitBtn.disabled = false;
            }
        });
    });
})();
