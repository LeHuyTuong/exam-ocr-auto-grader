class Candidate {
  final int studentId;
  final String fullName;
  final double similarity;

  Candidate({
    required this.studentId,
    required this.fullName,
    required this.similarity,
  });

  factory Candidate.fromJson(Map<String, dynamic> json) {
    return Candidate(
      studentId: json['studentId'] as int? ?? 0,
      fullName: json['fullName'] as String? ?? '',
      similarity: (json['similarity'] as num?)?.toDouble() ?? 0.0,
    );
  }
}

class ExtractResult {
  final List<Candidate> candidates;
  final int totalCorrect;
  final double score;
  final String ocrRawName;
  final double aiConfidence;
  final String imageUrl;
  final int examId;

  ExtractResult({
    required this.candidates,
    required this.totalCorrect,
    required this.score,
    required this.ocrRawName,
    required this.aiConfidence,
    required this.imageUrl,
    required this.examId,
  });

  factory ExtractResult.fromJson(Map<String, dynamic> json) {
    return ExtractResult(
      candidates: (json['candidates'] as List?)
              ?.map((c) => Candidate.fromJson(c as Map<String, dynamic>))
              .toList() ??
          [],
      totalCorrect: json['totalCorrect'] as int? ?? 0,
      score: (json['score'] as num?)?.toDouble() ?? 0.0,
      ocrRawName: json['ocrRawName'] as String? ?? '',
      aiConfidence: (json['aiConfidence'] as num?)?.toDouble() ?? 0.0,
      imageUrl: json['imageUrl'] as String? ?? '',
      examId: json['examId'] as int? ?? 0,
    );
  }
}
