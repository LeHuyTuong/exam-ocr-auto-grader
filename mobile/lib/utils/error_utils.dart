import 'package:dio/dio.dart';

/// Rút thông báo lỗi thân thiện từ response backend thay vì in DioException thô.
String friendlyError(Object e) {
  if (e is DioException) {
    final data = e.response?.data;
    if (data is Map && data['message'] is String) {
      return data['message'] as String;
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
