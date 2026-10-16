sealed class ApiFailure implements Exception {
  const ApiFailure({
    required this.message,
    this.statusCode,
    this.code,
  });

  final String message;
  final int? statusCode;
  final String? code;

  @override
  String toString() => message;
}

final class NetworkFailure extends ApiFailure {
  const NetworkFailure({
    super.message = 'لا يوجد اتصال بالإنترنت أو الخادم غير متاح حالياً.',
  });
}

final class TimeoutFailure extends ApiFailure {
  const TimeoutFailure({
    super.message = 'تعذر الاتصال بالخادم (انتهت مهلة الانتظار).',
  });
}

final class UnauthorizedFailure extends ApiFailure {
  const UnauthorizedFailure({
    super.message = 'انتهت جلسة الدخول. يرجى تسجيل الدخول مجدداً.',
    super.code,
  }) : super(statusCode: 401);
}

final class ForbiddenFailure extends ApiFailure {
  const ForbiddenFailure({
    super.message = 'غير مصرح لك بالوصول لهذا المورد.',
    super.code,
  }) : super(statusCode: 403);
}

final class ValidationFailure extends ApiFailure {
  const ValidationFailure({
    super.message = 'بيانات غير صالحة.',
    this.fieldErrors = const {},
  }) : super(statusCode: 422);

  final Map<String, List<String>> fieldErrors;
}

final class RateLimitedFailure extends ApiFailure {
  RateLimitedFailure({
    this.retryAfter,
    String? message,
  }) : super(
          message: message ??
              (retryAfter == null
                  ? 'محاولات كثيرة، حاول مرة أخرى بعد قليل.'
                  : 'محاولات كثيرة، حاول مرة أخرى بعد '
                      '${retryAfter.inSeconds} ثانية.'),
          statusCode: 429,
        );

  final Duration? retryAfter;
}

final class NotFoundFailure extends ApiFailure {
  const NotFoundFailure({
    super.message = 'العنصر المطلوب غير موجود.',
  }) : super(statusCode: 404);
}

final class ServerFailure extends ApiFailure {
  ServerFailure({
    required int statusCode,
    String? message,
  }) : super(
          message: message ?? 'حدث خطأ في الخادم (رمز $statusCode).',
          statusCode: statusCode,
        );
}

final class BadResponseFailure extends ApiFailure {
  const BadResponseFailure({
    super.message = 'استجابة غير متوقعة من الخادم.',
  });
}
