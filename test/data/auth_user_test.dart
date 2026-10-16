import 'package:flutter_test/flutter_test.dart';

import 'package:alhusseini_app/data/models/auth_user.dart';

void main() {
  group('AuthUser.fromJson', () {
    test('reads the complete server shape', () {
      final user = AuthUser.fromJson({
        'id': 7,
        'name': 'أحمد علي',
        'email': 'ahmed@example.com',
        'phone': '01000000000',
        'role': 'super-admin',
        'avatar_url': 'https://example.com/avatar.png',
        'branch': {
          'id': 3,
          'name': 'الفرع الرئيسي',
          'code': 'MAIN',
        },
      });

      expect(user.id, 7);
      expect(user.name, 'أحمد علي');
      expect(user.email, 'ahmed@example.com');
      expect(user.phone, '01000000000');
      expect(user.role, 'super-admin');
      expect(user.avatarUrl, 'https://example.com/avatar.png');
      expect(user.branch?.id, 3);
      expect(user.branch?.name, 'الفرع الرئيسي');
      expect(user.branch?.code, 'MAIN');
    });

    test('uses defaults for missing and null values', () {
      final user = AuthUser.fromJson({
        'id': null,
        'name': null,
        'email': null,
        'phone': null,
        'role': null,
        'avatar_url': null,
        'branch': null,
      });

      expect(user.id, 0);
      expect(user.name, '');
      expect(user.email, '');
      expect(user.phone, '');
      expect(user.role, 'owner');
      expect(user.avatarUrl, isNull);
      expect(user.branch, isNull);
    });

    test('accepts numeric strings', () {
      final user = AuthUser.fromJson({
        'id': '12',
        'branch': {
          'id': '4',
          'name': 'فرع المدينة',
          'code': 18,
        },
      });

      expect(user.id, 12);
      expect(user.branch?.id, 4);
      expect(user.branch?.code, '18');
    });

    test('toJson and fromJson round trip', () {
      const original = AuthUser(
        id: 11,
        name: 'سارة',
        email: 'sara@example.com',
        phone: '01111111111',
        role: 'owner',
        avatarUrl: 'avatar.png',
        branch: BranchInfo(
          id: 2,
          name: 'فرع النخيل',
          code: 'PALM',
        ),
      );

      final restored = AuthUser.fromJson(original.toJson());

      expect(restored.id, original.id);
      expect(restored.name, original.name);
      expect(restored.email, original.email);
      expect(restored.phone, original.phone);
      expect(restored.role, original.role);
      expect(restored.avatarUrl, original.avatarUrl);
      expect(restored.branch?.id, original.branch?.id);
      expect(restored.branch?.name, original.branch?.name);
      expect(restored.branch?.code, original.branch?.code);
    });
  });
}
