class YleExam {
  final int id;
  final String level;
  final String skill;
  final String name;
  final int totalMarks;
  final int totalPages;
  final String? createdBy;
  final String? createdAt;
  final List<YlePart> parts;

  YleExam({
    required this.id,
    required this.level,
    required this.skill,
    required this.name,
    required this.totalMarks,
    required this.totalPages,
    this.createdBy,
    this.createdAt,
    required this.parts,
  });

  factory YleExam.fromJson(Map<String, dynamic> json) => YleExam(
    id: json['id'] as int,
    level: json['level'] as String,
    skill: json['skill'] as String,
    name: json['name'] as String,
    totalMarks: json['totalMarks'] as int? ?? 0,
    totalPages: json['totalPages'] as int? ?? 0,
    createdBy: json['createdBy'] as String?,
    createdAt: json['createdAt'] as String?,
    parts: (json['parts'] as List?)
        ?.map((p) => YlePart.fromJson(p as Map<String, dynamic>))
        .toList() ?? [],
  );
}

class YlePart {
  final int id;
  final int partNumber;
  final String title;
  final String questionType;
  final bool isAutoGradable;
  final int maxMarks;
  final int pageNumber;
  final List<YleQuestion> questions;

  YlePart({
    required this.id,
    required this.partNumber,
    required this.title,
    required this.questionType,
    required this.isAutoGradable,
    required this.maxMarks,
    required this.pageNumber,
    required this.questions,
  });

  factory YlePart.fromJson(Map<String, dynamic> json) => YlePart(
    id: json['id'] as int,
    partNumber: json['partNumber'] as int? ?? 0,
    title: json['title'] as String? ?? '',
    questionType: json['questionType'] as String? ?? '',
    isAutoGradable: json['isAutoGradable'] as bool? ?? false,
    maxMarks: json['maxMarks'] as int? ?? 0,
    pageNumber: json['pageNumber'] as int? ?? 0,
    questions: (json['questions'] as List?)
        ?.map((q) => YleQuestion.fromJson(q as Map<String, dynamic>))
        .toList() ?? [],
  );
}

class YleQuestion {
  final int id;
  final int questionNumber;
  final String? prompt;
  final String? correctAnswer;
  final List<String> acceptedVariants;
  final int points;

  YleQuestion({
    required this.id,
    required this.questionNumber,
    this.prompt,
    this.correctAnswer,
    required this.acceptedVariants,
    required this.points,
  });

  factory YleQuestion.fromJson(Map<String, dynamic> json) => YleQuestion(
    id: json['id'] as int,
    questionNumber: json['questionNumber'] as int? ?? 0,
    prompt: json['prompt'] as String?,
    correctAnswer: json['correctAnswer'] as String?,
    acceptedVariants: (json['acceptedVariants'] as List?)
        ?.map((v) => v as String)
        .toList() ?? [],
    points: json['points'] as int? ?? 1,
  );
}

class YleSubmission {
  final int id;
  final int yleExamId;
  final int classId;
  final int? studentId;
  final String? studentName;
  final String? examDate;
  final String? ocrRawName;
  final double? autoScore;
  final double? manualScore;
  final double? totalScore;
  final double? maxScore;
  final String status;
  final String? createdAt;
  final int pagesCount;

  YleSubmission({
    required this.id,
    required this.yleExamId,
    required this.classId,
    this.studentId,
    this.studentName,
    this.examDate,
    this.ocrRawName,
    this.autoScore,
    this.manualScore,
    this.totalScore,
    this.maxScore,
    required this.status,
    this.createdAt,
    this.pagesCount = 0,
  });

  factory YleSubmission.fromJson(Map<String, dynamic> json) => YleSubmission(
    id: json['id'] as int,
    yleExamId: json['yleExamId'] as int? ?? 0,
    classId: json['classId'] as int? ?? 0,
    studentId: json['studentId'] as int?,
    studentName: json['studentName'] as String?,
    examDate: json['examDate'] as String?,
    ocrRawName: json['ocrRawName'] as String?,
    autoScore: (json['autoScore'] as num?)?.toDouble(),
    manualScore: (json['manualScore'] as num?)?.toDouble(),
    totalScore: (json['totalScore'] as num?)?.toDouble(),
    maxScore: (json['maxScore'] as num?)?.toDouble(),
    status: json['status'] as String? ?? 'pending',
    createdAt: json['createdAt'] as String?,
    pagesCount: json['pagesCount'] as int? ?? 0,
  );
}

class YleAnswerItem {
  final int partNumber;
  final int questionNumber;
  final String value;
  final bool? isCorrect;
  final double confidence;

  YleAnswerItem({
    required this.partNumber,
    required this.questionNumber,
    required this.value,
    this.isCorrect,
    required this.confidence,
  });

  factory YleAnswerItem.fromJson(Map<String, dynamic> json) => YleAnswerItem(
    partNumber: json['partNumber'] as int? ?? 0,
    questionNumber: json['questionNumber'] as int? ?? 0,
    value: json['value'] as String? ?? '',
    isCorrect: json['isCorrect'] as bool?,
    confidence: (json['confidence'] as num?)?.toDouble() ?? 0.0,
  );
}

class YleBreakdownPart {
  final int partId;
  final int partNumber;
  final int pageNumber;
  final String title;
  final bool isAutoGradable;
  final int score;
  final int maxMarks;
  final List<YleBreakdownQuestion> questions;

  YleBreakdownPart({
    required this.partId,
    required this.partNumber,
    required this.pageNumber,
    required this.title,
    required this.isAutoGradable,
    required this.score,
    required this.maxMarks,
    required this.questions,
  });

  factory YleBreakdownPart.fromJson(Map<String, dynamic> json) => YleBreakdownPart(
    partId: json['partId'] as int? ?? 0,
    partNumber: json['partNumber'] as int? ?? 0,
    pageNumber: json['pageNumber'] as int? ?? 0,
    title: json['title'] as String? ?? '',
    isAutoGradable: json['isAutoGradable'] as bool? ?? false,
    score: json['score'] as int? ?? 0,
    maxMarks: json['maxMarks'] as int? ?? 0,
    questions: (json['questions'] as List?)
        ?.map((q) => YleBreakdownQuestion.fromJson(q as Map<String, dynamic>))
        .toList() ?? [],
  );
}

class YleBreakdownQuestion {
  final int questionNumber;
  final String? studentAnswer;
  final String? correctAnswer;
  final bool? isCorrect;
  final int marksAwarded;
  final String gradedBy;
  final double? aiConfidence;

  YleBreakdownQuestion({
    required this.questionNumber,
    this.studentAnswer,
    this.correctAnswer,
    this.isCorrect,
    required this.marksAwarded,
    required this.gradedBy,
    this.aiConfidence,
  });

  factory YleBreakdownQuestion.fromJson(Map<String, dynamic> json) => YleBreakdownQuestion(
    questionNumber: json['questionNumber'] as int? ?? 0,
    studentAnswer: json['studentAnswer'] as String?,
    correctAnswer: json['correctAnswer'] as String?,
    isCorrect: json['isCorrect'] as bool?,
    marksAwarded: json['marksAwarded'] as int? ?? 0,
    gradedBy: json['gradedBy'] as String? ?? 'pending',
    aiConfidence: (json['aiConfidence'] as num?)?.toDouble(),
  );
}

class YlePageInfo {
  final int id;
  final int pageNumber;
  final String imageUrl;

  YlePageInfo({
    required this.id,
    required this.pageNumber,
    required this.imageUrl,
  });

  factory YlePageInfo.fromJson(Map<String, dynamic> json) => YlePageInfo(
    id: json['id'] as int? ?? 0,
    pageNumber: json['pageNumber'] as int? ?? 0,
    imageUrl: json['imageUrl'] as String? ?? '',
  );
}

class YlePageResult {
  final YleSubmission submission;
  final List<YleBreakdownPart> breakdown;
  final List<YlePageInfo> pages;

  YlePageResult({
    required this.submission,
    required this.breakdown,
    required this.pages,
  });

  factory YlePageResult.fromJson(Map<String, dynamic> json) => YlePageResult(
    submission: YleSubmission.fromJson(json['submission'] as Map<String, dynamic>),
    breakdown: (json['breakdown'] as List?)
        ?.map((b) => YleBreakdownPart.fromJson(b as Map<String, dynamic>))
        .toList() ?? [],
    pages: (json['pages'] as List?)
        ?.map((p) => YlePageInfo.fromJson(p as Map<String, dynamic>))
        .toList() ?? [],
  );
}
