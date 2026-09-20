<!-- JAVASCRIPT -->
<script src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ asset('assets/libs/node-waves/waves.min.js') }}"></script>
<script src="{{ asset('assets/libs/feather-icons/feather.min.js') }}"></script>
<script src="{{ asset('assets/js/pages/plugins/lord-icon-2.1.0.js') }}"></script>
<script src="{{ asset('assets/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ asset('assets/libs/choices.js/public/assets/scripts/choices.min.js') }}"></script>
<script src="{{ asset('assets/js/plugins.js') }}"></script>

@yield('script')
@stack('scripts')

<!-- App js -->
<script src="{{ asset('assets/js/app.js') }}"></script>

<!-- Guaranteed Interactive Handlers for Sidebar, Fullscreen, Dark/Light Mode & Controls -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Fullscreen Toggle
    const fsButtons = document.querySelectorAll('[data-toggle="fullscreen"]');
    fsButtons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            document.body.classList.toggle('fullscreen-enable');
            if (!document.fullscreenElement && !document.mozFullScreenElement && !document.webkitFullscreenElement) {
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen();
                } else if (document.documentElement.mozRequestFullScreen) {
                    document.documentElement.mozRequestFullScreen();
                } else if (document.documentElement.webkitRequestFullscreen) {
                    document.documentElement.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                }
            }
        });
    });

    // 2. Sidebar Toggle (Hamburger Menu)
    const hamburgerBtn = document.getElementById('topnav-hamburger-icon');
    if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const width = document.documentElement.clientWidth;
            const hamburgerIcon = document.querySelector('.hamburger-icon');
            if (hamburgerIcon) {
                hamburgerIcon.classList.toggle('open');
            }

            if (width > 1025) {
                const currentSize = document.documentElement.getAttribute('data-sidebar-size');
                const newSize = (currentSize === 'sm' || currentSize === 'sm-hover') ? 'lg' : 'sm';
                document.documentElement.setAttribute('data-sidebar-size', newSize);
                sessionStorage.setItem('data-sidebar-size', newSize);
            } else if (width <= 767) {
                document.body.classList.toggle('vertical-sidebar-enable');
                document.documentElement.setAttribute('data-sidebar-size', 'lg');
            } else {
                const currentSize = document.documentElement.getAttribute('data-sidebar-size');
                const newSize = currentSize === 'sm' ? 'lg' : 'sm';
                document.documentElement.setAttribute('data-sidebar-size', newSize);
                sessionStorage.setItem('data-sidebar-size', newSize);
            }
        });
    }

    // 3. Vertical Overlay Click (Close sidebar on mobile click)
    const overlay = document.querySelector('.vertical-overlay');
    if (overlay) {
        overlay.addEventListener('click', function () {
            document.body.classList.remove('vertical-sidebar-enable');
            const hamburgerIcon = document.querySelector('.hamburger-icon');
            if (hamburgerIcon) {
                hamburgerIcon.classList.remove('open');
            }
        });
    }

    // 4. Vertical hover button on sidebar top
    const verticalHover = document.getElementById('vertical-hover');
    if (verticalHover) {
        verticalHover.addEventListener('click', function () {
            const currentSize = document.documentElement.getAttribute('data-sidebar-size');
            const newSize = currentSize === 'sm-hover' ? 'sm-hover-active' : 'sm-hover';
            document.documentElement.setAttribute('data-sidebar-size', newSize);
        });
    }

    // 5. Dark / Light Mode Switcher
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        sessionStorage.setItem('data-bs-theme', theme);
        localStorage.setItem('data-bs-theme', theme);

        // Toggle icon in topbar
        const modeIcons = document.querySelectorAll('.light-dark-mode i');
        modeIcons.forEach(function (icon) {
            if (theme === 'dark') {
                icon.classList.remove('bx-moon');
                icon.classList.add('bx-sun');
            } else {
                icon.classList.remove('bx-sun');
                icon.classList.add('bx-moon');
            }
        });

        // Sync customizer radio buttons
        const targetRadio = document.getElementById('layout-mode-' + theme);
        if (targetRadio) {
            targetRadio.checked = true;
        }

        // Trigger resize event for ApexCharts recolor
        window.dispatchEvent(new Event('resize'));
    }

    // Initial theme setup from storage or html attribute
    const initialTheme = sessionStorage.getItem('data-bs-theme') || localStorage.getItem('data-bs-theme') || document.documentElement.getAttribute('data-bs-theme') || 'light';
    applyTheme(initialTheme);

    // Click event for all .light-dark-mode buttons
    const darkLightBtns = document.querySelectorAll('.light-dark-mode');
    darkLightBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            applyTheme(newTheme);
        });
    });

    // Customizer radios change event
    const lightRadio = document.getElementById('layout-mode-light');
    const darkRadio = document.getElementById('layout-mode-dark');
    if (lightRadio) {
        lightRadio.addEventListener('change', function () {
            if (this.checked) applyTheme('light');
        });
    }
    if (darkRadio) {
        darkRadio.addEventListener('change', function () {
            if (this.checked) applyTheme('dark');
        });
    }
});
</script>
