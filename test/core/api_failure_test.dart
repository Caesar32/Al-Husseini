import 'package:flutter_test/flutter_test.dart';
import 'package:alhusseini_app/core/network/api_failure.dart';

void main() {
  group('ApiFailure', () {
    test('all failure types expose an Arabic message and exception identity', () {
      final failures = <ApiFailure>[
        const NetworkFailure(),
        const TimeoutFailure(),
        const UnauthorizedFailure(),
        const ForbiddenFailure(),
        const ValidationFailure(),
        RateLimitedFailure(
          retryAfter: const Duration(seconds: 5),
        ),
        const NotFoundFailure(),
        ServerFailure(statusCode: 500),
        const BadResponseFailure(),
      ];

      final statusCodes = <int?>[
        null,
        null,
        401,
        403,
        422,
        429,
        404,
        500,
        null,
      ];

      for (var index = 0; index < failures.length; index++) {
        final failure = failures[index];

        expect(failure.message, isNotEmpty);
        expect(failure.statusCode, statusCodes[index]);
        expect(failure.toString(), failure.message);
        expect(failure, isA<ApiFailure>());
        expect(failure, isA<Exception>());
      }
    });

    test('rate limited failure includes the retry duration', () {
      final failure = RateLimitedFailure(
        retryAfter: const Duration(seconds: 12),
      );

      expect(failure.message, contains('12 ثانية'));
      expect(failure.retryAfter, const Duration(seconds: 12));
    });

    test('rate limited failure has a fallback message without retry duration', () {
      final failure = RateLimitedFailure();

      expect(failure.message, 'محاولات كثيرة، حاول مرة أخرى بعد قليل.');
    });

    test('custom messages and codes are preserved', () {
      const unauthorized = UnauthorizedFailure(
        message: 'رسالة مخصصة.',
        code: 'OWNER_ACCESS_REVOKED',
      );
      const forbidden = ForbiddenFailure(
        code: 'OWNER_ROLE_REQUIRED',
      );

      expect(unauthorized.message, 'رسالة مخصصة.');
      expect(unauthorized.code, 'OWNER_ACCESS_REVOKED');
      expect(forbidden.code, 'OWNER_ROLE_REQUIRED');
    });

    test('validation failure exposes field errors', () {
      const failure = ValidationFailure(
        fieldErrors: {
          'email': ['البريد الإلكتروني غير صالح.'],
        },
      );

      expect(failure.fieldErrors['email'], ['البريد الإلكتروني غير صالح.']);
      expect(failure.statusCode, 422);
    });
  });
}
