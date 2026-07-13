import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiClient {
  static final ApiClient _instance = ApiClient._();
  factory ApiClient() => _instance;
  ApiClient._();

  late Dio _dio;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  static const String _baseUrlKey = 'api_url';
  static const String _tokenKey = 'auth_token';
  static const String _defaultBaseUrl = 'http://10.0.2.2:8080/api';

  Future<void> init() async {
    final savedUrl = await _storage.read(key: _baseUrlKey);
    final baseUrl = savedUrl ?? _defaultBaseUrl;
    _dio = Dio(BaseOptions(
      baseUrl: baseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 30),
      headers: {'Accept': 'application/json'},
    ));

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _storage.read(key: _tokenKey);
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
    ));
  }

  Future<void> setBaseUrl(String url) async {
    await _storage.write(key: _baseUrlKey, value: url);
    _dio.options.baseUrl = url;
  }

  Future<String?> getBaseUrl() => _storage.read(key: _baseUrlKey);

  Future<String?> getToken() => _storage.read(key: _tokenKey);
  Future<void> setToken(String token) async =>
      _storage.write(key: _tokenKey, value: token);
  Future<void> clearToken() async => _storage.delete(key: _tokenKey);

  Future<Response> post(String path, {dynamic data}) =>
      _dio.post(path, data: data);

  Future<Response> get(String path, {Map<String, dynamic>? params}) =>
      _dio.get(path, queryParameters: params);

  Future<Response> put(String path, {dynamic data}) =>
      _dio.put(path, data: data);

  Future<Response> uploadImage(String path, String filePath,
      {Map<String, dynamic>? fields}) async {
    final formData = FormData.fromMap({
      'image': await MultipartFile.fromFile(filePath),
      if (fields != null) ...fields,
    });
    return _dio.post(path, data: formData);
  }
}
