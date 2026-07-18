import '../models/extract_result.dart';
import '../models/graded_paper_result.dart';
import 'api_client.dart';

class ExamService {
  final ApiClient _api = ApiClient();

  Future<Map<String, dynamic>> getClasses() async {
    final res = await _api.get('/classes/mine');
    return res.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> createClass(
      String code, String name, String level) async {
    final res = await _api.post('/classes', data: {
      'code': code,
      'name': name,
      'level': level,
    });
    return res.data as Map<String, dynamic>;
  }

  /// Mỗi lớp chỉ có đúng 1 exam (config chấm bài), không còn phân biệt theo
  /// ngày — endpoint backend vẫn tên "/exams/today" nhưng nay trả về exam
  /// duy nhất của lớp, dùng chung cho mọi lần chấm.
  Future<Map<String, dynamic>?> getClassExam(int classId) async {
    try {
      final res = await _api.get('/exams/today', params: {'class_id': classId});
      return res.data as Map<String, dynamic>?;
    } catch (_) {
      return null;
    }
  }

  Future<Map<String, dynamic>> createClassExam(
      int classId, int totalQuestions, int? maxScore,
      {String gradingMode = 'counting'}) async {
    final res = await _api.post('/exams/today', data: {
      'class_id': classId,
      'total_questions': totalQuestions,
      'max_score': maxScore ?? totalQuestions,
      'grading_mode': gradingMode,
    });
    return res.data as Map<String, dynamic>;
  }

  Future<ExtractResult> extractImage(
      String imagePath, int classId, String? mlkitHint) async {
    final fields = <String, dynamic>{
      'class_id': classId.toString(),
    };
    if (mlkitHint != null) {
      fields['mlkit_hint'] = mlkitHint;
    }
    final res =
        await _api.uploadImage('/ocr/extract', imagePath, fields: fields);
    return ExtractResult.fromJson(res.data as Map<String, dynamic>);
  }

  Future<void> saveGrade({
    required int examId,
    required int classId,
    required int totalCorrect,
    required double score,
    required String ocrRawName,
    required String imageUrl,
    double? aiConfidence,
    int? studentId,
    bool? createNewStudent,
    String? newStudentName,
    String? imageUrl2,
    Map<String, int>? subScores,
  }) async {
    final data = <String, dynamic>{
      'exam_id': examId,
      'class_id': classId,
      'total_correct': totalCorrect,
      'score': score,
      'ocr_raw_name': ocrRawName,
      'image_url': imageUrl,
      'ai_confidence': aiConfidence,
      'student_id': studentId,
    };
    if (createNewStudent == true) {
      data['create_new_student'] = true;
    }
    if (newStudentName != null) {
      data['new_student_name'] = newStudentName;
    }
    if (imageUrl2 != null) {
      data['image_url_2'] = imageUrl2;
    }
    if (subScores != null) {
      data['sub_scores'] = subScores;
    }
    await _api.post('/grades', data: data);
  }

  /// Task 1/2 of the graded-paper flow — a tight crop of the pencil-written
  /// name line. Returns the exam id to reuse for the scores crop.
  Future<GradedNameResult> extractGradedName(
      String imagePath, int classId, String? mlkitHint) async {
    final fields = <String, dynamic>{
      'class_id': classId.toString(),
      'mode': 'name',
    };
    if (mlkitHint != null) {
      fields['mlkit_hint'] = mlkitHint;
    }
    final res = await _api.uploadImage('/ocr/extract-graded', imagePath,
        fields: fields);
    return GradedNameResult.fromJson(res.data as Map<String, dynamic>);
  }

  /// Task 2/2 — a tight crop of the red-ink score strip at the end of the paper.
  Future<GradedScoresResult> extractGradedScores(
      String imagePath, int classId, String? mlkitHint) async {
    final fields = <String, dynamic>{
      'class_id': classId.toString(),
      'mode': 'scores',
    };
    if (mlkitHint != null) {
      fields['mlkit_hint'] = mlkitHint;
    }
    final res = await _api.uploadImage('/ocr/extract-graded', imagePath,
        fields: fields);
    return GradedScoresResult.fromJson(res.data as Map<String, dynamic>);
  }

  /// Raw .xlsx bytes for the class/exam grade sheet, ready to share or save.
  Future<List<int>> downloadExcel(int examId) =>
      _api.getBytes('/exams/$examId/export');

  Future<Map<String, dynamic>> getGrades(int examId,
      {int? classId, int? studentId, int page = 1, int perPage = 15}) async {
    final params = <String, dynamic>{
      'exam_id': examId,
      'page': page,
      'per_page': perPage,
    };
    if (classId != null) params['class_id'] = classId;
    if (studentId != null) params['student_id'] = studentId;
    final res = await _api.get('/grades', params: params);
    return res.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getDashboardClassStats(int classId) async {
    final res = await _api.get('/dashboard/class/$classId');
    return res.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getDashboardStudentStats(int classId,
      {int page = 1, int perPage = 20}) async {
    final res = await _api.get('/dashboard/class/$classId/students',
        params: {'page': page, 'per_page': perPage});
    return res.data as Map<String, dynamic>;
  }
}
