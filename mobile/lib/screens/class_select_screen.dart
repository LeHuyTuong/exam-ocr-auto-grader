import 'package:flutter/material.dart';
import '../models/school_class.dart';
import '../services/exam_service.dart';
import '../utils/error_utils.dart';
import '../widgets/app_card.dart';
import '../widgets/empty_state.dart';
import '../widgets/error_view.dart';
import '../widgets/loading_view.dart';
import 'graded_scan_screen.dart';
import 'grade_list_screen.dart';
import 'scan_screen.dart';

class ClassSelectScreen extends StatefulWidget {
  const ClassSelectScreen({super.key});

  @override
  State<ClassSelectScreen> createState() => _ClassSelectScreenState();
}

class _ClassSelectScreenState extends State<ClassSelectScreen> {
  final _service = ExamService();
  List<SchoolClass> _classes = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadClasses();
  }

  Future<void> _loadClasses() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await _service.getClasses();
      if (mounted) {
        setState(() {
          _classes = (data['classes'] as List?)
                  ?.map((c) => SchoolClass.fromJson(c as Map<String, dynamic>))
                  .toList() ??
              [];
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Không thể tải danh sách lớp. Vui lòng thử lại.';
        });
      }
    }
  }

  Future<void> _selectClass(SchoolClass cls) async {
    try {
      final exams = await _service.getClassExams(cls.id);
      if (!mounted) return;

      if (exams.isEmpty) {
        // Lớp chưa có đề nào — mở dialog tạo đề đầu tiên rồi vào màn quét.
        await _createFirstExamAndScan(cls);
        return;
      }

      // Có ít nhất 1 đề — mở màn chọn đề (active → quét, khoá → xem/xuất).
      if (!mounted) return;
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => _ExamListScreen(cls: cls)),
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(friendlyError(e))),
        );
      }
    }
  }

  /// Lớp chưa có đề: mở dialog → tạo đề đầu tiên → vào màn quét ngay.
  Future<void> _createFirstExamAndScan(SchoolClass cls) async {
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (ctx) => _ExamDialog(classCode: cls.code, showNameField: true),
    );
    if (result == null || !mounted) return;

    final totalQuestions = result['totalQuestions'] as int? ?? 50;
    final maxScore = result['maxScore'] as int?;
    final gradingMode = result['gradingMode'] as String? ?? 'counting';
    final name = result['name'] as String?;

    final data = await _service.createExam(
      cls.id,
      name: name,
      totalQuestions: totalQuestions,
      maxScore: maxScore,
      gradingMode: gradingMode,
    );
    if (!mounted) return;

    final examData = data['exam'] as Map<String, dynamic>?;
    final examId = examData?['id'] as int? ?? 0;
    _pushScanScreen(cls, examId, totalQuestions, maxScore ?? totalQuestions,
        gradingMode);
  }

  /// Vào màn quét theo gradingMode của đề đã chọn/tạo.
  void _pushScanScreen(SchoolClass cls, int examId, int totalQuestions,
      int maxScore, String gradingMode) {
    final className = '${cls.code} - ${cls.name}';
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => gradingMode == 'graded'
            ? GradedScanScreen(
                classId: cls.id, examId: examId, className: className)
            : ScanScreen(
                classId: cls.id,
                examId: examId,
                className: className,
                totalQuestions: totalQuestions,
                maxScore: maxScore,
              ),
      ),
    );
  }

  /// Đổi kiểu chấm cho lớp đã có bài — mở lại hộp thoại (điền sẵn cấu hình
  /// hiện tại) rồi cập nhật exam. Giáo viên lỡ chọn nhầm không còn bị kẹt.
  Future<void> _changeGradingMode(SchoolClass cls) async {
    try {
      final exam = await _service.getClassExam(cls.id);
      final examData = exam?['exam'] as Map<String, dynamic>?;
      if (!mounted) return;

      final result = await showDialog<Map<String, dynamic>>(
        context: context,
        builder: (ctx) => _ExamDialog(
          classCode: cls.code,
          initialMode: examData?.tryGet('gradingMode') as String?,
          initialQuestions: examData?.tryGet('totalQuestions') as int?,
          initialMaxScore: examData?.tryGet('maxScore') as int?,
          confirmLabel: 'Lưu',
        ),
      );
      if (result == null) return;

      await _service.createClassExam(
        cls.id,
        result['totalQuestions'] as int? ?? 50,
        result['maxScore'] as int?,
        gradingMode: result['gradingMode'] as String? ?? 'counting',
      );
      if (!mounted) return;

      final modeLabel = (result['gradingMode'] as String?) == 'graded'
          ? 'Unit Test đã chấm tay'
          : 'Đếm câu đúng';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Đã đổi kiểu chấm: $modeLabel')),
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(friendlyError(e))),
        );
      }
    }
  }

  Future<void> _createClass() async {
    final result = await showDialog<Map<String, String>>(
      context: context,
      builder: (_) => const _CreateClassDialog(),
    );
    if (result == null) return;

    try {
      await _service.createClass(
        result['code']!,
        result['name']!,
        result['level']!,
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Đã tạo lớp ${result['code']}')),
        );
      }
      _loadClasses();
    } catch (e) {
      if (mounted) {
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
        title: const Text('Chọn lớp'),
        centerTitle: true,
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _createClass,
        icon: const Icon(Icons.add),
        label: const Text('Tạo lớp'),
      ),
      body: _loading
          ? const LoadingView(message: 'Đang tải danh sách lớp...')
          : _error != null
              ? ErrorView(
                  message: _error!,
                  onRetry: _loadClasses,
                )
              : _classes.isEmpty
                  ? const EmptyState(
                      icon: Icons.class_outlined,
                      message:
                          'Chưa có lớp nào.\nHãy liên hệ admin để được phân công.',
                    )
                  : ListView.builder(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      itemCount: _classes.length,
                      itemBuilder: (context, i) {
                        final cls = _classes[i];
                        return AppCard(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 16, vertical: 12),
                          onTap: () => _selectClass(cls),
                          child: Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: theme.colorScheme.primaryContainer,
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Icon(
                                  Icons.class_,
                                  color: theme.colorScheme.primary,
                                ),
                              ),
                              const SizedBox(width: 16),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      '${cls.code} - ${cls.name}',
                                      style: theme.textTheme.titleSmall
                                          ?.copyWith(
                                              fontWeight: FontWeight.w600),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      'Cấp: ${cls.level}',
                                      style:
                                          theme.textTheme.bodySmall?.copyWith(
                                        color:
                                            theme.colorScheme.onSurfaceVariant,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              PopupMenuButton<String>(
                                icon: Icon(
                                  Icons.more_vert,
                                  size: 20,
                                  color: theme.colorScheme.onSurfaceVariant,
                                ),
                                tooltip: 'Tuỳ chọn',
                                onSelected: (v) {
                                  if (v == 'change_mode') {
                                    _changeGradingMode(cls);
                                  }
                                },
                                itemBuilder: (_) => const [
                                  PopupMenuItem(
                                    value: 'change_mode',
                                    child: Row(
                                      children: [
                                        Icon(Icons.tune, size: 18),
                                        SizedBox(width: 8),
                                        Text('Đổi kiểu chấm'),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        );
                      },
                    ),
    );
  }
}

extension _MapTryGet on Map<String, dynamic> {
  dynamic tryGet(String key) => containsKey(key) ? this[key] : null;
}

/// Màn hình con (đẩy từ ClassSelectScreen khi lớp đã có đề): danh sách đề của 1
/// lớp — đề active bấm vào để chấm, đề khoá bấm vào để xem/xuất. Có nút tạo đề mới.
class _ExamListScreen extends StatefulWidget {
  final SchoolClass cls;
  const _ExamListScreen({required this.cls});

  @override
  State<_ExamListScreen> createState() => _ExamListScreenState();
}

class _ExamListScreenState extends State<_ExamListScreen> {
  final _service = ExamService();
  List<Map<String, dynamic>> _exams = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadExams();
  }

  Future<void> _loadExams() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final exams = await _service.getClassExams(widget.cls.id);
      if (mounted) setState(() { _exams = exams; _loading = false; });
    } catch (e) {
      if (mounted) {
        setState(() { _loading = false; _error = friendlyError(e); });
      }
    }
  }

  Future<void> _createNewExam() async {
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (ctx) =>
          _ExamDialog(classCode: widget.cls.code, showNameField: true),
    );
    if (result == null || !mounted) return;

    try {
      final data = await _service.createExam(
        widget.cls.id,
        name: result['name'] as String?,
        totalQuestions: result['totalQuestions'] as int? ?? 50,
        maxScore: result['maxScore'] as int?,
        gradingMode: result['gradingMode'] as String? ?? 'counting',
      );
      if (!mounted) return;
      // Tạo xong → vào màn quét luôn (đề mới là đề active).
      final examData = data['exam'] as Map<String, dynamic>?;
      final examId = examData?['id'] as int? ?? 0;
      final totalQuestions = (examData?.tryGet('totalQuestions') as int?) ??
          (result['totalQuestions'] as int? ?? 50);
      final maxScore = (examData?.tryGet('maxScore') as int?) ??
          (result['maxScore'] as int?) ?? totalQuestions;
      final gradingMode = (examData?.tryGet('gradingMode') as String?) ??
          (result['gradingMode'] as String? ?? 'counting');
      _pushScan(examId, totalQuestions, maxScore, gradingMode);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(friendlyError(e))),
        );
      }
    }
  }

  void _pushScan(int examId, int totalQuestions, int maxScore, String gradingMode) {
    final className = '${widget.cls.code} - ${widget.cls.name}';
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => gradingMode == 'graded'
            ? GradedScanScreen(
                classId: widget.cls.id, examId: examId, className: className)
            : ScanScreen(
                classId: widget.cls.id,
                examId: examId,
                className: className,
                totalQuestions: totalQuestions,
                maxScore: maxScore,
              ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text('${widget.cls.code} - Chọn đề'),
        centerTitle: true,
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _createNewExam,
        icon: const Icon(Icons.add),
        label: const Text('Tạo đề mới'),
      ),
      body: _loading
          ? const LoadingView(message: 'Đang tải danh sách đề...')
          : _error != null
              ? ErrorView(message: _error!, onRetry: _loadExams)
              : _exams.isEmpty
                  ? const EmptyState(
                      icon: Icons.assignment_outlined,
                      message: 'Chưa có đề nào.\nBấm "Tạo đề mới" để bắt đầu.',
                    )
                  : RefreshIndicator(
                      onRefresh: _loadExams,
                      child: ListView.builder(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 16, vertical: 12),
                        itemCount: _exams.length,
                        itemBuilder: (context, i) {
                          final e = _exams[i];
                          final isActive = e['isActive'] == true;
                          final examId = e['id'] as int? ?? 0;
                          final name = e['name'] as String? ?? 'Bài thi';
                          final gradingMode =
                              e['gradingMode'] as String? ?? 'counting';
                          final totalQuestions =
                              e['totalQuestions'] as int? ?? 50;
                          final maxScore =
                              e['maxScore'] as int? ?? totalQuestions;
                          return AppCard(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 16, vertical: 12),
                            onTap: () {
                              if (isActive) {
                                _pushScan(examId, totalQuestions, maxScore, gradingMode);
                              } else {
                                // Đề khoá → chỉ xem/xuất.
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => GradeListScreen(
                                      classId: widget.cls.id,
                                      examId: examId,
                                    ),
                                  ),
                                );
                              }
                            },
                            child: Row(
                              children: [
                                Container(
                                  padding: const EdgeInsets.all(10),
                                  decoration: BoxDecoration(
                                    color: isActive
                                        ? theme.colorScheme.primaryContainer
                                        : theme.colorScheme.surfaceContainerHighest,
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: Icon(
                                    isActive ? Icons.assignment_turned_in : Icons.lock_outline,
                                    color: isActive ? theme.colorScheme.primary : theme.colorScheme.onSurfaceVariant,
                                  ),
                                ),
                                const SizedBox(width: 16),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(name, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600)),
                                      const SizedBox(height: 4),
                                      Text(
                                        gradingMode == 'graded' ? 'Unit Test đã chấm tay' : 'Đếm câu đúng',
                                        style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                                      ),
                                    ],
                                  ),
                                ),
                                Chip(
                                  label: Text(
                                    isActive ? 'Đang chấm' : 'Đã khoá',
                                    style: TextStyle(fontSize: 11, color: isActive ? Colors.green : Colors.grey),
                                  ),
                                  visualDensity: VisualDensity.compact,
                                  padding: EdgeInsets.zero,
                                ),
                              ],
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}

class _ExamDialog extends StatefulWidget {
  final String classCode;
  final String? initialMode;
  final int? initialQuestions;
  final int? initialMaxScore;
  final String confirmLabel;
  final bool showNameField;

  const _ExamDialog({
    required this.classCode,
    this.initialMode,
    this.initialQuestions,
    this.initialMaxScore,
    this.confirmLabel = 'Bắt đầu',
    this.showNameField = false,
  });

  @override
  State<_ExamDialog> createState() => _ExamDialogState();
}

enum _GradingMode { counting, graded }

class _ExamDialogState extends State<_ExamDialog> {
  late final TextEditingController _nameCtrl;
  late final TextEditingController _questionsCtrl;
  late final TextEditingController _maxScoreCtrl;
  late _GradingMode _mode;

  @override
  void initState() {
    super.initState();
    _mode = widget.initialMode == 'graded'
        ? _GradingMode.graded
        : _GradingMode.counting;
    _nameCtrl = TextEditingController();
    _questionsCtrl =
        TextEditingController(text: (widget.initialQuestions ?? 50).toString());
    _maxScoreCtrl = TextEditingController(
        text:
            (widget.initialMaxScore ?? (_mode == _GradingMode.graded ? 50 : 10))
                .toString());
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _questionsCtrl.dispose();
    _maxScoreCtrl.dispose();
    super.dispose();
  }

  String? _nameResult() {
    final t = _nameCtrl.text.trim();
    return t.isEmpty ? null : t;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return AlertDialog(
      title: Text(
        widget.showNameField
            ? 'Tạo đề mới — ${widget.classCode}'
            : 'Chọn kiểu chấm — ${widget.classCode}',
        style: theme.textTheme.titleMedium,
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (widget.showNameField) ...[
              TextField(
                controller: _nameCtrl,
                decoration: const InputDecoration(
                  labelText: 'Tên đề (tuỳ chọn)',
                  hintText: 'Để trống = tự sinh "Bài thi {mã lớp} - {ngày}"',
                ),
              ),
              const SizedBox(height: 12),
            ],
            RadioGroup<_GradingMode>(
              groupValue: _mode,
              onChanged: (v) => setState(() {
                _mode = v ?? _GradingMode.counting;
                if (_mode == _GradingMode.graded &&
                    _maxScoreCtrl.text == '10') {
                  _maxScoreCtrl.text = '50';
                }
              }),
              child: const Column(
                children: [
                  RadioListTile<_GradingMode>(
                    value: _GradingMode.counting,
                    title: Text('Đếm câu đúng'),
                    subtitle: Text('Chụp 1 ảnh, AI đếm số câu đúng'),
                    contentPadding: EdgeInsets.zero,
                  ),
                  RadioListTile<_GradingMode>(
                    value: _GradingMode.graded,
                    title: Text('Unit Test đã chấm (2 ảnh)'),
                    subtitle: Text('Chụp tên + dải điểm bút đỏ'),
                    contentPadding: EdgeInsets.zero,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            if (_mode == _GradingMode.counting) ...[
              TextField(
                controller: _questionsCtrl,
                decoration: const InputDecoration(
                  labelText: 'Tổng số câu hỏi',
                  hintText: '50',
                ),
                keyboardType: TextInputType.number,
              ),
              const SizedBox(height: 12),
            ],
            TextField(
              controller: _maxScoreCtrl,
              decoration: InputDecoration(
                labelText: 'Thang điểm',
                hintText: _mode == _GradingMode.counting
                    ? 'Để trống = số câu hỏi'
                    : '50',
              ),
              keyboardType: TextInputType.number,
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Huỷ'),
        ),
        FilledButton(
          onPressed: () {
            if (_mode == _GradingMode.graded) {
              final maxScore = int.tryParse(_maxScoreCtrl.text) ?? 50;
              Navigator.pop(context, {
                // Cột total_questions không dùng ở luồng Unit Test nhưng là
                // NOT NULL trong DB — dùng lại thang điểm làm giá trị placeholder.
                'totalQuestions': maxScore,
                'maxScore': maxScore,
                'gradingMode': 'graded',
                'name': _nameResult(),
              });
              return;
            }

            final q = int.tryParse(_questionsCtrl.text);
            if (q == null || q <= 0) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Số câu hỏi phải lớn hơn 0')),
              );
              return;
            }
            Navigator.pop(context, {
              'totalQuestions': q,
              'maxScore': int.tryParse(_maxScoreCtrl.text),
              'gradingMode': 'counting',
              'name': _nameResult(),
            });
          },
          child: Text(widget.confirmLabel),
        ),
      ],
    );
  }
}

class _CreateClassDialog extends StatefulWidget {
  const _CreateClassDialog();

  @override
  State<_CreateClassDialog> createState() => _CreateClassDialogState();
}

class _CreateClassDialogState extends State<_CreateClassDialog> {
  final _codeCtrl = TextEditingController();
  final _nameCtrl = TextEditingController();
  String _level = 'primary';
  String? _error;

  @override
  void dispose() {
    _codeCtrl.dispose();
    _nameCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Tạo lớp mới'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            TextField(
              controller: _codeCtrl,
              decoration: const InputDecoration(
                labelText: 'Mã lớp',
                hintText: 'VD: TA-201',
              ),
              textCapitalization: TextCapitalization.characters,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _nameCtrl,
              decoration: const InputDecoration(
                labelText: 'Tên lớp',
                hintText: 'VD: Tiếng Anh 201',
              ),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _level,
              decoration: const InputDecoration(labelText: 'Cấp'),
              items: const [
                DropdownMenuItem(value: 'primary', child: Text('Tiểu học')),
                DropdownMenuItem(value: 'secondary', child: Text('THCS/THPT')),
              ],
              onChanged: (v) => setState(() => _level = v ?? 'primary'),
            ),
            if (_error != null) ...[
              const SizedBox(height: 8),
              Text(_error!, style: const TextStyle(color: Colors.red)),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Huỷ'),
        ),
        FilledButton(
          onPressed: () {
            final code = _codeCtrl.text.trim();
            final name = _nameCtrl.text.trim();
            if (code.isEmpty || name.isEmpty) {
              setState(() => _error = 'Nhập đủ mã lớp và tên lớp.');
              return;
            }
            Navigator.pop(context, {
              'code': code,
              'name': name,
              'level': _level,
            });
          },
          child: const Text('Tạo'),
        ),
      ],
    );
  }
}
