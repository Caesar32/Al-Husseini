# M5: Quality and testing

## Current state
- `test/widget_test.dart` only does `expect(true, isTrue)`. Zero real coverage.
- `analysis_options.yaml` includes `flutter_lints` with no extra rules; analyze is clean (0 issues).
- `device_preview` is in `dependencies` (shipped in release) (F-12).
- No CI job for the Flutter app (the repo CI targets Laravel).

## Target test layout
```
test/
  fixtures/            # JSON shaped exactly like app/Http/Resources/Owner/* output
  core/                # api_service (MockClient), envelope, json_read
  data/                # one test per model; repositories with MockClient
  providers/           # fake repositories, fake_async for polling
  widgets/             # AsyncStateView, cards, invoice sheet, login form
```
Tests are added in the phase that changes the code (M0-M4). M5 fills the gaps and adds gates.

## Cards
| Card | Change | Acceptance |
|---|---|---|
| M5-01 | Replace the smoke test with an app boot test (splash routes to login with empty storage, to the shell with a token) | Passes |
| M5-02 | Stricter lints: `prefer_const_constructors`, `avoid_dynamic_calls`, `always_declare_return_types`, `unawaited_futures`, `avoid_catches_without_on_clauses` (fix the findings in separate cards, a few files each) | analyze 0 |
| M5-03 | Move `device_preview` to `dev_dependencies` (or remove it; owner decision). Check that `main.dart` does not import it | Release build OK |
| M5-04 | Coverage report `flutter test --coverage`; target ≥ 70% for `lib/core`, `lib/data`, `lib/providers` | Report in the index |
| M5-05 | Release build gate: `flutter build apk --release` (and `--analyze-size` once) | Build succeeds; APK size recorded in the index |
| M5-06 | Optional CI workflow `flutter analyze && flutter test` on `lib/**`/`test/**` changes | Green run |
| M5-07 | Add a "superseded by the controllers; see `docs/mobile-phases/M1`" note to the top of `docs/OWNER_MOBILE_API_PLAN.md` | Docs only |

## Exit criteria
Analyze is clean under the stricter lints, all tests are green, the coverage target is met, and the release APK builds. Update the baseline table in `00_INDEX.md` with the final numbers.
