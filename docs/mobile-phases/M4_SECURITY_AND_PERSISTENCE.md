# M4: Security and persistence

Files: `lib/core/storage/auth_storage.dart`, `lib/presentation/screens/login_screen.dart` (settings dialog), `lib/core/network/api_service.dart`, `android/`, `ios/`.

## Current state
- The token (90-day Sanctum token, ability `owner:monitor`, full executive read access) is stored in `SharedPreferences` as plaintext (F-06). The cached user JSON sits there too.
- The base URL can be changed from the login screen by anyone holding the phone, with presets `http://127.0.0.1:8000/api` and `http://10.0.2.2:8000/api`. No scheme validation. The next login sends credentials, and later the bearer token, to that host (F-07).
- Android: `INTERNET` permission only; no `usesCleartextTraffic` (release blocks http by default on API 28+, debug may not). iOS ATS not reviewed.
- Server-side controls already in place: login throttle `owner-login`, the `owner.valid` middleware revokes the token on deactivation or role loss, tokens expire after 90 days.
- No offline indicator; a failed background poll is invisible.

## Target
- `flutter_secure_storage` (Keychain / EncryptedSharedPreferences) for the token. One-time migration: read the legacy prefs key, write it to secure storage, delete it from prefs. `AuthStorage` API stays synchronous for reads by caching in memory after `init()`.
- Base URL: the settings sheet is available only in `kDebugMode` (or behind a long-press plus confirmation). Accept only `https://` in release; changing the URL clears the token and user.
- Logout and 401 clear both secure storage and prefs.
- Rate-limit UX: on a 429 from login, disable the button with a countdown from `Retry-After`.
- Offline/stale: `connectivity_plus` is optional. Prefer deriving "offline" from `NetworkFailure` plus `DashboardProvider.isStale` (no new dependency); show `StaleDataBanner` (M3) with the last sync time.
- Release hardening review: `android:allowBackup="false"` (keeps the token out of cloud backups), confirm no cleartext in release, iOS ATS default.

## Cards
| Card | File | Change | Acceptance |
|---|---|---|---|
| M4-01 | `pubspec.yaml`, `auth_storage.dart` | Secure token storage + legacy migration | Unit test with a fake secure-storage backend: migration moves and deletes |
| M4-02 | `login_screen.dart` / `server_settings_sheet.dart` | Debug-only gate, https-only in release, clears the session on change | Widget test: the sheet is unreachable when the gate is off; http rejected |
| M4-03 | `login_screen.dart` | 429 countdown | Widget test with a fake provider |
| M4-04 | `AndroidManifest.xml` | `allowBackup=false` | Manual: `flutter build apk --release` succeeds |
| M4-05 | `live_pulse_tab.dart` (via M3 widgets) | Stale/offline banner | Widget test |

New dependency (needs approval): `flutter_secure_storage`.

## Exit criteria
No token in `SharedPreferences` after the first launch on the new version. The release build cannot be pointed at a non-https host.
