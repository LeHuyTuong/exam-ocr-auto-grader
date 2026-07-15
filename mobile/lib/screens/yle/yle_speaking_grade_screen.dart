import 'package:flutter/material.dart';
import '../../models/yle_models.dart';
import '../../services/yle_service.dart';
import '../../utils/error_utils.dart';
import 'yle_result_screen.dart';

/// Speaking has no pages to scan — just pick the student and enter one
/// holistic score (0–5) the examiner already decided during the oral test.
class YleSpeakingGradeScreen extends StatefulWidget {
  final YleExam exam;
  final int classId;
  final String className;

  const YleSpeakingGradeScreen({
    super.key,
    required this.exam,
    required this.classId,
    required this.className,
  });

  @override
  State<YleSpeakingGradeScreen> createState() => _YleSpeakingGradeScreenState();
}

class _YleSpeakingGradeScreenState extends State<YleSpeakingGradeScreen> {
  final _service = YleService();
  final _newNameCtrl = TextEditingController();

  bool _loading = true;
  bool _saving = false;
  String? _error;

  int? _submissionId;
  List<Map<String, dynamic>> _students = [];
  int? _selectedStudentId;
  bool _addingNew = false;
  int _score = 0;

  int get _maxMarks => widget.exam.parts.first.maxMarks;
  int get _partId => widget.exam.parts.first.id;

  @override
  void initState() {
    super.initState();
    _init();
  }

  @override
  void dispose() {
    _newNameCtrl.dispose();
    super.dispose();
  }

  Future<void> _init() async {
    try {
      final today = DateTime.now().toIso8601String().split('T').first;
      final results = await Future.wait([
        _service.createSubmission(
          yleExamId: widget.exam.id,
          classId: widget.classId,
          examDate: today,
        ),
        _service.getClassStudents(widget.classId),
      ]);
      final submission = results[0] as YleSubmission;
      _submissionId = submission.id;
      _students = results[1] as List<Map<String, dynamic>>;
      if (mounted) setState(() => _loading = false);
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = friendlyError(e);
        });
      }
    }
  }

  Future<void> _save() async {
    if (_submissionId == null) return;
    if (!_addingNew && _selectedStudentId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Chọn học sinh trước khi lưu.')),
      );
      return;
    }
    if (_addingNew && _newNameCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Nhập tên học sinh mới.')),
      );
      return;
    }

    setState(() => _saving = true);
    try {
      await _service.updateStudent(
        submissionId: _submissionId!,
        studentId: _addingNew ? null : _selectedStudentId,
        createNewStudent: _addingNew,
        newStudentName: _addingNew ? _newNameCtrl.text.trim() : null,
      );

      await _service.addManualMarks(
        submissionId: _submissionId!,
        marks: [
          {'part_id': _partId, 'marks': _score},
        ],
      );

      if (mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => YleResultScreen(
              submissionId: _submissionId!,
              exam: widget.exam,
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() => _saving = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(friendlyError(e))),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text('${widget.exam.name} — ${widget.className}'),
        centerTitle: true,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(_error!, textAlign: TextAlign.center),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: () {
                            setState(() => _loading = true);
                            _init();
                          },
                          child: const Text('Thử lại'),
                        ),
                      ],
                    ),
                  ),
                )
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    Text(
                      'Chọn học sinh',
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 8),
                    if (_students.isEmpty && !_addingNew)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 8),
                        child: Text(
                          'Lớp chưa có học sinh nào. Thêm học sinh mới bên dưới.',
                          style: TextStyle(color: Colors.grey),
                        ),
                      ),
                    RadioGroup<int>(
                      groupValue: _addingNew ? null : _selectedStudentId,
                      onChanged: (v) => setState(() {
                        _selectedStudentId = v;
                        _addingNew = false;
                      }),
                      child: Column(
                        children: _students
                            .map((s) => RadioListTile<int>(
                                  value: s['id'] as int,
                                  title: Text(s['fullName'] as String),
                                ))
                            .toList(),
                      ),
                    ),
                    CheckboxListTile(
                      value: _addingNew,
                      title: const Text('+ Học sinh mới'),
                      controlAffinity: ListTileControlAffinity.leading,
                      onChanged: (v) => setState(() {
                        _addingNew = v ?? false;
                        if (_addingNew) _selectedStudentId = null;
                      }),
                    ),
                    if (_addingNew)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                        child: TextField(
                          controller: _newNameCtrl,
                          decoration: const InputDecoration(
                            labelText: 'Tên học sinh mới',
                            isDense: true,
                          ),
                        ),
                      ),
                    const SizedBox(height: 24),
                    Text(
                      'Điểm Speaking (giám khảo đã chấm trực tiếp)',
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        IconButton(
                          icon: const Icon(Icons.remove_circle_outline, size: 32),
                          onPressed: _score > 0
                              ? () => setState(() => _score--)
                              : null,
                        ),
                        SizedBox(
                          width: 60,
                          child: Text(
                            '$_score / $_maxMarks',
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              fontSize: 28,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.add_circle_outline, size: 32),
                          onPressed: _score < _maxMarks
                              ? () => setState(() => _score++)
                              : null,
                        ),
                      ],
                    ),
                    Slider(
                      value: _score.toDouble(),
                      min: 0,
                      max: _maxMarks.toDouble(),
                      divisions: _maxMarks,
                      label: '$_score',
                      onChanged: (v) => setState(() => _score = v.round()),
                    ),
                    const SizedBox(height: 16),
                    ElevatedButton.icon(
                      onPressed: _saving ? null : _save,
                      icon: _saving
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.check),
                      label: Text(_saving ? 'Đang lưu...' : 'Lưu & Xem kết quả'),
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 16),
                      ),
                    ),
                  ],
                ),
    );
  }
}
