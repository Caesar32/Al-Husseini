# F2: Form and input controls

Tracker: `00_INDEX.md`. Requires F1. Next: F3.

## Rules
- Field names, ids used by JS, `value`/`old()` behaviour and validation rules stay identical. Server validation is the source of truth; HTML attributes (`required`, `maxlength`, `min`) must match the FormRequest rules (see the `national_id` drift found in the backend roadmap).
- Every control is labelled (`<label for>`), and errors are linked with `aria-describedby` + `aria-invalid`.
- No visible change except error states now rendering consistently.

## Objective
Replace hand-written form markup (228 `form-control`, 71 `form-select`, 17 `form-switch`, 10 native `type="date"`) with components that wire label, help text, `old()` and `@error` the same way everywhere. Today only 8 `invalid-feedback` blocks and 17 `@error` exist, and 154 of 221 labels have no `for`.

## Context (verified)
- Most admin forms post via fetch and render JSON 422 errors in JS (HR modals, POS, credit); classic forms use `@error` (users, settings, purchases create, suppliers).
- Flatpickr and Choices.js are loaded globally but used on ~1 page; most dates are native inputs.
- RTL: inputs with numbers/phones/serials need `dir="ltr"` (already used in several places; keep it).

## Components
| Component | Props | Notes |
|---|---|---|
| `<x-form.field>` | `name`, `label`, `help=null`, `required=false`, `id=null` | wrapper: label (`for`), slot, help (`id="{id}-help"`), `@error` feedback (`id="{id}-error"`) |
| `<x-form.input>` | `name`, `type='text'`, `value=null`, `label`, `required=false`, `dir=null` | uses `old($name, $value)`; `aria-invalid`/`aria-describedby` when errored |
| `<x-form.select>` | `name`, `options` (value=>label), `selected=null`, `placeholder=null`, `label` | |
| `<x-form.textarea>` | `name`, `rows=3`, `value=null`, `label` | |
| `<x-form.date>` | `name`, `value=null`, `label`, `min=null`, `max=null` | native `type="date"` (no Flatpickr dependency) |
| `<x-form.switch>` | `name`, `checked=false`, `label`, `value='1'` | hidden `0` input so unchecked posts `0` (only where the controller expects it) |
| `<x-form.errors>` | `bag='default'` | summary list for classic forms, `role="alert"` |
| JS mirror | `Admin.form.showErrors(form, errors)` / `clearErrors(form)` | in F4 `admin-core.js`; toggles the same `is-invalid` + feedback ids for fetch-posted forms |

## Codex Frontend Cards
```text
[PHASE: F2 - Step 1 | TARGET: resources/views/components/form/{field,input,select,textarea}.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Create the four components per docs/frontend-phases/F2_FORM_AND_INPUT_CONTROLS.md; id defaults to Str::slug($name) with dots/brackets converted (items[0][qty] -> items-0-qty).
2. `@props` as listed; `$attributes->merge(['class' => 'form-control'])` passes through placeholder, maxlength, inputmode, autocomplete, data-*.
3. Migrate resources/views/admin/users/index.blade.php create/edit form fields only (classic @error form).
4. Tests tests/Feature/Components/FormFieldTest.php: label for=id, old() wins over value, error renders is-invalid + aria-invalid + aria-describedby pointing at an element that exists.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: users page create/edit modal unchanged; submit an invalid form and see the same error texts. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Components tests/Feature/Admin
```
```text
[PHASE: F2 - Step 2 | TARGET: resources/views/components/form/{date,switch,errors}.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Create the three components per the table.
2. Migrate resources/views/admin/settings (switches) and the date fields of resources/views/admin/purchases/create.blade.php.
3. Confirm every migrated field's HTML constraints match its FormRequest rules; list mismatches in the STATUS line instead of changing rules.
4. Tests for switch hidden-input behaviour and date min/max.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: settings toggles save and reload correctly; purchase create dates unchanged. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Components tests/Feature/Purchases tests/Feature/Admin
```
```text
[PHASE: F2 - Step 3 | TARGET: resources/views/admin/hr/partials/employees/modal-form.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Precondition: the owner's uncommitted edits to this file are resolved (backend roadmap Phase 0 decision). Stop if `git status` shows it modified.
2. Convert the employee modal fields to <x-form.*>; keep every id used by hr/partials/employees/scripts.blade.php.
3. national_id: `required` only if StoreEmployeeRequest requires it.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: add + edit employee from the HR page. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Hr
```

## Acceptance criteria
- [ ] 7 form components with render tests; migrated forms post identical payloads (feature tests green).
- [ ] Every migrated label has `for`; every errored control has `aria-invalid` + `aria-describedby`.
- [ ] HTML constraints match FormRequest rules on migrated fields (mismatches reported to the owner, not silently changed).
