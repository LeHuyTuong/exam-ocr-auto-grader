import 'package:flutter/material.dart';
import '../../models/school_class.dart';
import '../../models/yle_models.dart';
import '../../services/exam_service.dart';
import '../../services/yle_service.dart';
import '../../widgets/app_card.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/error_view.dart';
import '../../widgets/loading_view.dart';
import 'yle_answer_key_screen.dart';
import 'yle_scan_screen.dart';
import 'yle_speaking_grade_screen.dart';

class YleHomeScreen extends StatefulWidget {
  const YleHomeScreen({super.key});

  @override
  State<YleHomeScreen> createState() => _YleHomeScreenState();
}

enum _YleStep { menu, selectExam, selectClass }

class _YleHomeScreenState extends State<YleHomeScreen> {
  final _yleService = YleService();
  final _examService = ExamService();

  _YleStep _step = _YleStep.menu;
  List<YleExam> _exams = [];
  List<SchoolClass> _classes = [];
  bool _loading = true;
  String? _error;
  YleExam? _selectedExam;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(_step == _YleStep.menu
            ? 'Cambridge YLE'
            : _step == _YleStep.selectExam
                ? 'Chọn đề thi'
                : 'Chọn lớp'),
        centerTitle: true,
      ),
      body: _buildBody(theme),
    );
  }

  Widget _buildBody(ThemeData theme) {
    switch (_step) {
      case _YleStep.menu:
        return _buildMenu(theme);
      case _YleStep.selectExam:
        return _buildExamList(theme);
      case _YleStep.selectClass:
        return _buildClassList(theme);
    }
  }

  Widget _buildMenu(ThemeData theme) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          const SizedBox(height: 20),
          AppCard(
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const YleAnswerKeyScreen()),
              );
            },
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.primaryContainer,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(Icons.edit_note, size: 32,
                      color: theme.colorScheme.primary),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Nhập đáp án',
                          style: theme.textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 4),
                      Text('Quản lý đáp án cho đề Cambridge YLE',
                          style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.onSurfaceVariant)),
                    ],
                  ),
                ),
                Icon(Icons.arrow_forward_ios, size: 16,
                    color: theme.colorScheme.onSurfaceVariant),
              ],
            ),
          ),
          AppCard(
            onTap: () {
              setState(() {
                _step = _YleStep.selectExam;
                _loading = true;
              });
              _loadExams();
            },
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.secondaryContainer,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(Icons.camera_alt, size: 32,
                      color: theme.colorScheme.secondary),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Chấm bài học sinh',
                          style: theme.textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 4),
                      Text('Chọn đề & lớp, rồi quét bài từng trang',
                          style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.onSurfaceVariant)),
                    ],
                  ),
                ),
                Icon(Icons.arrow_forward_ios, size: 16,
                    color: theme.colorScheme.onSurfaceVariant),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _skillLabel(String skill) {
    switch (skill) {
      case 'listening':
        return 'Listening';
      case 'reading_writing':
        return 'Reading & Writing';
      case 'speaking':
        return 'Speaking';
      default:
        return skill;
    }
  }

  Future<void> _loadExams() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      _exams = await _yleService.getExams();
      if (mounted) setState(() => _loading = false);
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Không thể tải danh sách đề thi.';
        });
      }
    }
  }

  Future<void> _loadClasses() async {
    setState(() {
      _step = _YleStep.selectClass;
      _loading = true;
      _error = null;
    });
    try {
      final data = await _examService.getClasses();
      if (mounted) {
        setState(() {
          _classes = (data['classes'] as List?)
                  ?.map((c) =>
                      SchoolClass.fromJson(c as Map<String, dynamic>))
                  .toList() ??
              [];
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Không thể tải danh sách lớp.';
        });
      }
    }
  }

  Widget _buildExamList(ThemeData theme) {
    if (_loading) {
      return const LoadingView(message: 'Đang tải danh sách đề thi...');
    }
    if (_error != null) {
      return ErrorView(message: _error!, onRetry: _loadExams);
    }
    if (_exams.isEmpty) {
      return const EmptyState(
        icon: Icons.assignment_outlined,
        message: 'Chưa có đề thi YLE nào.\nHãy tạo đề thi trước.',
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: _exams.length,
      itemBuilder: (context, i) {
        final exam = _exams[i];
        return AppCard(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          onTap: () {
            _selectedExam = exam;
            _loadClasses();
          },
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: theme.colorScheme.primaryContainer,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(Icons.description,
                    color: theme.colorScheme.primary),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(exam.name,
                        style: theme.textTheme.titleSmall
                            ?.copyWith(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 2),
                    Text(
                      '${exam.level.toUpperCase()} — ${_skillLabel(exam.skill)} | ${exam.totalMarks} điểm'
                      '${exam.totalPages > 0 ? ', ${exam.totalPages} trang' : ''}',
                      style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant),
                    ),
                  ],
                ),
              ),
              Icon(Icons.arrow_forward_ios, size: 16,
                  color: theme.colorScheme.onSurfaceVariant),
            ],
          ),
        );
      },
    );
  }

  Widget _buildClassList(ThemeData theme) {
    if (_loading) {
      return const LoadingView(message: 'Đang tải danh sách lớp...');
    }
    if (_error != null) {
      return ErrorView(message: _error!, onRetry: _loadClasses);
    }
    if (_classes.isEmpty) {
      return const EmptyState(
        icon: Icons.class_outlined,
        message: 'Chưa có lớp nào.\nHãy liên hệ admin để được phân công.',
      );
    }

    final exam = _selectedExam!;

    return ListView.builder(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: _classes.length,
      itemBuilder: (context, i) {
        final cls = _classes[i];
        return AppCard(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          onTap: () {
            final className = '${cls.code} - ${cls.name}';
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => exam.skill == 'speaking'
                    ? YleSpeakingGradeScreen(
                        exam: exam,
                        classId: cls.id,
                        className: className,
                      )
                    : YleScanScreen(
                        exam: exam,
                        classId: cls.id,
                        className: className,
                      ),
              ),
            );
          },
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: theme.colorScheme.primaryContainer,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(Icons.class_,
                    color: theme.colorScheme.primary),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('${cls.code} - ${cls.name}',
                        style: theme.textTheme.titleSmall
                            ?.copyWith(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 2),
                    Text('Cấp: ${cls.level}',
                        style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant)),
                  ],
                ),
              ),
              Icon(Icons.arrow_forward_ios, size: 16,
                  color: theme.colorScheme.onSurfaceVariant),
            ],
          ),
        );
      },
    );
  }
}
