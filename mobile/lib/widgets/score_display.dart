import 'package:flutter/material.dart';

import '../utils/number_utils.dart';

class ScoreDisplay extends StatelessWidget {
  final double score;
  final double maxScore;
  final double? fontSize;

  const ScoreDisplay({
    super.key,
    required this.score,
    required this.maxScore,
    this.fontSize,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final fs = fontSize ?? 28;
    return Row(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.baseline,
      textBaseline: TextBaseline.alphabetic,
      children: [
        Text(
          formatScore(score),
          style: TextStyle(
            fontSize: fs,
            fontWeight: FontWeight.bold,
            color: theme.colorScheme.primary,
          ),
        ),
        Text(
          ' / ${formatScore(maxScore)}',
          style: TextStyle(
            fontSize: fs * 0.6,
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
      ],
    );
  }
}
