/**
 * Warranties (resources/views/admin/warranties/{index,verify}.blade.php), frontend F4.
 *
 * Page "warranties.index" (data-config: { verifyUrl, settleUrl (with __ID__) }):
 *   live serial verification box, instant-claim modal, supplier settlement form URL.
 * Actions (both pages): warranties.open-claim, warranties.prefill-claim, warranties.sample, warranties.decision.
 * Moved from the pages' inline scripts; results are escaped (B3).
 */
(function () {
    'use strict';

    const esc = Admin.esc;

    function openClaimModal(serial) {
        document.getElementById('modalDefectiveSerial').value = serial;
        new bootstrap.Modal(document.getElementById('newClaimModal')).show();
    }

    Admin.actions.registerAll({
        // Index: "open instant replacement ticket" button in the verification result.
        'warranties.open-claim': (el) => openClaimModal(el.dataset.serial),
        // Verify page: the claim button opens the modal via data-bs-toggle; this fills the serial.
        'warranties.prefill-claim': (el) => {
            const input = document.getElementById('modalDefectiveSerial');
            if (input) input.value = el.dataset.serial;
        },
        // Verify page: sample serial chips submit the verify form.
        'warranties.sample': (el) => {
            document.getElementById('serialInput').value = el.dataset.serial;
            document.getElementById('verifyForm').submit();
        },
        // Verify page: replacement fields only for a "replaced" decision.
        'warranties.decision': (select) => {
            document.querySelectorAll('.replacement-fields').forEach((el) => el.classList.toggle('d-none', select.value !== 'replaced'));
        },
    });

    Admin.page.register('warranties.index', function (root, config) {
        const searchInput = document.getElementById('serialSearchInput');
        const searchBtn = document.getElementById('serialSearchBtn');
        const resultBox = document.getElementById('verificationResultBox');

        function verifySerial() {
            const serial = searchInput.value.trim();
            if (!serial) return;

            resultBox.classList.remove('d-none');
            resultBox.innerHTML = '<div class="text-muted"><i class="ri-loader-4-line spin"></i> جاري الفحص في قاعدة البيانات...</div>';

            fetch(config.verifyUrl + '?serial_number=' + encodeURIComponent(serial))
                .then(res => res.json())
                .then(data => {
                    if (!data.exists) {
                        resultBox.innerHTML = `
                            <div class="alert alert-danger mb-0">
                                <strong><i class="ri-close-circle-line me-1"></i> السيريال غير مسجل:</strong> ${esc(data.message)}
                            </div>
                        `;
                    } else {
                        const statusClass = data.is_valid ? 'alert-success' : 'alert-warning';
                        resultBox.innerHTML = `
                            <div class="alert ${statusClass} mb-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="fw-bold mb-1"><i class="ri-shield-check-line me-1"></i> ${esc(data.message)}</h6>
                                        <div>العميل: <strong>${esc(data.warranty.customer ? data.warranty.customer.name : 'عميل نقدي')}</strong> (${esc(data.warranty.customer ? data.warranty.customer.phone : '')})</div>
                                        <div>تاريخ الانتهاء: <strong>${esc(data.warranty.end_date)}</strong> (المتبقي: ${esc(data.days_remaining)} يوم)</div>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-danger btn-sm" data-action="warranties.open-claim" data-serial="${esc(serial)}">
                                            <i class="ri-alarm-warning-line me-1"></i> فتح تذكرة استبدال فوري
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                })
                .catch(() => {
                    resultBox.innerHTML = '<div class="alert alert-danger mb-0">حدث خطأ أثناء الاتصال بقاعدة البيانات.</div>';
                });
        }

        searchBtn?.addEventListener('click', verifySerial);
        searchInput?.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                verifySerial();
            }
        });

        // Supplier settlement: the modal form posts to this claim's settle route (FE-08).
        root.querySelectorAll('.settle-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('settleClaimNumberDisplay').value = this.getAttribute('data-claim-number');
                document.getElementById('settleForm').action = config.settleUrl.replace('__ID__', encodeURIComponent(this.getAttribute('data-claim-id')));
            });
        });
    });
})();
