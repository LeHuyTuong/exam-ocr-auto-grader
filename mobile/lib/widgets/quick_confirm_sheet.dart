import 'dart:async';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../models/extract_result.dart';
import '../theme/app_colors.dart';
import '../services/exam_service.dart';
import '../utils/error_utils.dart';
import 'confidence_badge.dart';
import 'score_display.dart';
import 'primary_button.dart';

class QuickConfirmSheet extends StatefulWidget {
  final ExtractResult result;
  final int classId;
  final int totalQuestions;
  final int maxScore;
  final VoidCallback onSaved;
  final VoidCallback onEditDetail;
  final bool autoSaveEnabled;

  const QuickConfirmSheet({
    super.key,
    required this.result,
    required this.classId,
    required this.totalQuestions,
    required this.maxScore,
    required this.onSaved,
    required this.onEditDetail,
    this.autoSaveEnabled = false,
  });

  @override
  State<QuickConfirmSheet> createState() => _QuickConfirmSheetState();
}

class _QuickConfirmSheetState extends State<QuickConfirmSheet> {
  final _service = ExamService();
  bool _saving = false;
  bool _saved = false;
  int? _selectedStudentId;
  bool _createNew = false;
  late final TextEditingController _nameCtrl;
  late final TextEditingController _correctCtrl;

  int _countdown = 3;
  bool _countdownActive = false;
  Timer? _countdownTimer;

  @override
  void initState() {
    super.initState();
    _nameCtrl = TextEditingController(text: widget.result.ocrRawName);
    _correctCtrl =
        TextEditingController(text: widget.result.totalCorrect.toString());

    if (widget.result.candidates.isNotEmpty) {
      final top = widget.result.candidates.first;
      if (top.similarity > 0.7) {
        _selectedStudentId = top.studentId;
      }
    }

    final topCandidate = widget.result.candidates.isNotEmpty
        ? widget.result.candidates.first
        : null;
    final highConfidence = widget.result.aiConfidence >= 0.8;
    final highSimilarity =
        topCandidate != null && topCandidate.similarity >= 0.85;

    if (widget.autoSaveEnabled && highConfidence && highSimilarity) {
      _startAutoSave();
    }
  }

  @override
  void dispose() {
    _countdownTimer?.cancel();
    _nameCtrl.dispose();
    _correctCtrl.dispose();
    super.dispose();
  }

  void _startAutoSave() {
    _countdownActive = true;
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted || !_countdownActive) {
        timer.cancel();
        return;
      }
      setState(() => _countdown--);
      if (_countdown <= 0) {
        timer.cancel();
        _save();
      }
    });
  }

  double get _score {
    final correct = int.tryParse(_correctCtrl.text) ?? 0;
    if (widget.totalQuestions <= 0) return 0;
    return (correct / widget.totalQuestions) * widget.maxScore;
  }

  Future<void> _save() async {
    _countdownActive = false;
    _countdownTimer?.cancel();

    // Chặn sớm ở client: chưa chọn học sinh và cũng không tạo mới thì backend
    // sẽ trả VALIDATION_ERROR — hiện hướng dẫn rõ ràng thay vì để lỗi thoáng qua.
    if (!_createNew && _selectedStudentId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Hãy chọn học sinh từ gợi ý, hoặc bấm "Học sinh mới".'),
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
      await _service.saveGrade(
        examId: widget.result.examId,
        classId: widget.classId,
        totalCorrect: int.tryParse(_correctCtrl.text) ?? 0,
        score: _score,
        ocrRawName: _nameCtrl.text,
        imageUrl: widget.result.imageUrl,
        aiConfidence: widget.result.aiConfidence,
        studentId: _createNew ? null : _selectedStudentId,
        createNewStudent: _createNew,
        newStudentName: _createNew ? _nameCtrl.text : null,
      );
      widget.onSaved();
      if (mounted) setState(() => _saved = true);
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

  void _cancelAutoSave() {
    _countdownActive = false;
    _countdownTimer?.cancel();
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final topCandidate = widget.result.candidates.isNotEmpty
        ? widget.result.candidates.first
        : null;

    return DraggableScrollableSheet(
      initialChildSize: 0.5,
      minChildSize: 0.3,
      maxChildSize: 0.85,
      builder: (context, scrollController) {
        return Container(
          decoration: BoxDecoration(
            color: theme.colorScheme.surface,
            borderRadius:
                const BorderRadius.vertical(top: Radius.circular(20)),
          ),
          child: ListView(
            controller: scrollController,
            padding: const EdgeInsets.all(24),
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: theme.colorScheme.outlineVariant,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.network(
                      widget.result.imageUrl,
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
                                confidence: widget.result.aiConfidence),
                          ],
                        ),
                        const SizedBox(height: 8),
                        ScoreDisplay(
                          score: _score,
                          maxScore: widget.maxScore.toDouble(),
                          fontSize: 24,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              if (topCandidate != null && !_createNew) ...[
                const SizedBox(height: 16),
                const Text('Gợi ý:',
                    style: TextStyle(fontWeight: FontWeight.w600)),
                const SizedBox(height: 4),
                RadioGroup<int>(
                  groupValue: _selectedStudentId,
                  onChanged: (v) => setState(() => _selectedStudentId = v),
                  child: Column(
                    children: widget.result.candidates.map((c) {
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
                  child: const Text('Học sinh mới'),
                ),
              ],
              if (_createNew) ...[
                const SizedBox(height: 8),
                TextField(
                  controller: _nameCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Tên học sinh',
                  ),
                  onChanged: (_) => setState(() {}),
                ),
              ],
              const SizedBox(height: 12),
              TextField(
                controller: _correctCtrl,
                decoration: const InputDecoration(
                  labelText: 'Số câu đúng',
                ),
                keyboardType: TextInputType.number,
              ),
              const SizedBox(height: 24),
              if (_saved)
                const Center(
                  child: Text('Đã lưu!',
                      style: TextStyle(color: Colors.green, fontSize: 18)),
                )
              else ...[
                Row(
                  children: [
                    if (_countdownActive) ...[
                      Text(
                        'Tự động lưu sau $_countdown...',
                        style:
                            TextStyle(color: theme.colorScheme.primary),
                      ),
                      const SizedBox(width: 12),
                      TextButton(
                        onPressed: _cancelAutoSave,
                        child: const Text('Huỷ'),
                      ),
                      const Spacer(),
                    ],
                    Expanded(
                      child: PrimaryButton(
                        label: 'Lưu & Tiếp',
                        loading: _saving,
                        icon: Icons.save,
                        onPressed: _save,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                OutlinedButton(
                  onPressed: _countdownActive
                      ? _cancelAutoSave
                      : widget.onEditDetail,
                  child: Text(
                      _countdownActive ? 'Chỉnh sửa thủ công' : 'Sửa chi tiết'),
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}
