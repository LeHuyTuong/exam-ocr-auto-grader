import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../models/graded_paper_result.dart';
import '../services/exam_service.dart';
import '../theme/app_colors.dart';
import '../utils/error_utils.dart';
import '../widgets/confidence_badge.dart';
import '../widgets/primary_button.dart';

class GradedConfirmScreen extends StatefulWidget {
  final GradedNameResult nameResult;
  final GradedScoresResult scoresResult;
  final int classId;

  const GradedConfirmScreen({
    super.key,
    required this.nameResult,
    required this.scoresResult,
    required this.classId,
  });

  @override
  State<GradedConfirmScreen> createState() => _GradedConfirmScreenState();
}

class _GradedConfirmScreenState extends State<GradedConfirmScreen> {
  final _service = ExamService();
  final _nameCtrl = TextEditingController();
  final _totalCtrl = TextEditingController();
  final Map<String, TextEditingController> _subCtrls = {
    for (final key in const [
      'vocabulary',
      'grammar',
      'listening',
      'reading',
      'writing',
      'speaking',
    ])
      key: TextEditingController(),
  };

  static const _skillLabels = {
    'vocabulary': 'Từ vựng',
    'grammar': 'Ngữ pháp',
    'listening': 'Nghe',
    'reading': 'Đọc',
    'writing': 'Viết',
    'speaking': 'Nói',
  };

  int? _selectedStudentId;
  bool _saving = false;
  bool _createNew = false;

  @override
  void initState() {
    super.initState();
    _nameCtrl.text = widget.nameResult.ocrRawName;
    _totalCtrl.text = (widget.scoresResult.totalScore ?? 0).toString();
    for (final entry in _subCtrls.entries) {
      entry.value.text = (widget.scoresResult.subScores[entry.key] ?? 0).toString();
    }

    if (widget.nameResult.candidates.isNotEmpty) {
      final top = widget.nameResult.candidates.first;
      if (top.similarity > 0.7) {
        _selectedStudentId = top.studentId;
      }
    } else {
      // Không có gợi ý nào để chọn — chắc chắn là học sinh mới, không cần
      // bắt giáo viên bấm thêm nút (nút đó cũng không hiện khi rỗng).
      _createNew = true;
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _totalCtrl.dispose();
    for (final c in _subCtrls.values) {
      c.dispose();
    }
    super.dispose();
  }

  int get _sumOfSubScores =>
      _subCtrls.values.fold(0, (sum, c) => sum + (int.tryParse(c.text) ?? 0));

  int get _total => int.tryParse(_totalCtrl.text) ?? 0;

  bool get _sumMismatch => _sumOfSubScores != _total;

  Future<void> _save() async {
    // Chưa chọn học sinh và cũng không tạo mới thì backend trả 422 — báo rõ
    // thay vì để lỗi thô "Lỗi lưu: DioException 422" hiện lên.
    if (!_createNew && _selectedStudentId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Hãy chọn học sinh từ gợi ý, hoặc bấm "Đây là học sinh mới".'),
        ),
      );
      return;
    }
    if (_createNew && _nameCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Hãy nhập tên học sinh mới.')),
      );
      return;
    }

    setState(() => _saving = true);

    try {
      final subScores = {
        for (final entry in _subCtrls.entries)
          entry.key: int.tryParse(entry.value.text) ?? 0,
      };

      await _service.saveGrade(
        examId: widget.nameResult.examId,
        classId: widget.classId,
        totalCorrect: _total,
        score: _total.toDouble(),
        ocrRawName: _nameCtrl.text,
        imageUrl: widget.nameResult.imageUrl ?? '',
        imageUrl2: widget.scoresResult.imageUrl,
        aiConfidence: widget.scoresResult.aiConfidence,
        studentId: _createNew ? null : _selectedStudentId,
        createNewStudent: _createNew,
        newStudentName: _createNew ? _nameCtrl.text : null,
        subScores: subScores,
      );

      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() => _saving = false);
      final message = (e is DioException && e.response?.statusCode == 409)
          ? 'Học sinh này đã có điểm cho bài thi này.'
          : friendlyError(e);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(message)),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Xác nhận điểm Unit Test'),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                _Thumbnail(url: widget.nameResult.imageUrl, label: 'Tên'),
                const SizedBox(width: 12),
                _Thumbnail(url: widget.scoresResult.imageUrl, label: 'Điểm'),
              ],
            ),
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _nameCtrl,
                    decoration: const InputDecoration(labelText: 'Tên học sinh'),
                    onChanged: (_) => setState(() {}),
                  ),
                ),
                const SizedBox(width: 8),
                ConfidenceBadge(confidence: widget.nameResult.aiConfidence),
              ],
            ),
            if (widget.nameResult.candidates.isNotEmpty && !_createNew) ...[
              const SizedBox(height: 12),
              const Text('Gợi ý:', style: TextStyle(fontWeight: FontWeight.w600)),
              const SizedBox(height: 4),
              RadioGroup<int>(
                groupValue: _selectedStudentId,
                onChanged: (v) => setState(() => _selectedStudentId = v),
                child: Column(
                  children: widget.nameResult.candidates.map((c) {
                    final selected = _selectedStudentId == c.studentId;
                    return Card(
                      color: selected ? theme.colorScheme.primaryContainer : null,
                      child: ListTile(
                        dense: true,
                        title: Text(c.fullName),
                        trailing: Text(
                          '${(c.similarity * 100).toStringAsFixed(0)}%',
                          style: TextStyle(
                            color: AppColors.fromConfidence(c.similarity),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        leading: Radio<int>(value: c.studentId),
                      ),
                    );
                  }).toList(),
                ),
              ),
              TextButton(
                onPressed: () => setState(() => _createNew = true),
                child: const Text('Đây là học sinh mới'),
              ),
            ],
            if (_createNew) ...[
              const SizedBox(height: 8),
              const Text(
                'Sẽ tạo học sinh mới với tên trên',
                style: TextStyle(color: AppColors.warning),
              ),
            ],
            const SizedBox(height: 24),
            Row(
              children: [
                Text('Điểm thành phần',
                    style: theme.textTheme.titleMedium
                        ?.copyWith(fontWeight: FontWeight.bold)),
                const SizedBox(width: 8),
                ConfidenceBadge(confidence: widget.scoresResult.aiConfidence),
              ],
            ),
            const SizedBox(height: 12),
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 2.6,
              children: _subCtrls.entries.map((entry) {
                return TextField(
                  controller: entry.value,
                  decoration: InputDecoration(labelText: _skillLabels[entry.key]),
                  keyboardType: TextInputType.number,
                  onChanged: (_) => setState(() {}),
                );
              }).toList(),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _totalCtrl,
              decoration: const InputDecoration(labelText: 'TỔNG'),
              keyboardType: TextInputType.number,
              style: const TextStyle(fontWeight: FontWeight.bold),
              onChanged: (_) => setState(() {}),
            ),
            if (_sumMismatch) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.red.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.red),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.warning_amber_rounded, color: Colors.red),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Tổng ($_total) không khớp tổng các điểm thành phần ($_sumOfSubScores) — kiểm tra lại trước khi lưu.',
                        style: const TextStyle(color: Colors.red),
                      ),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 24),
            PrimaryButton(
              label: 'Xác nhận và lưu',
              loading: _saving,
              icon: Icons.save,
              onPressed: _save,
            ),
          ],
        ),
      ),
    );
  }
}

class _Thumbnail extends StatelessWidget {
  final String? url;
  final String label;

  const _Thumbnail({required this.url, required this.label});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Expanded(
      child: Column(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: url != null
                ? Image.network(
                    url!,
                    height: 96,
                    width: double.infinity,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => Container(
                      height: 96,
                      color: theme.colorScheme.surfaceContainerHighest,
                      child: const Icon(Icons.image),
                    ),
                  )
                : Container(
                    height: 96,
                    color: theme.colorScheme.surfaceContainerHighest,
                    child: const Icon(Icons.image_not_supported),
                  ),
          ),
          const SizedBox(height: 4),
          Text(label, style: theme.textTheme.bodySmall),
        ],
      ),
    );
  }
}
