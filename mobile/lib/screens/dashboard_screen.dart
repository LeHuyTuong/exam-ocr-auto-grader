import 'package:flutter/material.dart';
import '../models/school_class.dart';
import '../services/exam_service.dart';
import '../widgets/app_card.dart';
import '../widgets/empty_state.dart';
import '../widgets/loading_view.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final _service = ExamService();
  final _scrollController = ScrollController();
  List<SchoolClass> _classes = [];
  SchoolClass? _selectedClass;
  Map<String, dynamic>? _classStats;
  List<Map<String, dynamic>> _students = [];
  bool _loadingClass = false;
  bool _loadingStudents = false;
  bool _loadingMoreStudents = false;
  int _studentPage = 1;
  int _studentLastPage = 1;

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
        !_loadingMoreStudents &&
        _studentPage < _studentLastPage) {
      _loadMoreStudents();
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

  Future<void> _loadDashboard() async {
    if (_selectedClass == null) return;
    setState(() {
      _loadingClass = true;
      _loadingStudents = true;
      _studentPage = 1;
      _students = [];
    });

    try {
      final stats = await _service.getDashboardClassStats(_selectedClass!.id);
      final students =
          await _service.getDashboardStudentStats(_selectedClass!.id);
      final meta = students['meta'] as Map<String, dynamic>?;
      _classStats = stats;
      _students = List<Map<String, dynamic>>.from(
          students['students'] as List? ?? []);
      _studentPage = meta?['current_page'] as int? ?? 1;
      _studentLastPage = meta?['last_page'] as int? ?? 1;
    } catch (_) {}

    if (mounted) {
      setState(() {
        _loadingClass = false;
        _loadingStudents = false;
      });
    }
  }

  Future<void> _loadMoreStudents() async {
    if (_selectedClass == null) return;
    setState(() => _loadingMoreStudents = true);

    try {
      final result = await _service.getDashboardStudentStats(
        _selectedClass!.id,
        page: _studentPage + 1,
      );
      final meta = result['meta'] as Map<String, dynamic>?;
      final newStudents = List<Map<String, dynamic>>.from(
          result['students'] as List? ?? []);
      _students.addAll(newStudents);
      _studentPage = meta?['current_page'] as int? ?? 1;
      _studentLastPage = meta?['last_page'] as int? ?? 1;
    } catch (_) {}

    if (mounted) setState(() => _loadingMoreStudents = false);
  }

  String _formatScore(dynamic score) {
    if (score == null) return '-';
    final n = score is num ? score : num.tryParse(score.toString());
    return (n ?? 0).toStringAsFixed(1);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Thống kê điểm'),
        centerTitle: true,
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
                _loadDashboard();
              },
            ),
          ),
          Expanded(
            child: _loadingClass
                ? const LoadingView(message: 'Đang tải thống kê...')
                : _classStats == null
                    ? const EmptyState(
                        icon: Icons.bar_chart,
                        message: 'Chọn lớp để xem thống kê',
                      )
                    : ListView(
                        controller: _scrollController,
                        padding: const EdgeInsets.all(16),
                        children: [
                          _buildStatsCard(theme),
                          const SizedBox(height: 24),
                          Text(
                            'Điểm trung bình từng học sinh',
                            style: theme.textTheme.titleMedium
                                ?.copyWith(fontWeight: FontWeight.bold),
                          ),
                          const SizedBox(height: 8),
                          if (_loadingStudents)
                            const LoadingView()
                          else if (_students.isEmpty)
                            const EmptyState(
                              icon: Icons.people_outline,
                              message: 'Chưa có dữ liệu học sinh',
                            )
                          else
                            ..._students
                                .map((s) => _buildStudentCard(s, theme)),
                          if (_studentPage < _studentLastPage)
                            const Padding(
                              padding: EdgeInsets.all(16),
                              child:
                                  Center(child: CircularProgressIndicator()),
                            ),
                        ],
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatsCard(ThemeData theme) {
    final stats = _classStats!['stats'] as Map<String, dynamic>? ?? {};
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Tổng quan lớp',
            style: theme.textTheme.titleMedium
                ?.copyWith(fontWeight: FontWeight.bold),
          ),
          const Divider(),
          _statRow(theme, 'Sĩ số', '${stats['total_students'] ?? 0}'),
          _statRow(theme, 'Số bài thi', '${stats['total_exams'] ?? 0}'),
          _statRow(
              theme, 'Điểm TB lớp', _formatScore(stats['average_score'])),
          _statRow(
              theme, 'Cao nhất', _formatScore(stats['highest_score'])),
          _statRow(
              theme, 'Thấp nhất', _formatScore(stats['lowest_score'])),
        ],
      ),
    );
  }

  Widget _statRow(ThemeData theme, String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: theme.textTheme.bodyMedium),
          Text(value,
              style: theme.textTheme.bodyMedium
                  ?.copyWith(fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }

  Widget _buildStudentCard(Map<String, dynamic> s, ThemeData theme) {
    return AppCard(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  s['full_name'] ?? '',
                  style: theme.textTheme.titleSmall
                      ?.copyWith(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 4),
                Text(
                  'TB: ${_formatScore(s['average_score'])}  |  Cao: ${_formatScore(s['best_score'])}  |  Thấp: ${_formatScore(s['worst_score'])}',
                  style: theme.textTheme.bodySmall,
                ),
              ],
            ),
          ),
          Text(
            '${s['total_exams']} bài',
            style: theme.textTheme.bodySmall
                ?.copyWith(color: theme.colorScheme.onSurfaceVariant),
          ),
        ],
      ),
    );
  }
}
