import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

class ConfidenceBadge extends StatelessWidget {
  final double confidence;

  const ConfidenceBadge({super.key, required this.confidence});

  @override
  Widget build(BuildContext context) {
    final color = AppColors.fromConfidence(confidence);
    final pct = (confidence * 100).toStringAsFixed(0);
    return Chip(
      label: Text('$pct%', style: TextStyle(fontSize: 12, color: color)),
      side: BorderSide(color: color.withValues(alpha: 0.4)),
      backgroundColor: color.withValues(alpha: 0.1),
      visualDensity: VisualDensity.compact,
    );
  }
}
