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
  const GradeListScreen({super.key});

  @override
  State<GradeListScreen> createState() => _GradeListScreenState();
}

class _GradeListScreenState extends State<GradeListScreen> {
  final _service = ExamService();
  final _scrollController = ScrollController();
  List<SchoolClass> _classes = [];
  SchoolClass? _selectedClass;
  List<Grade> _grades = [];
  bool _loadingGrades = false;
  bool _loadingMore = false;
  bool _exporting = false;
  int _currentPage = 1;
  int _lastPage = 1;
  int? _examId;

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
      }
    } catch (_) {}
  }

  Future<void> _loadGrades() async {
    if (_selectedClass == null) return;
    setState(() {
      _loadingGrades = true;
      _currentPage = 1;
      _grades = [];
      _examId = null;
    });

    try {
      final exam = await _service.getClassExam(_selectedClass!.id);
      if (exam != null) {
        final examId =
            (exam['exam'] as Map<String, dynamic>?)?['id'] as int? ?? 0;
        final result = await _service.getGrades(examId);
        final meta = result['meta'] as Map<String, dynamic>?;
        final rawGrades =
            List<Map<String, dynamic>>.from(result['grades'] as List? ?? []);
        _grades = rawGrades.map((g) => Grade.fromJson(g)).toList();
        _currentPage = meta?['current_page'] as int? ?? 1;
        _lastPage = meta?['last_page'] as int? ?? 1;
        _examId = examId;
      }
    } catch (_) {}

    if (mounted) setState(() => _loadingGrades = false);
  }

  Future<void> _exportExcel() async {
    if (_examId == null || _exporting) return;
    setState(() => _exporting = true);

    try {
      final bytes = await _service.downloadExcel(_examId!);
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/Diem_${_selectedClass?.code}_$_examId.xlsx');
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
    if (_selectedClass == null) return;
    setState(() => _loadingMore = true);

    try {
      final exam = await _service.getClassExam(_selectedClass!.id);
      if (exam != null) {
        final examId =
            (exam['exam'] as Map<String, dynamic>?)?['id'] as int? ?? 0;
        final result =
            await _service.getGrades(examId, page: _currentPage + 1);
        final meta = result['meta'] as Map<String, dynamic>?;
        final rawGrades =
            List<Map<String, dynamic>>.from(result['grades'] as List? ?? []);
        _grades.addAll(rawGrades.map((g) => Grade.fromJson(g)));
        _currentPage = meta?['current_page'] as int? ?? 1;
        _lastPage = meta?['last_page'] as int? ?? 1;
      }
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
          if (_examId != null)
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
                setState(() => _selectedClass = c);
                _loadGrades();
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
                            onRefresh: _loadGrades,
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
