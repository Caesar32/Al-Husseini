# F0: Baseline, pipeline decision and design tokens

Tracker: `00_INDEX.md`. Requires: nothing. Next: F1 (and F4 in parallel).

## Rules
- No visible change in this phase. Tokens are introduced as aliases of the values already in use.
- Don't remove Vite, Tailwind or any file (owner decision). Don't add npm or Composer dependencies.
- Never weaken tests. Don't commit unless the owner asked.

## Objective
Record a reproducible frontend baseline, settle how new JS/CSS is delivered (D-F1), centralize design tokens in one stylesheet, and add a ratchet test so inline JS and inline styles can only go down.

## Context (verified)
- CSS load order (`layouts/partials/head-css.blade.php`): Google Fonts (Cairo, Almarai, Outfit) -> `layout.js` -> `bootstrap(-rtl).min.css` -> `icons.min.css` -> `app(-rtl).min.css` -> SweetAlert2 CSS -> a ~880-line inline `<style>` with `:root` tokens (L50+: fonts, `--brand-sapphire/gold/navy`, `--vz-border-radius*`) and dark-mode overrides (`[data-bs-theme="dark"]`, L396+).
- `master-without-nav.blade.php` (auth pages) has its own head; check it shares the same tokens.
- POS has a separate 729-line `sales/pos/partials/styles.blade.php`.
- Vite/Tailwind: configured, built in CI (`.github/workflows/ci.yml`), not referenced by any view.

## Steps
### Step 1: Baseline (orchestrator)
- `php artisan test --compact` -> record the count in `00_INDEX.md`; record `FE0_SHA`.
- `php artisan view:cache && php artisan view:clear` must succeed.

### Step 2: Hygiene ratchet test
```text
[PHASE: F0 - Step 2 | TARGET: tests/Architecture/FrontendHygieneTest.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Create a Pest test that scans resources/views/**/*.blade.php and counts: inline event attributes (regex `\son(click|change|submit|input|keyup|keydown|keypress|blur|focus)\s*=`), inline `<script>` blocks without `src`, `style="` attributes, `<style` blocks, `{!!` occurrences.
2. Hold the maxima in one constant array FRONTEND_BUDGET = ['handlers' => <measured>, 'scripts' => <measured>, 'styles' => <measured>, 'style_blocks' => <measured>, 'raw_echo' => 0]; assert each count <= its budget and print file:count of the top offenders in the failure message.
3. Add a self-check dataset proving each regex matches a sample string (so the guard cannot silently pass).
4. Header comment: lower a budget in the same commit that removes the code; never raise it.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: php artisan test --compact tests/Architecture/FrontendHygieneTest.php ; php artisan view:cache && php artisan view:clear
```

### Step 3: Token stylesheet
```text
[PHASE: F0 - Step 3 | TARGET: public/assets/css/tokens.css, resources/views/admin/layouts/partials/head-css.blade.php, resources/views/admin/layouts/master-without-nav.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Create public/assets/css/tokens.css with `:root` custom properties grouped and commented:
   color: --ah-color-primary (= #1a4480), -primary-dark, -primary-light, --ah-color-accent (= #c59b27), -accent-strong, -accent-soft, --ah-color-ink (= #0c141f), semantic --ah-color-success/-warning/-danger/-info mapped to var(--bs-success) etc.;
   type: --ah-font-sans (Cairo, Almarai, system), --ah-font-numeric (Outfit, Poppins, ...), --ah-fs-xs..--ah-fs-2xl (rem scale), --ah-fw-regular/medium/semibold/bold;
   space: --ah-space-1..--ah-space-8 (0.25rem steps matching Bootstrap spacers);
   radius: --ah-radius-sm/md/lg/xl/2xl (current --vz-border-radius* values);
   elevation: --ah-shadow-sm/md/lg (copy the shadows currently used by .card and .dropdown-menu in head-css);
   focus: --ah-focus-ring (2px solid var(--ah-color-primary-light), offset 2px);
   touch: --ah-touch-min (44px).
   Dark theme: `[data-bs-theme="dark"]` block redefining only the color tokens that head-css already overrides.
2. Values must equal what head-css uses today. Then make the existing head-css `:root` variables reference the tokens (e.g. `--brand-sapphire: var(--ah-color-primary);`) instead of literals. No selector or rule changes.
3. Link tokens.css right after the Bootstrap CSS in both layouts (`<link href="{{ asset('assets/css/tokens.css') }}" ...>`).
4. Add a render test (tests/Feature/Admin/FrontendTokensTest.php): the dashboard and the login page both link tokens.css.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: dashboard, POS and login in light and dark mode look identical to the F0 screenshots. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Admin/FrontendTokensTest.php
```

### Step 4: Screenshot baseline (orchestrator, D-F3 manual option)
Capture light + dark, desktop 1440px and mobile 390px, for: login, dashboard, POS (empty cart and with 2 lines), products, invoices list, invoice show, credit, HR employees/attendance/payroll, warranties, purchases create. Store them outside the repo (owner's choice) and list the set in `00_INDEX.md`.

### Step 5: Record D-F1 / D-F2 / D-F3
Ask the owner; record the answers in `00_INDEX.md`. F4 cannot start before D-F1 and D-F2 are recorded.

## Acceptance criteria
- [ ] Baseline test count, `FE0_SHA` and the hygiene budget are recorded.
- [ ] `FrontendHygieneTest` passes and its self-check fails if a regex is broken.
- [ ] `tokens.css` is linked by both layouts; screenshots show no difference.
- [ ] Owner decisions D-F1, D-F2, D-F3 recorded.
