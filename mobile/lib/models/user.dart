class User {
  final int id;
  final String name;
  final String email;
  final List<String> roles;
  final List<String> permissions;

  User({
    required this.id,
    required this.name,
    required this.email,
    required this.roles,
    required this.permissions,
  });

  bool get isAdmin => roles.contains('admin');
  bool get isTeacher => roles.contains('teacher');

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      roles: (json['roles'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          [],
      permissions: (json['permissions'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          [],
    );
  }
}
