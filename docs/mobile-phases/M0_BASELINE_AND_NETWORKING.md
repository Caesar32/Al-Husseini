# M0: Baseline and networking

Files: `lib/core/network/api_service.dart` (142), `lib/core/constants/api_constants.dart`, `lib/main.dart`.

## Current state
- `ApiService` wraps `http.Client` (injectable through the constructor, which is good for tests), adds `Accept`/`Content-Type`/`Bearer`, and applies a 15 s timeout.
- Errors become a single `ApiException(message, statusCode)`. The 401 branch calls `_storage.clearAll()` (not awaited) and throws, but nobody is told the session ended (F-02).
- 422 reads `errors.values.first`, which throws a cast error if `errors` is not `Map<String, dynamic>`. 429 (login throttle `owner-login`), 404, 5xx and HTML error pages all fall into the generic branch (F-09).
- `get`/`post` duplicate the try/catch block. The `catch (e)` turns `FormatException`, `TypeError` and `HandshakeException` into "unexpected error: <dart internals>".
- The server sends a 401 with `code: OWNER_ACCESS_REVOKED` when the user is deactivated or loses `super-admin` (`EnsureOwnerTokenIsValid`), and a 403 with `code: OWNER_ROLE_REQUIRED` on login.

## Target
- `ApiFailure` sealed hierarchy: `NetworkFailure`, `TimeoutFailure`, `UnauthorizedFailure(code)`, `ForbiddenFailure(code)`, `ValidationFailure(fieldErrors)`, `RateLimitedFailure(retryAfter)`, `NotFoundFailure`, `ServerFailure(status)`, `BadResponseFailure`. Each carries an Arabic `message`. Keep `ApiException` as the thrown type (or make `ApiFailure implements Exception`) so call sites change minimally.
- One `_send()` method for both verbs. Decode JSON safely; a non-JSON body on 2xx becomes `BadResponseFailure`.
- A session-expiry signal: `ApiService` exposes a `Stream<void> onUnauthorized` (or a `VoidCallback`), and awaits `clearAll()` before emitting. `AuthProvider` subscribes in M2.
- 429: read the `Retry-After` header; message `محاولات كثيرة، حاول بعد X ثانية`.
- Response envelope helper: `Map<String, dynamic> requireDataMap(json)` / `List requireDataList(json)` that throws `BadResponseFailure` instead of a raw cast error. Repositories use it in M2.

## Cards
| Card | File | Change | Acceptance |
|---|---|---|---|
| M0-01 | `core/network/api_failure.dart` (new) | Sealed failure types plus Arabic messages | Unit test: each type has a non-empty message |
| M0-02 | `api_service.dart` | Single `_send`; map status codes to failures (401/403 read `code`, 422 safe, 429 `Retry-After`, 404, 5xx, non-JSON) | `test/core/api_service_test.dart` with `MockClient` from `package:http/testing.dart`: one case per status, plus timeout and `SocketException` |
| M0-03 | `api_service.dart` | Awaited `clearAll` and an `onUnauthorized` broadcast stream on 401 | Test: a 401 emits once and storage is empty afterwards |
| M0-04 | `core/network/envelope.dart` (new) | `requireDataMap` / `requireDataList` / `readPagination` | Unit tests: missing `data`, wrong type, valid |

## Exit criteria
Analyze clean. No `catch (e)` that stringifies a Dart error into UI text. All status codes the owner API can return (200, 401, 403, 404, 422, 429, 500) are covered by tests.
