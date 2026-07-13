import '../models/extract_result.dart';
import 'api_client.dart';

class ExamService {
  final ApiClient _api = ApiClient();

  Future<Map<String, dynamic>> getClasses() async {
    final res = await _api.get('/classes/mine');
    return res.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>?> getTodayExam(int classId) async {
    try {
      final res =
          await _api.get('/exams/today', params: {'class_id': classId});
      return res.data as Map<String, dynamic>?;
    } catch (_) {
      return null;
    }
  }

  Future<Map<String, dynamic>> createTodayExam(
      int classId, int totalQuestions, int? maxScore) async {
    final res = await _api.post('/exams/today', data: {
      'class_id': classId,
      'total_questions': totalQuestions,
      'max_score': maxScore ?? totalQuestions,
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
    final res = await _api.uploadImage('/ocr/extract', imagePath, fields: fields);
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
    await _api.post('/grades', data: data);
  }

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
