/**
 * Scrap inventory sale (resources/views/admin/scrap/index.blade.php), frontend F4.
 *
 * Page "scrap.index": choose the batteries of a factory sale batch: ticked rows, the whole stock, or a
 * count. For the last two the server picks the batteries, so a batch is not limited to one table page.
 * Actions: scrap.select, scrap.check-all, scrap.select-all, scrap.mode, scrap.count.
 */
(function () {
    'use strict';

    const byId = (id) => document.getElementById(id);

    function chosenMode() {
        if (byId('sellModeSelected')?.checked) return 'selected';
        if (byId('sellModeAll')?.checked) return 'all';
        if (byId('sellModeCount')?.checked) return 'count';

        return null;
    }

    function stockOnHand() {
        const data = byId('openSellModalBtn')?.dataset ?? {};

        return { units: Number(data.stockUnits) || 0, cost: Number(data.stockCost) || 0 };
    }

    /** @param {boolean} rowsChanged ticking rows is a choice of that mode; clearing them all withdraws it. */
    function updateSelection(rowsChanged = false) {
        const ticked = Array.from(document.querySelectorAll('.scrap-checkbox')).filter((cb) => cb.checked);
        const tickedCost = ticked.reduce((sum, cb) => sum + (parseFloat(cb.getAttribute('data-value')) || 0), 0);
        const selectedRadio = byId('sellModeSelected');

        if (selectedRadio) {
            if (rowsChanged && ticked.length > 0) selectedRadio.checked = true;
            if (ticked.length === 0) selectedRadio.checked = false;
            selectedRadio.disabled = ticked.length === 0;
        }

        const mode = chosenMode();
        const stock = stockOnHand();

        const hiddenInputs = byId('hiddenInputsContainer');
        if (hiddenInputs) {
            hiddenInputs.textContent = '';
            if (mode === 'selected') {
                ticked.forEach((cb) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'scrap_battery_ids[]';
                    input.value = cb.value;
                    hiddenInputs.appendChild(input);
                });
            }
        }

        const countWrap = byId('batchCountWrap');
        const countInput = byId('batchCountInput');
        if (countWrap && countInput) {
            countWrap.hidden = mode !== 'count';
            countInput.disabled = mode !== 'count';
            countInput.required = mode === 'count';
        }

        let batchCount = 0;
        let batchCost = '0.00';
        if (mode === 'selected') {
            batchCount = ticked.length;
            batchCost = tickedCost.toFixed(2);
        } else if (mode === 'all') {
            batchCount = stock.units;
            batchCost = stock.cost.toFixed(2);
        } else if (mode === 'count') {
            batchCount = parseInt(countInput?.value, 10) || 0;
            batchCost = 'تُحسب عند التأكيد'; // Depends on the oldest batteries, which only the server lists.
        }

        const selectedCountDisplay = byId('selectedCountDisplay');
        if (selectedCountDisplay) {
            selectedCountDisplay.textContent = String(ticked.length);
        }
        const modalBatchCount = byId('modalBatchCount');
        if (modalBatchCount) {
            modalBatchCount.textContent = String(batchCount);
        }
        const modalBatchCost = byId('modalBatchCost');
        if (modalBatchCost) {
            modalBatchCost.textContent = batchCost;
        }
    }

    Admin.actions.registerAll({
        'scrap.select': () => updateSelection(true),
        'scrap.check-all': (el) => {
            document.querySelectorAll('.scrap-checkbox').forEach((cb) => { cb.checked = el.checked; });
            updateSelection(true);
        },
        'scrap.select-all': () => {
            document.querySelectorAll('.scrap-checkbox').forEach((cb) => { cb.checked = true; });
            const checkAllMaster = byId('checkAllMaster');
            if (checkAllMaster) {
                checkAllMaster.checked = true;
            }
            updateSelection(true);
        },
        'scrap.mode': () => updateSelection(),
        'scrap.count': () => updateSelection(),
    });

    Admin.page.register('scrap.index', function () {
        updateSelection();
    });
})();
