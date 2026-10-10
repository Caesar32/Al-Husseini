class AuthUser {
  final int id;
  final String name;
  final String email;
  final String phone;
  final String role;
  final String? avatarUrl;
  final BranchInfo? branch;

  AuthUser({
    required this.id,
    required this.name,
    required this.email,
    required this.phone,
    required this.role,
    this.avatarUrl,
    this.branch,
  });

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    return AuthUser(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      name: json['name'] ?? '',
      email: json['email'] ?? '',
      phone: json['phone'] ?? '',
      role: json['role'] ?? 'owner',
      avatarUrl: json['avatar_url'],
      branch: json['branch'] != null ? BranchInfo.fromJson(json['branch']) : null,
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

  BranchInfo({
    required this.id,
    required this.name,
    this.code,
  });

  factory BranchInfo.fromJson(Map<String, dynamic> json) {
    return BranchInfo(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      name: json['name'] ?? '',
      code: json['code'],
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
