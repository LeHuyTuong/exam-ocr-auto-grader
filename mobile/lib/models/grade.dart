class Grade {
  final int id;
  final String studentName;
  final int totalCorrect;
  final double score;
  final String status;
  final String? createdAt;

  Grade({
    required this.id,
    required this.studentName,
    required this.totalCorrect,
    required this.score,
    required this.status,
    this.createdAt,
  });

  factory Grade.fromJson(Map<String, dynamic> json) {
    return Grade(
      id: json['id'] as int? ?? 0,
      studentName: json['studentName'] as String? ?? '',
      totalCorrect: json['totalCorrect'] as int? ?? 0,
      score: (json['score'] as num?)?.toDouble() ?? 0.0,
      status: json['status'] as String? ?? '',
      createdAt:
          json['createdAt'] as String? ?? json['created_at'] as String?,
    );
  }
}
