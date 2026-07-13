import '../models/yle_models.dart';
import 'api_client.dart';

class YleService {
  final ApiClient _api = ApiClient();

  Future<List<YleExam>> getExams({String? level, String? skill}) async {
    final params = <String, dynamic>{};
    if (level != null) params['level'] = level;
    if (skill != null) params['skill'] = skill;

    final res = await _api.get('/yle/exams', params: params);
    final data = res.data as Map<String, dynamic>;
    return (data['exams'] as List)
        .map((e) => YleExam.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<YleExam> createExam(String level, String skill, {String? name}) async {
    final res = await _api.post('/yle/exams', data: {
      'level': level,
      'skill': skill,
      if (name != null) 'name': name,
    });
    final data = res.data as Map<String, dynamic>;
    return YleExam.fromJson(data['exam'] as Map<String, dynamic>);
  }

  Future<void> updateQuestion(int questionId, {
    String? correctAnswer,
    List<String>? acceptedVariants,
  }) async {
    await _api.put('/yle/questions/$questionId', data: {
      if (correctAnswer != null) 'correct_answer': correctAnswer,
      if (acceptedVariants != null) 'accepted_variants': acceptedVariants,
    });
  }

  Future<YleSubmission> createSubmission({
    required int yleExamId,
    required int classId,
    required String examDate,
  }) async {
    final res = await _api.post('/yle/submissions', data: {
      'yle_exam_id': yleExamId,
      'class_id': classId,
      'exam_date': examDate,
    });
    final data = res.data as Map<String, dynamic>;
    return YleSubmission.fromJson(data['submission'] as Map<String, dynamic>);
  }

  Future<Map<String, dynamic>> uploadPage({
    required int submissionId,
    required String imagePath,
    required int pageNumber,
    String? mlkitHint,
  }) async {
    final fields = <String, dynamic>{
      'page_number': pageNumber.toString(),
    };
    if (mlkitHint != null) {
      fields['mlkit_hint'] = mlkitHint;
    }
    final res = await _api.uploadImage(
      '/yle/submissions/$submissionId/pages',
      imagePath,
      fields: fields,
    );
    return res.data as Map<String, dynamic>;
  }

  Future<YleSubmission> updateStudent({
    required int submissionId,
    int? studentId,
    bool? createNewStudent,
    String? newStudentName,
  }) async {
    final data = <String, dynamic>{};
    if (studentId != null) data['student_id'] = studentId;
    if (createNewStudent == true) {
      data['create_new_student'] = true;
      data['new_student_name'] = newStudentName;
    }
    final res = await _api.put('/yle/submissions/$submissionId/student', data: data);
    final result = res.data as Map<String, dynamic>;
    return YleSubmission.fromJson(result['submission'] as Map<String, dynamic>);
  }

  Future<YleSubmission> addManualMarks({
    required int submissionId,
    required List<Map<String, dynamic>> marks,
  }) async {
    final res = await _api.post('/yle/submissions/$submissionId/manual', data: {
      'marks': marks,
    });
    final data = res.data as Map<String, dynamic>;
    return YleSubmission.fromJson(data['submission'] as Map<String, dynamic>);
  }

  Future<YlePageResult> getSubmissionResult(int submissionId) async {
    final res = await _api.get('/yle/submissions/$submissionId');
    final data = res.data as Map<String, dynamic>;
    return YlePageResult.fromJson(data);
  }

  Future<List<YleSubmission>> getSubmissions({
    int? yleExamId,
    int? classId,
    int? studentId,
    String? status,
    int page = 1,
    int perPage = 15,
  }) async {
    final params = <String, dynamic>{
      'page': page,
      'per_page': perPage,
    };
    if (yleExamId != null) params['yle_exam_id'] = yleExamId;
    if (classId != null) params['class_id'] = classId;
    if (studentId != null) params['student_id'] = studentId;
    if (status != null) params['status'] = status;

    final res = await _api.get('/yle/submissions', params: params);
    final data = res.data as Map<String, dynamic>;
    return (data['submissions'] as List)
        .map((s) => YleSubmission.fromJson(s as Map<String, dynamic>))
        .toList();
  }
}
