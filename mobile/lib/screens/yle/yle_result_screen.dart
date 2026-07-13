import 'package:flutter/material.dart';
import '../../models/yle_models.dart';
import '../../services/yle_service.dart';
import '../../theme/app_colors.dart';

class YleResultScreen extends StatefulWidget {
  final int submissionId;
  final YleExam exam;

  const YleResultScreen({
    super.key,
    required this.submissionId,
    required this.exam,
  });

  @override
  State<YleResultScreen> createState() => _YleResultScreenState();
}

class _YleResultScreenState extends State<YleResultScreen> {
  final _service = YleService();
  YlePageResult? _result;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      _result = await _service.getSubmissionResult(widget.submissionId);
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Kết quả'),
        centerTitle: true,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _result == null
              ? const Center(child: Text('Không thể tải kết quả'))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      // Total score card
                      Card(
                        color: theme.colorScheme.primaryContainer,
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            children: [
                              Text(
                                'Tổng điểm',
                                style: theme.textTheme.titleLarge,
                              ),
                              const SizedBox(height: 12),
                              Text(
                                '${_result!.submission.totalScore?.toStringAsFixed(0) ?? "—"} / ${_result!.submission.maxScore?.toStringAsFixed(0) ?? "—"}',
                                style: theme.textTheme.displayMedium?.copyWith(
                                  fontWeight: FontWeight.bold,
                                  color: theme.colorScheme.onPrimaryContainer,
                                ),
                              ),
                              const SizedBox(height: 8),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  _ScoreChip(
                                    label: 'Auto',
                                    score: _result!.submission.autoScore ?? 0,
                                    color: Colors.blue,
                                  ),
                                  const SizedBox(width: 12),
                                  _ScoreChip(
                                    label: 'Manual',
                                    score: _result!.submission.manualScore ?? 0,
                                    color: Colors.orange,
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Part breakdown
                      Text(
                        'Chi tiết từng phần',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 8),

                      ...(_result!.breakdown.map((part) => Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      'Part ${part.partNumber}: ${part.title}',
                                      style: const TextStyle(fontWeight: FontWeight.w600),
                                    ),
                                  ),
                                  _ScoreBadge(
                                    score: part.score,
                                    maxMarks: part.maxMarks,
                                  ),
                                ],
                              ),
                              if (part.isAutoGradable && part.questions.any((q) => q.studentAnswer != null)) ...[
                                const SizedBox(height: 8),
                                ...part.questions.map((q) {
                                  if (q.studentAnswer == null) return const SizedBox.shrink();
                                  final isCorrect = q.isCorrect == true;
                                  return Padding(
                                    padding: const EdgeInsets.symmetric(vertical: 2),
                                    child: Row(
                                      children: [
                                        SizedBox(
                                          width: 30,
                                          child: Text('Q${q.questionNumber}:',
                                              style: const TextStyle(fontSize: 12)),
                                        ),
                                        Text(
                                          q.studentAnswer ?? '',
                                          style: TextStyle(
                                            fontSize: 13,
                                            decoration: !isCorrect
                                                ? TextDecoration.lineThrough
                                                : null,
                                            color: isCorrect ? Colors.green : Colors.red,
                                          ),
                                        ),
                                        if (q.aiConfidence != null) ...[
                                          const SizedBox(width: 4),
                                          Text(
                                            '(${(q.aiConfidence! * 100).toStringAsFixed(0)}%)',
                                            style: TextStyle(
                                              fontSize: 11,
                                              color: AppColors.fromConfidence(q.aiConfidence!),
                                            ),
                                          ),
                                        ],
                                        const Spacer(),
                                        Icon(
                                          isCorrect ? Icons.check_circle : Icons.cancel,
                                          size: 16,
                                          color: isCorrect ? Colors.green : Colors.red,
                                        ),
                                      ],
                                    ),
                                  );
                                }),
                              ],
                            ],
                          ),
                        ),
                      ))),
                    ],
                  ),
                ),
      bottomNavigationBar: Padding(
        padding: const EdgeInsets.all(16),
        child: ElevatedButton.icon(
          onPressed: () => Navigator.popUntil(context, (route) => route.isFirst),
          icon: const Icon(Icons.home),
          label: const Text('Về trang chủ'),
          style: ElevatedButton.styleFrom(
            padding: const EdgeInsets.symmetric(vertical: 14),
          ),
        ),
      ),
    );
  }
}

class _ScoreChip extends StatelessWidget {
  final String label;
  final double score;
  final Color color;

  const _ScoreChip({
    required this.label,
    required this.score,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Chip(
      avatar: CircleAvatar(
        backgroundColor: color,
        radius: 8,
        child: Text(
          score.toStringAsFixed(0),
          style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
        ),
      ),
      label: Text(label),
    );
  }
}

class _ScoreBadge extends StatelessWidget {
  final int score;
  final int maxMarks;

  const _ScoreBadge({required this.score, required this.maxMarks});

  @override
  Widget build(BuildContext context) {
    final ratio = maxMarks > 0 ? score / maxMarks : 0.0;
    final color = ratio >= 0.8
        ? Colors.green
        : ratio >= 0.5
            ? Colors.orange
            : Colors.red;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withAlpha(25),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withAlpha(76)),
      ),
      child: Text(
        '$score/$maxMarks',
        style: TextStyle(
          fontWeight: FontWeight.bold,
          color: color,
          fontSize: 13,
        ),
      ),
    );
  }
}
