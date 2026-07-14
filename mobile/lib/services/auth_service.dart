import 'package:flutter/foundation.dart';
import '../models/user.dart';
import 'api_client.dart';

class AuthService extends ChangeNotifier {
  final ApiClient _api = ApiClient();
  User? _user;
  bool _loading = false;
  bool _restored = false;

  AuthService() {
    _api.onSessionExpired = _handleSessionExpired;
  }

  User? get user => _user;
  bool get isAuthenticated => _user != null;
  bool get loading => _loading;
  bool get restored => _restored;

  Future<String?> register(
      String name, String email, String password, String? classCode) async {
    _loading = true;
    notifyListeners();

    try {
      final data = <String, dynamic>{
        'name': name,
        'email': email,
        'password': password,
      };
      if (classCode != null && classCode.isNotEmpty) {
        data['class_code'] = classCode;
      }

      final res = await _api.post('/auth/register', data: data);
      final responseData = res.data as Map<String, dynamic>;
      await _api.setToken(responseData['access_token'] as String);
      await _api.setTokenExpiry(responseData['expires_in'] as int);
      _user = User.fromJson(responseData['user'] as Map<String, dynamic>);
      _loading = false;
      notifyListeners();
      return null;
    } catch (e) {
      _loading = false;
      notifyListeners();
      return 'Đăng ký thất bại. Email có thể đã tồn tại.';
    }
  }

  Future<bool> tryRestoreSession() async {
    final token = await _api.getToken();
    if (token == null) {
      _restored = true;
      notifyListeners();
      return false;
    }

    try {
      final res = await _api.get('/auth/me');
      _user = User.fromJson(res.data['user']);
      _restored = true;
      notifyListeners();
      return true;
    } catch (_) {
      await _api.clearToken();
      _restored = true;
      notifyListeners();
      return false;
    }
  }

  Future<String?> login(String email, String password) async {
    _loading = true;
    notifyListeners();

    try {
      final res = await _api.post('/auth/login', data: {
        'email': email,
        'password': password,
      });

      final data = res.data;
      await _api.setToken(data['access_token'] as String);
      await _api.setTokenExpiry(data['expires_in'] as int);
      _user = User.fromJson(data['user'] as Map<String, dynamic>);
      _loading = false;
      notifyListeners();
      return null;
    } catch (e) {
      _loading = false;
      notifyListeners();
      return 'Đăng nhập thất bại. Kiểm tra email/mật khẩu.';
    }
  }

  Future<void> logout() async {
    try {
      await _api.post('/auth/logout');
    } catch (_) {}
    await _api.clearToken();
    _user = null;
    notifyListeners();
  }

  void _handleSessionExpired() {
    _user = null;
    notifyListeners();
  }
}
