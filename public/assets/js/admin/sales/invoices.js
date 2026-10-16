/**
 * Sales invoices list (resources/views/admin/sales/invoices.blade.php), frontend F4.
 * Actions: invoices.preset (data-preset), invoices.export. Functions moved verbatim.
 */
(function () {
    'use strict';

    // Apply Quick Date Presets
    function applyDatePreset(preset) {
        const today = new Date();
        const dateFromInput = document.getElementById('filter_date_from');
        const dateToInput = document.getElementById('filter_date_to');
        const form = document.getElementById('invoicesFilterForm');

        function formatDate(d) {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        if (preset === 'today') {
            const todayStr = formatDate(today);
            dateFromInput.value = todayStr;
            dateToInput.value = todayStr;
        } else if (preset === 'yesterday') {
            const yest = new Date();
            yest.setDate(today.getDate() - 1);
            const yestStr = formatDate(yest);
            dateFromInput.value = yestStr;
            dateToInput.value = yestStr;
        } else if (preset === 'last7') {
            const d7 = new Date();
            d7.setDate(today.getDate() - 6);
            dateFromInput.value = formatDate(d7);
            dateToInput.value = formatDate(today);
        } else if (preset === 'this_month') {
            const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            dateFromInput.value = formatDate(startOfMonth);
            dateToInput.value = formatDate(today);
        } else if (preset === 'clear') {
            dateFromInput.value = '';
            dateToInput.value = '';
        }

        if (form) {
            form.submit();
        }
    }

    // Export Table to CSV
    function exportTableToCSV() {
        const table = document.getElementById('invoicesTable');
        if (!table) return;

        let csv = [];
        const rows = table.querySelectorAll('tr');

        for (let i = 0; i < rows.length; i++) {
            let row = [], cols = rows[i].querySelectorAll('td, th');
            // Exclude the last action column
            for (let j = 0; j < cols.length - 1; j++) {
                let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/(\s\s+)/gm, ' ').trim();
                data = data.replace(/"/g, '""');
                row.push('"' + data + '"');
            }
            if (row.length > 0) {
                csv.push(row.join(','));
            }
        }

        const csvFile = new Blob(['\uFEFF' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const downloadLink = document.createElement('a');
        const dateStr = new Date().toISOString().split('T')[0];
        downloadLink.download = `فواتير_مبيعات_مركز_الحسيني_${dateStr}.csv`;
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = 'none';
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    }

    Admin.actions.registerAll({
        'invoices.preset': (el) => applyDatePreset(el.dataset.preset),
        'invoices.export': () => exportTableToCSV(),
    });
})();
