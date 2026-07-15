import 'dart:math' as math;
import 'package:flutter/material.dart';

enum ScanState { idle, detecting, stable, processing }

class ScanOverlay extends StatelessWidget {
  final ScanState state;
  final int gradedCount;

  const ScanOverlay({
    super.key,
    required this.state,
    required this.gradedCount,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Stack(
      children: [
        _buildScanFrame(context),
        Positioned(
          top: 60,
          left: 0,
          right: 0,
          child: _buildStatusBar(theme),
        ),
        if (gradedCount > 0)
          Positioned(
            bottom: 100,
            left: 16,
            right: 16,
            child: _buildProgressBar(theme),
          ),
      ],
    );
  }

  Widget _buildScanFrame(BuildContext context) {
    final size = MediaQuery.of(context).size;
    final width = size.width * 0.75;
    // A4 portrait ratio (210:297) so the guide matches a real exam page,
    // clamped so it never overflows short screens.
    final height = math.min(width * (297 / 210), size.height * 0.65);

    return Center(
      child: Container(
        width: width,
        height: height,
        decoration: BoxDecoration(
          border: Border.all(
            color: state == ScanState.stable
                ? Colors.green
                : state == ScanState.processing
                    ? Colors.orange
                    : Colors.white,
            width: 2,
          ),
          borderRadius: BorderRadius.circular(12),
          color: Colors.black.withValues(alpha: 0.05),
        ),
        child: state == ScanState.processing
            ? const Center(child: CircularProgressIndicator(color: Colors.white))
            : null,
      ),
    );
  }

  Widget _buildStatusBar(ThemeData theme) {
    String text;
    IconData icon;
    Color color;

    switch (state) {
      case ScanState.idle:
        text = 'Giữ yên tờ giấy trong khung — máy tự chụp';
        icon = Icons.camera_alt_outlined;
        color = Colors.white70;
      case ScanState.detecting:
        text = 'Đang tìm bài...';
        icon = Icons.search;
        color = Colors.yellowAccent;
      case ScanState.stable:
        text = 'Đã nhận diện!';
        icon = Icons.check_circle;
        color = Colors.greenAccent;
      case ScanState.processing:
        text = 'Đang xử lý...';
        icon = Icons.hourglass_top;
        color = Colors.orangeAccent;
    }

    return Column(
      children: [
        Icon(icon, color: color, size: 32),
        const SizedBox(height: 8),
        Text(
          text,
          style: TextStyle(
              color: color, fontSize: 16, fontWeight: FontWeight.w600),
        ),
      ],
    );
  }

  Widget _buildProgressBar(ThemeData theme) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.black54,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.checklist, color: Colors.white, size: 20),
          const SizedBox(width: 8),
          Text(
            'Đã chấm: $gradedCount bài',
            style: const TextStyle(color: Colors.white, fontSize: 14),
          ),
        ],
      ),
    );
  }
}
