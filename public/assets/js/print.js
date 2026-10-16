/* Print button for standalone print pages (receipt, warranty certificate, purchase receipt),
   which do not load the admin core. Usage: <button data-action="print">. (frontend F4) */
document.addEventListener('click', function (event) {
    if (event.target.closest('[data-action="print"]')) {
        window.print();
    }
});
