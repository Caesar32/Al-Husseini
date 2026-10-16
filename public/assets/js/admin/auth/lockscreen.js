/**
 * Lock screen (resources/views/admin/auth/lockscreen.blade.php), frontend F4.
 * Config (data-config on [data-page="auth.lockscreen"]): { lockoutUntil (unix seconds or null) }
 * Moved from the page's inline script.
 */
(function () {
    'use strict';

    Admin.page.register('auth.lockscreen', function (root, config) {
        const passwordInput = document.getElementById('userpassword');
        const passwordAddon = document.getElementById('password-addon');
        const unlockButton = document.getElementById('unlock-button');
        const lockoutAlert = document.getElementById('lockout-alert');
        const countdownElement = document.getElementById('lockout-countdown');

        passwordAddon?.addEventListener('click', function () {
            if (passwordInput.disabled) {
                return;
            }

            passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
        });

        const lockoutUntil = config.lockoutUntil;

        if (!lockoutUntil || !countdownElement || !lockoutAlert) {
            return;
        }

        let timerId = null;

        const setLockedState = (locked) => {
            passwordInput.disabled = locked;
            passwordAddon.disabled = locked;
            unlockButton.disabled = locked;
        };

        const finishLockout = () => {
            if (timerId !== null) {
                clearInterval(timerId);
                timerId = null;
            }

            lockoutAlert.remove();
            passwordInput.disabled = false;
            passwordAddon.disabled = false;
            unlockButton.disabled = false;
            passwordInput.value = '';
            passwordInput.focus();
        };

        const updateCountdown = () => {
            const remaining = Math.max(
                0,
                Math.ceil((lockoutUntil * 1000 - Date.now()) / 1000)
            );

            countdownElement.textContent = remaining;

            if (remaining <= 0) {
                finishLockout();
            }
        };

        setLockedState(true);
        updateCountdown();
        timerId = window.setInterval(updateCountdown, 1000);
        Admin.page.onLeave(() => clearInterval(timerId));
    });
})();
