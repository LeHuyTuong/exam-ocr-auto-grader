class SchoolClass {
  final int id;
  final String code;
  final String name;
  final String level;

  SchoolClass({
    required this.id,
    required this.code,
    required this.name,
    required this.level,
  });

  factory SchoolClass.fromJson(Map<String, dynamic> json) {
    return SchoolClass(
      id: json['id'] as int? ?? 0,
      code: json['code'] as String? ?? '',
      name: json['name'] as String? ?? '',
      level: json['level'] as String? ?? '',
    );
  }
}
