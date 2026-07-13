import 'package:flutter/material.dart';

class AppColors {
  AppColors._();

  static const Color success = Color(0xFF16A34A);
  static const Color warning = Color(0xFFF59E0B);
  static const Color error = Color(0xFFDC2626);

  static Color confidenceHigh = success;
  static Color confidenceMedium = warning;
  static Color confidenceLow = error;

  static Color fromConfidence(double confidence) {
    if (confidence >= 0.8) return confidenceHigh;
    if (confidence >= 0.6) return confidenceMedium;
    return confidenceLow;
  }
}
