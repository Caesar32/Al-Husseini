/**
 * Scrap inventory selection (resources/views/admin/scrap/index.blade.php), frontend F4.
 *
 * Page "scrap.index": select scrap batteries for a factory sale batch.
 * Actions: scrap.select, scrap.check-all, scrap.select-all.
 * Moved from the page's inline script.
 */
(function () {
    'use strict';

    function updateSelection() {
        const checkboxes = document.querySelectorAll('.scrap-checkbox');
        const hiddenInputs = document.getElementById('hiddenInputsContainer');
        const selectedCountDisplay = document.getElementById('selectedCountDisplay');
        const modalBatchCount = document.getElementById('modalBatchCount');
        const modalBatchCost = document.getElementById('modalBatchCost');
        const openSellBtn = document.getElementById('openSellModalBtn');
        let count = 0;
        let totalCost = 0;

        if (hiddenInputs) {
            hiddenInputs.textContent = '';
        }

        checkboxes.forEach((cb) => {
            if (!cb.checked) {
                return;
            }

            count++;
            totalCost += parseFloat(cb.getAttribute('data-value')) || 0;

            if (hiddenInputs) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'scrap_battery_ids[]';
                input.value = cb.value;
                hiddenInputs.appendChild(input);
            }
        });

        if (selectedCountDisplay) {
            selectedCountDisplay.textContent = String(count);
        }
        if (modalBatchCount) {
            modalBatchCount.textContent = String(count);
        }
        if (modalBatchCost) {
            modalBatchCost.textContent = totalCost.toFixed(2);
        }
        if (openSellBtn) {
            if (count > 0) {
                openSellBtn.removeAttribute('disabled');
            } else {
                openSellBtn.setAttribute('disabled', 'disabled');
            }
        }
    }

    Admin.actions.registerAll({
        'scrap.select': () => updateSelection(),
        'scrap.check-all': (el) => {
            document.querySelectorAll('.scrap-checkbox').forEach((cb) => { cb.checked = el.checked; });
            updateSelection();
        },
        'scrap.select-all': () => {
            document.querySelectorAll('.scrap-checkbox').forEach((cb) => { cb.checked = true; });
            const checkAllMaster = document.getElementById('checkAllMaster');
            if (checkAllMaster) {
                checkAllMaster.checked = true;
            }
            updateSelection();
        },
    });

    Admin.page.register('scrap.index', function () {
        updateSelection();
    });
})();
