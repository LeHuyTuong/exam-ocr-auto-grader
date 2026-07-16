import 'package:flutter/material.dart';
import '../models/school_class.dart';
import '../services/exam_service.dart';
import '../widgets/app_card.dart';
import '../widgets/empty_state.dart';
import '../widgets/error_view.dart';
import '../widgets/loading_view.dart';
import 'graded_scan_screen.dart';
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
                  ?.map(
                      (c) => SchoolClass.fromJson(c as Map<String, dynamic>))
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
      final exam = await _service.getClassExam(cls.id);
      int totalQuestions;
      int? maxScore;
      String gradingMode;

      if (exam == null) {
        if (!mounted) return;
        final result = await showDialog<Map<String, dynamic>>(
          context: context,
          builder: (ctx) => _ExamDialog(classCode: cls.code),
        );
        if (result == null) return;
        totalQuestions = result['totalQuestions'] as int? ?? 50;
        maxScore = result['maxScore'] as int?;
        gradingMode = result['gradingMode'] as String? ?? 'counting';
        await _service.createClassExam(cls.id, totalQuestions, maxScore,
            gradingMode: gradingMode);
      } else {
        final examData = exam['exam'] as Map<String, dynamic>?;
        totalQuestions = examData?.tryGet('totalQuestions') as int? ?? 50;
        maxScore = examData?.tryGet('maxScore') as int?;
        gradingMode = examData?.tryGet('gradingMode') as String? ?? 'counting';
      }

      if (!mounted) return;

      final className = '${cls.code} - ${cls.name}';
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => gradingMode == 'graded'
              ? GradedScanScreen(classId: cls.id, className: className)
              : ScanScreen(
                  classId: cls.id,
                  className: className,
                  totalQuestions: totalQuestions,
                  maxScore: maxScore ?? totalQuestions,
                ),
        ),
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Lỗi: $e')),
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
                                      style: theme.textTheme.bodySmall
                                          ?.copyWith(
                                        color:
                                            theme.colorScheme.onSurfaceVariant,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              Icon(
                                Icons.arrow_forward_ios,
                                size: 16,
                                color: theme.colorScheme.onSurfaceVariant,
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

class _ExamDialog extends StatefulWidget {
  final String classCode;
  const _ExamDialog({required this.classCode});

  @override
  State<_ExamDialog> createState() => _ExamDialogState();
}

enum _GradingMode { counting, graded }

class _ExamDialogState extends State<_ExamDialog> {
  final _questionsCtrl = TextEditingController(text: '50');
  final _maxScoreCtrl = TextEditingController(text: '10');
  _GradingMode _mode = _GradingMode.counting;

  @override
  void dispose() {
    _questionsCtrl.dispose();
    _maxScoreCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return AlertDialog(
      title: Text(
        'Chọn kiểu chấm — ${widget.classCode}',
        style: theme.textTheme.titleMedium,
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            RadioGroup<_GradingMode>(
              groupValue: _mode,
              onChanged: (v) => setState(() {
                _mode = v ?? _GradingMode.counting;
                if (_mode == _GradingMode.graded && _maxScoreCtrl.text == '10') {
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
                hintText: _mode == _GradingMode.counting ? 'Để trống = số câu hỏi' : '50',
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
            });
          },
          child: const Text('Bắt đầu'),
        ),
      ],
    );
  }
}
