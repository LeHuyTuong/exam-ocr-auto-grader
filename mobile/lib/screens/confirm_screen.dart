import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../models/extract_result.dart';
import '../services/exam_service.dart';
import '../theme/app_colors.dart';
import '../widgets/confidence_badge.dart';
import '../widgets/score_display.dart';
import '../widgets/primary_button.dart';
import 'grade_list_screen.dart';

class ConfirmScreen extends StatefulWidget {
  final ExtractResult extractResult;
  final int classId;
  final int totalQuestions;
  final int maxScore;
  final VoidCallback? onSaved;

  const ConfirmScreen({
    super.key,
    required this.extractResult,
    required this.classId,
    required this.totalQuestions,
    required this.maxScore,
    this.onSaved,
  });

  @override
  State<ConfirmScreen> createState() => _ConfirmScreenState();
}

class _ConfirmScreenState extends State<ConfirmScreen> {
  final _service = ExamService();
  final _nameCtrl = TextEditingController();
  final _correctCtrl = TextEditingController();
  late double _score;
  int? _selectedStudentId;
  bool _saving = false;
  bool _createNew = false;

  @override
  void initState() {
    super.initState();
    _nameCtrl.text = widget.extractResult.ocrRawName;
    _correctCtrl.text = widget.extractResult.totalCorrect.toString();
    _score = widget.extractResult.score;

    if (widget.extractResult.candidates.isNotEmpty) {
      final top = widget.extractResult.candidates.first;
      if (top.similarity > 0.7) {
        _selectedStudentId = top.studentId;
      }
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _correctCtrl.dispose();
    super.dispose();
  }

  void _updateScore() {
    final correct = int.tryParse(_correctCtrl.text) ?? 0;
    setState(() {
      _score = widget.totalQuestions > 0
          ? (correct / widget.totalQuestions) * widget.maxScore
          : 0;
    });
  }

  Future<void> _save() async {
    setState(() => _saving = true);

    try {
      await _service.saveGrade(
        examId: widget.extractResult.examId,
        classId: widget.classId,
        totalCorrect: int.tryParse(_correctCtrl.text) ?? 0,
        score: _score,
        ocrRawName: _nameCtrl.text,
        imageUrl: widget.extractResult.imageUrl,
        aiConfidence: widget.extractResult.aiConfidence,
        studentId: _createNew ? null : _selectedStudentId,
        createNewStudent: _createNew,
        newStudentName: _createNew ? _nameCtrl.text : null,
      );

      if (widget.onSaved != null) {
        widget.onSaved!();
        if (mounted) Navigator.pop(context);
      } else if (mounted) {
        Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(builder: (_) => const GradeListScreen()),
          (route) => false,
        );
      }
    } catch (e) {
      setState(() => _saving = false);
      String message = 'Lỗi lưu: $e';
      if (e is DioException && e.response?.statusCode == 409) {
        message = 'Học sinh này đã có điểm cho bài thi này.';
      }
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
        title: const Text('Xác nhận'),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.network(
                    widget.extractResult.imageUrl,
                    width: 72,
                    height: 96,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => Container(
                      width: 72,
                      height: 96,
                      color: theme.colorScheme.surfaceContainerHighest,
                      child: const Icon(Icons.image),
                    ),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              _nameCtrl.text,
                              style: theme.textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.bold),
                            ),
                          ),
                          ConfidenceBadge(
                              confidence: widget.extractResult.aiConfidence),
                        ],
                      ),
                      const SizedBox(height: 8),
                      ScoreDisplay(
                        score: _score,
                        maxScore: widget.maxScore.toDouble(),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),
            TextField(
              controller: _nameCtrl,
              decoration: const InputDecoration(
                labelText: 'Tên học sinh',
              ),
              onChanged: (_) => setState(() {}),
            ),
            if (widget.extractResult.candidates.isNotEmpty &&
                !_createNew) ...[
              const SizedBox(height: 12),
              const Text('Gợi ý:',
                  style: TextStyle(fontWeight: FontWeight.w600)),
              const SizedBox(height: 4),
              RadioGroup<int>(
                groupValue: _selectedStudentId,
                onChanged: (v) => setState(() => _selectedStudentId = v),
                child: Column(
                  children: widget.extractResult.candidates.map((c) {
                    final selected = _selectedStudentId == c.studentId;
                    return Card(
                      color: selected
                          ? theme.colorScheme.primaryContainer
                          : null,
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
            const SizedBox(height: 16),
            TextField(
              controller: _correctCtrl,
              decoration: const InputDecoration(
                labelText: 'Số câu đúng',
              ),
              keyboardType: TextInputType.number,
              onChanged: (_) => _updateScore(),
            ),
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
