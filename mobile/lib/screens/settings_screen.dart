import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../services/api_client.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  final _urlCtrl = TextEditingController();
  bool _saving = false;
  bool _testing = false;
  bool _autoSave = true;
  String? _testResult;

  @override
  void initState() {
    super.initState();
    _loadSettings();
  }

  @override
  void dispose() {
    _urlCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadSettings() async {
    const storage = FlutterSecureStorage();
    final url = await storage.read(key: 'api_url');
    final autoSave = await storage.read(key: 'auto_save');
    if (mounted) {
      setState(() {
        _urlCtrl.text = url ?? 'http://10.0.2.2:8080/api';
        _autoSave = autoSave != 'false';
      });
    }
  }

  Future<void> _saveUrl() async {
    setState(() => _saving = true);
    await ApiClient().setBaseUrl(_urlCtrl.text.trim());
    setState(() => _saving = false);
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Đã lưu địa chỉ server')),
      );
    }
  }

  Future<void> _testConnection() async {
    setState(() {
      _testing = true;
      _testResult = null;
    });
    try {
      final token = await ApiClient().getToken();
      if (token == null) {
        setState(() {
          _testResult = 'Chưa đăng nhập. Không thể kiểm tra.';
          _testing = false;
        });
        return;
      }
      await ApiClient().get('/user');
      setState(() {
        _testResult = 'Kết nối thành công!';
        _testing = false;
      });
    } catch (e) {
      setState(() {
        _testResult = 'Kết nối thất bại: $e';
        _testing = false;
      });
    }
  }

  Future<void> _toggleAutoSave(bool value) async {
    setState(() => _autoSave = value);
    const storage = FlutterSecureStorage();
    await storage.write(key: 'auto_save', value: value.toString());
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Cài đặt')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Server',
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _urlCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Địa chỉ API',
                      hintText: 'http://10.0.2.2:8080/api',
                      prefixIcon: Icon(Icons.dns),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: FilledButton(
                          onPressed: _saving ? null : _saveUrl,
                          child: _saving
                              ? const SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(
                                      strokeWidth: 2),
                                )
                              : const Text('Lưu'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _testing ? null : _testConnection,
                          icon: _testing
                              ? const SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(
                                      strokeWidth: 2),
                                )
                              : const Icon(Icons.wifi_find),
                          label: const Text('Kiểm tra'),
                        ),
                      ),
                    ],
                  ),
                  if (_testResult != null) ...[
                    const SizedBox(height: 8),
                    Text(
                      _testResult!,
                      style: TextStyle(
                        color: _testResult!.contains('thành công')
                            ? Colors.green
                            : Colors.red,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Quét bài thi',
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  SwitchListTile(
                    title: const Text('Tự động lưu'),
                    subtitle: const Text(
                        'Tự động lưu khi AI đủ tự tin (đếm ngược 3s)'),
                    value: _autoSave,
                    onChanged: _toggleAutoSave,
                    contentPadding: EdgeInsets.zero,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
