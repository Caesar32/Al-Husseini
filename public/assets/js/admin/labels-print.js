/**
 * Barcode label print page (resources/views/admin/labels/print.blade.php), a standalone page without
 * the admin layout. <body data-auto-print> opens the print dialog once the page is ready, unless a
 * product name does not fit its label: printing that would silently lose the end of the name.
 */
(function () {
    'use strict';

    const MAX_TITLE_LINES = 2;

    /** Lines the title needs without the CSS clamp. */
    function titleLines(title) {
        title.style.display = 'block';
        title.style.webkitLineClamp = 'none';
        title.style.flex = 'none'; // the label's flex column would otherwise squeeze a tall title
        const lines = Math.round(title.getBoundingClientRect().height / parseFloat(getComputedStyle(title).lineHeight));
        title.style.display = '';
        title.style.webkitLineClamp = '';
        title.style.flex = '';

        return lines;
    }

    function warnAboutCutTitles() {
        const cut = Array.from(document.querySelectorAll('.label__title')).filter((title) => titleLines(title) > MAX_TITLE_LINES);
        if (cut.length === 0) return false;

        document.getElementById('titleCutList').textContent = Array.from(new Set(cut.map((title) => title.textContent.trim()))).join('، ');
        document.getElementById('titleCutNotice').hidden = false;

        return true;
    }

    document.getElementById('printLabels').addEventListener('click', function () { window.print(); });
    document.getElementById('closeLabels').addEventListener('click', function () { window.close(); });

    // Waits for the web font: it decides how many lines a name takes and how the labels are laid out.
    window.addEventListener('load', function () {
        document.fonts.ready.then(function () {
            const hasCutTitle = warnAboutCutTitles();
            if (!hasCutTitle && document.body.hasAttribute('data-auto-print')) window.print();
        });
    });
})();
