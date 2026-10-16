/**
 * Login page (resources/views/admin/auth/login.blade.php), frontend F4.
 * Config (data-config on [data-page="auth.login"]): { lockoutSeconds }
 * Action: auth.fill-login (account switcher buttons). Moved from the page's inline script.
 */
(function () {
    'use strict';

    Admin.page.register('auth.login', function (root, config) {
        function fillLogin(button) {
            const loginInput = document.getElementById('loginInput');
            const passInput = document.getElementById('password-input');
            const switcher = document.getElementById('account-switcher');
            const password = switcher ? switcher.dataset.password : undefined;

            if (loginInput) loginInput.value = button.dataset.login;
            if (passInput) {
                passInput.value = password !== undefined ? password : '';
                if (password === undefined) passInput.focus(); // no preset password: the user types it
            }

            document.querySelectorAll('#account-switcher .account-switch').forEach(function (b) {
                b.classList.toggle('active', b === button);
            });
        }

        // Lockout countdown (was the DOMContentLoaded handler).
        function init() {
            const loginInput = document.getElementById('loginInput');
            const passwordInput = document.getElementById('password-input');
            const rememberCheck = document.getElementById('auth-remember-check');
            const btnLogin = document.getElementById('btn-login');
            const loginForm = document.getElementById('login-form');
            const lockoutAlert = document.getElementById('login-lockout-alert');
            const timerAlertSpan = document.getElementById('login-lockout-timer');
            const timerInputSpan = document.getElementById('login-input-timer');
            const fieldError = document.getElementById('login-field-error');

            let seconds = parseInt(config.lockoutSeconds, 10) || 0;

            if (seconds <= 0 && timerAlertSpan) {
                seconds = parseInt(timerAlertSpan.textContent.trim(), 10) || 0;
            }

            if (seconds > 0) {
                // Disable form elements during lockout
                if (loginInput) {
                    loginInput.disabled = true;
                    loginInput.setAttribute('disabled', 'disabled');
                    loginInput.classList.add('bg-light');
                    loginInput.blur();
                }
                if (passwordInput) {
                    passwordInput.disabled = true;
                    passwordInput.setAttribute('disabled', 'disabled');
                    passwordInput.classList.add('bg-light');
                }
                if (rememberCheck) {
                    rememberCheck.disabled = true;
                }
                if (btnLogin) {
                    btnLogin.disabled = true;
                    btnLogin.setAttribute('disabled', 'disabled');
                    btnLogin.classList.add('disabled');
                }

                // Real-time live countdown
                const timerInterval = setInterval(function () {
                    seconds--;

                    if (timerAlertSpan) {
                        timerAlertSpan.textContent = seconds;
                    }
                    if (timerInputSpan) {
                        timerInputSpan.textContent = seconds;
                    }

                    if (seconds <= 0) {
                        clearInterval(timerInterval);

                        // Automatically re-enable all fields and buttons
                        if (loginInput) {
                            loginInput.disabled = false;
                            loginInput.removeAttribute('disabled');
                            loginInput.classList.remove('bg-light');
                            loginInput.focus();
                        }
                        if (passwordInput) {
                            passwordInput.disabled = false;
                            passwordInput.removeAttribute('disabled');
                            passwordInput.classList.remove('bg-light');
                        }
                        if (rememberCheck) {
                            rememberCheck.disabled = false;
                        }
                        if (btnLogin) {
                            btnLogin.disabled = false;
                            btnLogin.removeAttribute('disabled');
                            btnLogin.classList.remove('disabled');
                        }

                        // Automatically remove lockout alerts without page refresh
                        if (lockoutAlert) {
                            lockoutAlert.style.transition = 'opacity 0.5s ease';
                            lockoutAlert.style.opacity = '0';
                            setTimeout(function () {
                                lockoutAlert.remove();
                            }, 500);
                        }
                        if (fieldError) {
                            fieldError.style.transition = 'opacity 0.5s ease';
                            fieldError.style.opacity = '0';
                            setTimeout(function () {
                                fieldError.remove();
                            }, 500);
                        }
                    }
                }, 1000);

                // Prevent submission during lockout
                if (loginForm) {
                    loginForm.addEventListener('submit', function (e) {
                        if (seconds > 0) {
                            e.preventDefault();
                            e.stopPropagation();
                            return false;
                        }
                    });
                }
            }
        }

        Admin.actions.register('auth.fill-login', (button) => fillLogin(button));
        init();
    });
})();
