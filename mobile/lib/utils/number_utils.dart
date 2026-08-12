import 'package:flutter/services.dart';

/// Bàn phím số trên điện thoại có phím thập phân (mặc định TextInputType.number
/// không có dấu chấm nên giáo viên không gõ được 7.5).
const decimalKeyboard = TextInputType.numberWithOptions(decimal: true);

/// Chỉ cho gõ chữ số và dấu ngăn cách thập phân — chặn chữ/ký tự lạ ngay tại ô
/// nhập thay vì để lỗi 422 lúc lưu.
final decimalInputFormatters = <TextInputFormatter>[
  FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]')),
];

/// Đọc điểm giáo viên gõ. Chấp nhận cả "7.5" và "7,5" — bàn phím tiếng Việt
/// thường cho ra dấu phẩy, mà double.parse("7,5") thì fail.
double? parseScore(String raw) {
  final text = raw.trim().replaceAll(',', '.');
  if (text.isEmpty) return null;

  return double.tryParse(text);
}

/// Hiển thị điểm gọn nhất có thể: 8.0 -> "8", 7.5 -> "7.5", 7.25 -> "7.25".
String formatScore(num value) {
  final rounded = double.parse(value.toStringAsFixed(2));

  return rounded == rounded.roundToDouble()
      ? rounded.toStringAsFixed(0)
      : rounded.toString();
}
