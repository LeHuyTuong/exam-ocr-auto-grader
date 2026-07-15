import 'extract_result.dart';

/// Result of `mode=name` — the tight crop of the pencil-written name line.
class GradedNameResult {
  final List<Candidate> candidates;
  final String ocrRawName;
  final double aiConfidence;
  final String? imageUrl;
  final int examId;

  GradedNameResult({
    required this.candidates,
    required this.ocrRawName,
    required this.aiConfidence,
    required this.imageUrl,
    required this.examId,
  });

  factory GradedNameResult.fromJson(Map<String, dynamic> json) {
    return GradedNameResult(
      candidates: (json['candidates'] as List?)
              ?.map((c) => Candidate.fromJson(c as Map<String, dynamic>))
              .toList() ??
          [],
      ocrRawName: json['ocrRawName'] as String? ?? '',
      aiConfidence: (json['aiConfidence'] as num?)?.toDouble() ?? 0.0,
      imageUrl: json['imageUrl'] as String?,
      examId: json['examId'] as int? ?? 0,
    );
  }
}

/// Result of `mode=scores` — the tight crop of the red-ink score strip.
class GradedScoresResult {
  final Map<String, int> subScores;
  final int? totalScore;
  final bool sumMismatch;
  final double aiConfidence;
  final String? imageUrl;
  final int examId;

  GradedScoresResult({
    required this.subScores,
    required this.totalScore,
    required this.sumMismatch,
    required this.aiConfidence,
    required this.imageUrl,
    required this.examId,
  });

  factory GradedScoresResult.fromJson(Map<String, dynamic> json) {
    final raw = json['subScores'] as Map<String, dynamic>? ?? {};
    return GradedScoresResult(
      subScores: raw.map((k, v) => MapEntry(k, (v as num?)?.toInt() ?? 0)),
      totalScore: json['totalScore'] as int?,
      sumMismatch: json['sumMismatch'] as bool? ?? false,
      aiConfidence: (json['aiConfidence'] as num?)?.toDouble() ?? 0.0,
      imageUrl: json['imageUrl'] as String?,
      examId: json['examId'] as int? ?? 0,
    );
  }
}
