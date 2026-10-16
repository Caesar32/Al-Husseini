/**
 * Role create/edit permission checkboxes (resources/views/admin/roles/{create,edit}.blade.php), frontend F4.
 * Actions: roles.select-all, roles.deselect-all, roles.toggle-category (data-target = permission group key).
 */
(function () {
    'use strict';

    const setAll = (checked) => document.querySelectorAll('.perm-checkbox').forEach((cb) => { cb.checked = checked; });

    Admin.actions.registerAll({
        'roles.select-all': () => setAll(true),
        'roles.deselect-all': () => setAll(false),
        'roles.toggle-category': (button) => {
            const checkboxes = document.querySelectorAll('.category-' + button.dataset.target);
            const allChecked = Array.from(checkboxes).every((cb) => cb.checked);
            checkboxes.forEach((cb) => { cb.checked = !allChecked; });
        },
    });
})();
