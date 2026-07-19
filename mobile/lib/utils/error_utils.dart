import 'package:dio/dio.dart';

/// Rút thông báo lỗi thân thiện từ response backend thay vì in DioException thô.
String friendlyError(Object e) {
  if (e is DioException) {
    final data = e.response?.data;
    if (data is Map) {
      // Laravel validation error (422): {"message": "...", "errors": {field: [msg]}}.
      // Ưu tiên lỗi field cụ thể (vd. lý do thật khiến đăng ký thất bại: sai
      // định dạng mật khẩu, email đã tồn tại...) hơn message tóm tắt chung chung.
      final errors = data['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final firstFieldErrors = errors.values.first;
        if (firstFieldErrors is List && firstFieldErrors.isNotEmpty) {
          return firstFieldErrors.first.toString();
        }
      }
      if (data['message'] is String) {
        return data['message'] as String;
      }
    }
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.connectionError) {
      return 'Không kết nối được máy chủ. Kiểm tra mạng hoặc địa chỉ server.';
    }
    final code = e.response?.statusCode;
    if (code == 500) {
      return 'Máy chủ gặp sự cố khi xử lý. Vui lòng thử lại.';
    }
  }
  return 'Đã có lỗi xảy ra. Vui lòng thử lại.';
}
