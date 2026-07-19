import 'dart:io';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';
import '../models/grade.dart';
import '../models/school_class.dart';
import '../services/exam_service.dart';
import '../utils/error_utils.dart';
import '../widgets/app_card.dart';
import '../widgets/empty_state.dart';
import '../widgets/loading_view.dart';

class GradeListScreen extends StatefulWidget {
  /// Khi được mở từ màn chọn đề (đề khoá → chỉ xem/xuất), truyền sẵn classId +
  /// examId để tự chọn luôn, giáo viên không cần bấm lại.
  final int? classId;
  final int? examId;

  const GradeListScreen({super.key, this.classId, this.examId});

  @override
  State<GradeListScreen> createState() => _GradeListScreenState();
}

class _GradeListScreenState extends State<GradeListScreen> {
  final _service = ExamService();
  final _scrollController = ScrollController();
  List<SchoolClass> _classes = [];
  SchoolClass? _selectedClass;
  List<Map<String, dynamic>> _exams = [];
  int? _selectedExamId;
  List<Grade> _grades = [];
  bool _loadingGrades = false;
  bool _loadingMore = false;
  bool _exporting = false;
  int _currentPage = 1;
  int _lastPage = 1;

  @override
  void initState() {
    super.initState();
    _loadClasses();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
            _scrollController.position.maxScrollExtent - 200 &&
        !_loadingMore &&
        _currentPage < _lastPage) {
      _loadMoreGrades();
    }
  }

  Future<void> _loadClasses() async {
    try {
      final data = await _service.getClasses();
      if (mounted) {
        setState(() {
          _classes = (data['classes'] as List?)
                  ?.map(
                      (c) => SchoolClass.fromJson(c as Map<String, dynamic>))
                  .toList() ??
              [];
        });
        // Mở từ màn chọn đề (có classId truyền sẵn) → tự chọn lớp đó luôn.
        if (widget.classId != null) {
          final preset = _classes.cast<SchoolClass?>().firstWhere(
                (c) => c?.id == widget.classId,
                orElse: () => null,
              );
          if (preset != null) {
            setState(() => _selectedClass = preset);
            await _loadExams();
          }
        }
      }
    } catch (_) {}
  }

  /// Khi đổi lớp (hoặc mở có classId truyền sẵn) — tải danh sách đề của lớp,
  /// tự chọn đề (ưu tiên examId truyền sẵn, không thì đề active, không thì đề
  /// đầu) rồi tải điểm.
  Future<void> _loadExams() async {
    if (_selectedClass == null) return;
    setState(() {
      _loadingGrades = true;
      _currentPage = 1;
      _grades = [];
      _exams = [];
      _selectedExamId = null;
    });

    try {
      final exams = await _service.getClassExams(_selectedClass!.id);
      if (!mounted) return;
      setState(() => _exams = exams);

      int? pick;
      if (widget.examId != null && exams.any((e) => e['id'] == widget.examId)) {
        pick = widget.examId;
      } else {
        final active = exams.firstWhere(
          (e) => e['isActive'] == true,
          orElse: () => exams.isEmpty ? <String, dynamic>{} : exams.first,
        );
        pick = active['id'] as int?;
      }
      if (pick != null) {
        setState(() => _selectedExamId = pick);
        await _loadGradesForExam();
        return;
      }
    } catch (_) {}

    if (mounted) setState(() => _loadingGrades = false);
  }

  Future<void> _loadGradesForExam() async {
    if (_selectedClass == null || _selectedExamId == null) return;
    setState(() {
      _loadingGrades = true;
      _currentPage = 1;
      _grades = [];
    });

    try {
      final result = await _service.getGrades(_selectedExamId!);
      final meta = result['meta'] as Map<String, dynamic>?;
      final rawGrades =
          List<Map<String, dynamic>>.from(result['grades'] as List? ?? []);
      _grades = rawGrades.map((g) => Grade.fromJson(g)).toList();
      _currentPage = meta?['current_page'] as int? ?? 1;
      _lastPage = meta?['last_page'] as int? ?? 1;
    } catch (_) {}

    if (mounted) setState(() => _loadingGrades = false);
  }

  Future<void> _exportExcel() async {
    if (_selectedExamId == null || _exporting) return;
    setState(() => _exporting = true);

    try {
      final bytes = await _service.downloadExcel(_selectedExamId!);
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/Diem_${_selectedClass?.code}_$_selectedExamId.xlsx');
      await file.writeAsBytes(bytes);

      await SharePlus.instance.share(ShareParams(
        files: [XFile(file.path)],
        text: 'Bảng điểm ${_selectedClass?.code} - ${_selectedClass?.name}',
      ));
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(friendlyError(e))),
        );
      }
    }

    if (mounted) setState(() => _exporting = false);
  }

  Future<void> _loadMoreGrades() async {
    if (_selectedClass == null || _selectedExamId == null) return;
    setState(() => _loadingMore = true);

    try {
      final result =
          await _service.getGrades(_selectedExamId!, page: _currentPage + 1);
      final meta = result['meta'] as Map<String, dynamic>?;
      final rawGrades =
          List<Map<String, dynamic>>.from(result['grades'] as List? ?? []);
      _grades.addAll(rawGrades.map((g) => Grade.fromJson(g)));
      _currentPage = meta?['current_page'] as int? ?? 1;
      _lastPage = meta?['last_page'] as int? ?? 1;
    } catch (_) {}

    if (mounted) setState(() => _loadingMore = false);
  }

  String _formatDate(String? dateStr) {
    if (dateStr == null) return '';
    try {
      final date = DateTime.parse(dateStr);
      return DateFormat('HH:mm - dd/MM/yyyy', 'vi').format(date);
    } catch (_) {
      return dateStr;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Danh sách điểm'),
        centerTitle: true,
        actions: [
          if (_selectedExamId != null)
            IconButton(
              icon: _exporting
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.ios_share),
              tooltip: 'Xuất Excel',
              onPressed: _exporting ? null : _exportExcel,
            ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
            child: DropdownButtonFormField<SchoolClass>(
              initialValue: _selectedClass,
              decoration: const InputDecoration(
                labelText: 'Chọn lớp',
                prefixIcon: Icon(Icons.class_),
              ),
              items: _classes
                  .map((c) => DropdownMenuItem(
                        value: c,
                        child: Text('${c.code} - ${c.name}'),
                      ))
                  .toList(),
              onChanged: (c) {
                setState(() {
                  _selectedClass = c;
                  _exams = [];
                  _selectedExamId = null;
                  _grades = [];
                });
                if (c != null) _loadExams();
              },
            ),
          ),
          if (_selectedClass != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: DropdownButtonFormField<int>(
                initialValue: _selectedExamId,
                decoration: const InputDecoration(
                  labelText: 'Chọn đề',
                  prefixIcon: Icon(Icons.assignment_outlined),
                ),
                items: _exams
                    .map((e) => DropdownMenuItem(
                          value: e['id'] as int,
                          child: Text(
                            '${e['name'] as String? ?? 'Bài thi'}'
                            '${e['isActive'] == true ? ' (Đang chấm)' : ' (Đã khoá)'}',
                          ),
                        ))
                    .toList(),
                onChanged: (id) {
                  if (id == null) return;
                  setState(() => _selectedExamId = id);
                  _loadGradesForExam();
                },
              ),
            ),
          Expanded(
            child: _loadingGrades
                ? const LoadingView(message: 'Đang tải điểm...')
                : _selectedClass == null
                    ? const EmptyState(
                        icon: Icons.touch_app,
                        message: 'Chọn lớp để xem danh sách điểm',
                      )
                    : _grades.isEmpty
                        ? const EmptyState(
                            icon: Icons.inbox_outlined,
                            message: 'Chưa có điểm nào',
                          )
                        : RefreshIndicator(
                            onRefresh: _loadGradesForExam,
                            child: ListView.builder(
                              controller: _scrollController,
                              padding:
                                  const EdgeInsets.symmetric(horizontal: 16),
                              itemCount: _grades.length +
                                  (_currentPage < _lastPage ? 1 : 0),
                              itemBuilder: (context, i) {
                                if (i == _grades.length) {
                                  return const Padding(
                                    padding: EdgeInsets.all(16),
                                    child: Center(
                                        child: CircularProgressIndicator()),
                                  );
                                }
                                final g = _grades[i];
                                return AppCard(
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 16, vertical: 12),
                                  child: Row(
                                    children: [
                                      CircleAvatar(
                                        backgroundColor:
                                            theme.colorScheme.primaryContainer,
                                        child: Text(
                                          '${i + 1}',
                                          style: TextStyle(
                                            color:
                                                theme.colorScheme.primary,
                                          ),
                                        ),
                                      ),
                                      const SizedBox(width: 16),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              g.studentName,
                                              style: theme.textTheme.titleSmall
                                                  ?.copyWith(
                                                      fontWeight:
                                                          FontWeight.w600),
                                            ),
                                            const SizedBox(height: 4),
                                            Text(
                                              'Đúng: ${g.totalCorrect}  |  Điểm: ${g.score.toStringAsFixed(1)}',
                                              style:
                                                  theme.textTheme.bodySmall,
                                            ),
                                            if (g.createdAt != null) ...[
                                              const SizedBox(height: 2),
                                              Text(
                                                _formatDate(g.createdAt),
                                                style: theme.textTheme.bodySmall
                                                    ?.copyWith(
                                                  color: theme
                                                      .colorScheme
                                                      .onSurfaceVariant,
                                                  fontSize: 11,
                                                ),
                                              ),
                                            ],
                                          ],
                                        ),
                                      ),
                                      Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.end,
                                        children: [
                                          Chip(
                                            label: Text(
                                              g.status == 'confirmed'
                                                  ? 'Đã lưu'
                                                  : 'Chờ',
                                              style: TextStyle(
                                                fontSize: 11,
                                                color: g.status == 'confirmed'
                                                    ? Colors.green
                                                    : Colors.orange,
                                              ),
                                            ),
                                            visualDensity:
                                                VisualDensity.compact,
                                            padding: EdgeInsets.zero,
                                          ),
                                        ],
                                      ),
                                    ],
                                  ),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}
