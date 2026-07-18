import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiClient {
  static final ApiClient _instance = ApiClient._();
  factory ApiClient() => _instance;
  ApiClient._();

  late Dio _dio;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  VoidCallback? onSessionExpired;

  static const String _baseUrlKey = 'api_url';
  static const String _tokenKey = 'auth_token';
  static const String _tokenExpiryKey = 'token_expiry';
  // 🔴 SECURITY FIXME (OWASP A02): HTTP plaintext — JWT tokens & exam data
  // transmitted unencrypted in production. Switch to HTTPS once SSL is enabled
  // on carbonx.io.vn. After switching, also remove android:usesCleartextTraffic
  // from AndroidManifest.xml.
  static String get _defaultBaseUrl {
    if (kReleaseMode) return 'http://carbonx.io.vn/api';  // FIXME: change to https://
    if (kIsWeb) return 'http://127.0.0.1:8080/api';
    return 'http://10.0.2.2:8080/api';
  }

  bool _refreshing = false;

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
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          if (error.requestOptions.extra['_retried'] == true) {
            onSessionExpired?.call();
            handler.next(error);
            return;
          }
          if (!_refreshing) {
            final refreshed = await _tryRefreshToken();
            if (refreshed) {
              final token = await _storage.read(key: _tokenKey);
              error.requestOptions.extra['_retried'] = true;
              error.requestOptions.headers['Authorization'] = 'Bearer $token';
              try {
                final response = await _dio.fetch(error.requestOptions);
                handler.resolve(response);
                return;
              } catch (e) {
                handler.next(error);
                return;
              }
            } else {
              onSessionExpired?.call();
            }
          }
        }
        handler.next(error);
      },
    ));
  }

  Future<bool> _tryRefreshToken() async {
    _refreshing = true;
    try {
      final token = await _storage.read(key: _tokenKey);
      if (token == null) return false;

      final response = await Dio(BaseOptions(
        baseUrl: _dio.options.baseUrl,
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      )).post('/auth/refresh');

      final newToken = response.data['access_token'] as String?;
      final expiresIn = response.data['expires_in'] as int?;

      if (newToken != null) {
        await setToken(newToken);
        if (expiresIn != null) {
          final expiry = DateTime.now()
              .add(Duration(seconds: expiresIn - 300))
              .millisecondsSinceEpoch;
          await _storage.write(key: _tokenExpiryKey, value: expiry.toString());
        }
        return true;
      }
      return false;
    } catch (_) {
      await clearToken();
      await _storage.delete(key: _tokenExpiryKey);
      return false;
    } finally {
      _refreshing = false;
    }
  }

  Future<bool> isTokenExpiringSoon() async {
    final expiryStr = await _storage.read(key: _tokenExpiryKey);
    if (expiryStr == null) return false;
    final expiry = int.tryParse(expiryStr) ?? 0;
    return DateTime.now().millisecondsSinceEpoch >= expiry;
  }

  Future<void> setBaseUrl(String url) async {
    // Update the in-memory client first so a storage failure (e.g. secure
    // storage misbehaving on web) can't prevent the URL change from taking
    // effect for the current session.
    _dio.options.baseUrl = url;
    try {
      await _storage.write(key: _baseUrlKey, value: url);
    } catch (_) {}
  }

  Future<String?> getBaseUrl() => _storage.read(key: _baseUrlKey);

  /// The URL actually in effect right now (saved override or platform default).
  String get currentBaseUrl => _dio.options.baseUrl;

  Future<String?> getToken() => _storage.read(key: _tokenKey);

  Future<void> setToken(String token) async {
    await _storage.write(key: _tokenKey, value: token);
  }

  Future<void> clearToken() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _tokenExpiryKey);
  }

  Future<void> setTokenExpiry(int expiresIn) async {
    final expiry =
        DateTime.now().add(Duration(seconds: expiresIn - 300)).millisecondsSinceEpoch;
    await _storage.write(key: _tokenExpiryKey, value: expiry.toString());
  }

  /// Checks whether the configured server is reachable, without needing auth.
  /// Any HTTP response (even 401) proves the server is up; only a transport
  /// failure (timeout / no connection) counts as unreachable.
  Future<bool> testConnection() async {
    try {
      await _dio.get('/auth/me');
      return true;
    } on DioException catch (e) {
      return e.response != null;
    } catch (_) {
      return false;
    }
  }

  Future<Response> post(String path, {dynamic data}) =>
      _dio.post(path, data: data);

  Future<Response> get(String path, {Map<String, dynamic>? params}) =>
      _dio.get(path, queryParameters: params);

  /// For binary downloads (e.g. exported .xlsx files) — bypasses Dio's
  /// default JSON parsing so the raw bytes come back untouched.
  Future<List<int>> getBytes(String path) async {
    final response = await _dio.get<List<int>>(
      path,
      options: Options(responseType: ResponseType.bytes),
    );
    return response.data ?? [];
  }

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
