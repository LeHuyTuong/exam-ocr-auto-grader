import 'package:flutter/material.dart';
import '../models/school_class.dart';
import '../services/exam_service.dart';
import '../widgets/app_card.dart';
import '../widgets/empty_state.dart';
import '../widgets/error_view.dart';
import '../widgets/loading_view.dart';
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
      final exam = await _service.getTodayExam(cls.id);
      int totalQuestions;
      int? maxScore;

      if (exam == null) {
        if (!mounted) return;
        final result = await showDialog<Map<String, int>>(
          context: context,
          builder: (ctx) => _ExamDialog(classCode: cls.code),
        );
        if (result == null) return;
        totalQuestions = result['totalQuestions'] ?? 50;
        maxScore = result['maxScore'];
        await _service.createTodayExam(cls.id, totalQuestions, maxScore);
      } else {
        totalQuestions = (exam['exam'] as Map<String, dynamic>?)
                ?.tryGet('totalQuestions') as int? ?? 50;
        maxScore = (exam['exam'] as Map<String, dynamic>?)
            ?.tryGet('maxScore') as int?;
      }

      if (!mounted) return;
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => ScanScreen(
            classId: cls.id,
            className: '${cls.code} - ${cls.name}',
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

class _ExamDialogState extends State<_ExamDialog> {
  final _questionsCtrl = TextEditingController(text: '50');
  final _maxScoreCtrl = TextEditingController(text: '10');

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
        'Bài thi ${widget.classCode}',
        style: theme.textTheme.titleMedium,
      ),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            controller: _questionsCtrl,
            decoration: const InputDecoration(
              labelText: 'Tổng số câu hỏi',
              hintText: '50',
            ),
            keyboardType: TextInputType.number,
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _maxScoreCtrl,
            decoration: const InputDecoration(
              labelText: 'Thang điểm',
              hintText: 'Để trống = số câu hỏi',
            ),
            keyboardType: TextInputType.number,
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Huỷ'),
        ),
        FilledButton(
          onPressed: () {
            Navigator.pop(context, {
              'totalQuestions': int.tryParse(_questionsCtrl.text) ?? 50,
              'maxScore': int.tryParse(_maxScoreCtrl.text),
            });
          },
          child: const Text('Bắt đầu'),
        ),
      ],
    );
  }
}
