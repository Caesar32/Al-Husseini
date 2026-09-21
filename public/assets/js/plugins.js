/**
 * Velzon Plugins Loader - Al-Husseini Optimized
 * Prevents parser-blocking document.writeln and redundant double-script loading.
 */
(function() {
    'use strict';
    // Flatpickr & Choices are already explicitly loaded in vendor-scripts.blade.php
    // If toast-list exists and Toastify is needed, load dynamically without document.writeln
    if (document.querySelector('[toast-list]') && typeof Toastify === 'undefined') {
        const script = document.createElement('script');
        script.type = 'text/javascript';
        script.src = 'https://cdn.jsdelivr.net/npm/toastify-js';
        script.async = true;
        document.head.appendChild(script);
    }
})();