import 'json_read.dart';

class AuthUser {
  final int id;
  final String name;
  final String email;
  final String phone;
  final String role;
  final String? avatarUrl;
  final BranchInfo? branch;

  const AuthUser({
    required this.id,
    required this.name,
    required this.email,
    required this.phone,
    required this.role,
    this.avatarUrl,
    this.branch,
  });

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    final branchJson = readMap(json['branch']);

    return AuthUser(
      id: readInt(json['id']),
      name: readString(json['name']),
      email: readString(json['email']),
      phone: readString(json['phone']),
      role: readString(json['role'], fallback: 'owner'),
      avatarUrl: readStringOrNull(json['avatar_url']),
      branch: branchJson.isEmpty ? null : BranchInfo.fromJson(branchJson),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'phone': phone,
      'role': role,
      'avatar_url': avatarUrl,
      'branch': branch?.toJson(),
    };
  }
}

class BranchInfo {
  final int id;
  final String name;
  final String? code;

  const BranchInfo({
    required this.id,
    required this.name,
    this.code,
  });

  factory BranchInfo.fromJson(Map<String, dynamic> json) {
    return BranchInfo(
      id: readInt(json['id']),
      name: readString(json['name']),
      code: readStringOrNull(json['code']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'code': code,
    };
  }
}
