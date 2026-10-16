# Phase 1: Security and correctness bugs (B1-B6)

Status tracker: `REFACTOR_PLAN.md`. Requires Phase 0. Next phase: `02_phase_stock_movement_journal.md`.

## Rules
- Laravel 12, PHP 8.2, Pest. Tests run on SQLite `:memory:` with FK/CHECK enforced; never disable it.
- Never weaken, skip or delete tests. Every bug fix gets a regression test.
- Change only the listed lines. No new layers, no Policies/Events/Actions.
- Run Pint only on touched files. Don't commit or push unless the owner asks.

## Objective
Fix confirmed defects in code added after the remediation. Each fix stays local to its files.

SOLID:
- SRP: one middleware owns owner-token validity.
- DIP: job dispatch is decoupled from transaction timing.

## Bugs

### B1: Owner API token outlives deactivation and role removal
- **Where:**
  - `routes/api.php:30`: the middleware is only `auth:sanctum` + `ability:owner:monitor`.
  - `app/Http/Controllers/Api/V1/Owner/AuthController.php:37-57`: `is_active` and `super-admin` are checked once, at login.
  - `bootstrap/app.php:18-26`: `EnsureUserIsActive` is appended to the web group only.
- **Repro:**
  1. Log in as super-admin via `POST /api/v1/owner/auth/login` and take the token.
  2. Set `users.is_active = 0`, or remove the `super-admin` role.
  3. Call `GET /api/v1/owner/auth/me` with the token. It returns 200 and does so for 90 days.
- **Fix contract:**
```php
final class EnsureOwnerTokenIsValid // app/Http/Middleware/EnsureOwnerTokenIsValid.php
{
    // user null -> $next. If !is_active || !hasRole('super-admin'):
    // delete currentAccessToken(); return 401 JSON {status:error, code:OWNER_ACCESS_REVOKED}.
    public function handle(Request $request, Closure $next): Response;
}
// alias 'owner.valid'; routes/api.php group: ['auth:sanctum','ability:owner:monitor','owner.valid']
// UserController: when is_active becomes false -> $user->tokens()->delete().
```

### B2: Stored XSS in the purchase create live search
- **Where:** `resources/views/admin/purchases/create.blade.php`
  - Lines 250 and 391 use `{!! json_encode($x, JSON_UNESCAPED_UNICODE) !!}` with no `JSON_HEX_TAG`.
  - Lines 265-271 and 402-414 interpolate `${s.company_name}`, `${s.name}` and the product fields into `innerHTML`.
- **Repro:**
  1. Create a supplier whose `company_name` is `</script><script>alert(1)</script>`.
  2. Open the purchase create page. The script runs.
  3. A name like `<img src=x onerror=alert(1)>` also runs when it appears in the search results.
- **Fix contract:** use `@json($x)`. Build the result rows with `createElement` and set every server value through `textContent`.

### B3: XSS and attribute injection in the warranty serial verify
- **Where:** `resources/views/admin/warranties/index.blade.php:318-340`
  - `data.message`, the customer name and phone, `end_date` and `days_remaining` go into `innerHTML` unescaped.
  - `onclick="openClaimModal('${serial}')"` puts the typed serial into inline JS.
- **Repro:** verify the serial `x');alert(1);('`, then click the claim button. The alert runs.
- **Fix contract:**
  - Add a local `esc()` and wrap every interpolated value in it.
  - Replace the inline `onclick` with `data-serial` plus `addEventListener`.

### B4: Push job dispatched inside a transaction
- **Where:** `DispatchOwnerPushNotification::dispatch` is called inside `DB::transaction` at:
  - `app/Services/Sales/PosOrderService.php:361,425,569`
  - `app/Services/Sales/WarrantyService.php:174`
  - `app/Services/Hr/AttendanceService.php:194`
  - `config/queue.php:44` sets `after_commit => false`.
- **Repro:** on any driver other than `database` (sync or redis), a sale that rolls back after the dispatch still sends the notification.
- **Fix contract:** call `$this->afterCommit()` in the job's constructor.

### B5: Device token written to the logs
- **Where:** `app/Jobs/DispatchOwnerPushNotification.php:67-72` logs the first 15 characters of each device token.
- **Fix contract:** log only the device id and platform.

### B6: Optional `national_id` has no test
- **Where:**
  - Commit `7b95861` made `national_id` nullable in `app/Http/Requests/Hr/StoreEmployeeRequest.php` and `UpdateEmployeeRequest.php`.
  - No test covers this.
- **Fix contract:** tests only. Production code changes only if a test exposes a bug, for example a unique rule that collides on null.

## Codex prompts
```text
[TASK: Phase 1 - Step 1 (B1)]
ROLE: Strict Code Executor.
ACTION: Fix B1 (SRP: one middleware owns owner-token validity).
TARGET_FILE: new `app/Http/Middleware/EnsureOwnerTokenIsValid.php`, `bootstrap/app.php` ($middleware->alias block), `routes/api.php:30`, `app/Http/Controllers/Admin/UserController.php` (status toggle ~L187-218)
CHANGES_REQUIRED:
1. Create final class EnsureOwnerTokenIsValid::handle(Request $request, Closure $next): Response. If $request->user() null -> return $next($request). If !$user->is_active || !$user->hasRole('super-admin'): $user->currentAccessToken()?->delete(); return response()->json(['status'=>'error','code'=>'OWNER_ACCESS_REVOKED','message'=>'تم إلغاء صلاحية الوصول.'], 401).
2. bootstrap/app.php: add 'owner.valid' => \App\Http\Middleware\EnsureOwnerTokenIsValid::class to $middleware->alias.
3. routes/api.php:30: ['auth:sanctum','ability:owner:monitor','owner.valid'].
4. UserController status toggle (~L187-218): when the user ends up is_active=false, also call $user->tokens()->delete() before returning.
5. tests/Feature/Api/Owner/OwnerAuthTest.php add, using REAL tokens ($t = $user->createToken('d', ['owner:monitor'])->plainTextToken; ->withToken($t)), NOT Sanctum::actingAs (its token is a mock and cannot prove deletion): (a) is_active=false -> GET /api/v1/owner/auth/me 401 code OWNER_ACCESS_REVOKED and personal_access_tokens count 0; (b) $user->removeRole('super-admin') -> 401; (c) active super-admin -> 200; (d) toggling the user inactive via the UserController route deletes all its tokens.
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Api/Owner tests/Feature/Admin/RoleAndCashierTest.php`.
```
```text
[TASK: Phase 1 - Step 2 (B2)]
ROLE: Strict Code Executor.
ACTION: Fix stored XSS B2. No logic change.
TARGET_FILE: `resources/views/admin/purchases/create.blade.php` lines 245-421 only.
CHANGES_REQUIRED:
1. L250 and L391: `{!! json_encode($x, JSON_UNESCAPED_UNICODE) !!}` -> `@json($x)`.
2. Supplier and product result renderers: build rows with document.createElement; every server value (company_name, name, balance, sku, barcode, product name, stock, price) via textContent. Keep classes, active-index highlight, mousedown selection, badges, keyboard nav.
3. New test tests/Feature/Purchases/PurchaseCreateXssTest.php: supplier company_name '</script><script>alert(1)</script>'; GET purchase create as super-admin; assertDontSee('</script><script>alert(1)', false); assertSee('<\/script>', false).
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Purchases`.
```
```text
[TASK: Phase 1 - Step 3 (B3)]
ROLE: Strict Code Executor.
ACTION: Fix XSS/attribute injection B3.
TARGET_FILE: `resources/views/admin/warranties/index.blade.php` lines 305-346 only.
CHANGES_REQUIRED:
1. Add `const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));` inside the script scope.
2. Wrap with esc(): data.message (both templates), warranty.customer.name, warranty.customer.phone, warranty.end_date, days_remaining.
3. Replace `onclick="openClaimModal('${serial}')"` with `class="... js-open-claim" data-serial="${esc(serial)}"`; after innerHTML assignment: resultBox.querySelector('.js-open-claim')?.addEventListener('click', e => openClaimModal(e.currentTarget.dataset.serial)).
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Warranty`.
```
```text
[TASK: Phase 1 - Step 4 (B4, B5)]
ROLE: Strict Code Executor.
ACTION: Dispatch after commit (DIP on transaction timing); stop logging credentials.
TARGET_FILE: `app/Jobs/DispatchOwnerPushNotification.php`
CHANGES_REQUIRED:
1. Constructor body: `$this->afterCommit();` (keep promoted properties).
2. L67-72 Log::debug: remove device_token; log device id + platform only.
3. tests/Feature/Api/Owner/OwnerPushNotificationTest.php add (Queue::fake records immediately, so it cannot prove deferral; do not rely on it):
   (a) expect((new DispatchOwnerPushNotification('t','b'))->afterCommit)->toBeTrue();
   (b) config(['queue.default' => 'sync']); Log::spy(); try { DB::transaction(function () { DispatchOwnerPushNotification::dispatch('t','b'); throw new RuntimeException; }); } catch (RuntimeException) {} -> Log::shouldNotHaveReceived('info') for any message starting '[Owner Push]';
   (c) same with a committed transaction -> Log::shouldHaveReceived('info') with a '[Owner Push]' message (no owners/devices path is fine).
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Api/Owner`.
```
```text
[TASK: Phase 1 - Step 5 (B6)]
ROLE: Strict Code Executor.
ACTION: Add regression coverage B6. Production change only if a test fails.
TARGET_FILE: new `tests/Feature/Hr/EmployeeNationalIdTest.php`; if needed `app/Http/Requests/Hr/StoreEmployeeRequest.php`, `app/Http/Requests/Hr/UpdateEmployeeRequest.php`
CHANGES_REQUIRED:
0. HTTP tests (not service-level). Acting user: super-admin with branch_id = MAIN branch. Build the full valid payload from the rules() of StoreEmployeeRequest/UpdateEmployeeRequest (department + title text fields, branch_id, salary fields); send JSON with header Accept: application/json.
1. POST route('admin.hr.employees.store') without national_id -> 2xx; employees.national_id null.
2. Two POSTs without national_id -> both 2xx.
3. POST with national_id '123' -> 422 with error key national_id.
4. PUT route('admin.hr.employees.update', $employee) with national_id null on an employee that had one -> 2xx; null stored.
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Hr`.
```

## Acceptance criteria
- [ ] Steps 1-5 are applied, and each step's verification command passes.
- [ ] `php artisan test --compact` shows 0 failed and 0 skipped, with passed ≥ `N0` + the new tests.
- [ ] `vendor/bin/pint --test <touched php files>` passes.
- [ ] `grep -rn "{!!" resources/views` has no `json_encode` left without HEX flags.
- [ ] Manual browser check:
  - Purchase create search works and selects correctly.
  - Warranty verify still opens the claim modal.
  - An owner token is refused after the user is deactivated.
- [ ] Record the passed count in `REFACTOR_PLAN.md` as `N1`.
