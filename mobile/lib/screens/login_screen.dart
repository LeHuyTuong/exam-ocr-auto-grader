import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/auth_service.dart';
import '../widgets/primary_button.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _emailCtrl.dispose();
    _passCtrl.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    final auth = context.read<AuthService>();
    final error = await auth.login(
      _emailCtrl.text.trim(),
      _passCtrl.text,
    );
    if (error != null && mounted) {
      setState(() => _error = error);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    final theme = Theme.of(context);

    return Scaffold(
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: theme.colorScheme.primaryContainer,
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.school,
                  size: 48,
                  color: theme.colorScheme.primary,
                ),
              ),
              const SizedBox(height: 24),
              Text(
                'Chấm Thi',
                style: theme.textTheme.headlineMedium
                    ?.copyWith(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Text(
                'Đăng nhập để tiếp tục',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 40),
              TextField(
                controller: _emailCtrl,
                decoration: const InputDecoration(
                  labelText: 'Email',
                  prefixIcon: Icon(Icons.email_outlined),
                ),
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _passCtrl,
                decoration: const InputDecoration(
                  labelText: 'Mật khẩu',
                  prefixIcon: Icon(Icons.lock_outlined),
                ),
                obscureText: true,
                textInputAction: TextInputAction.done,
                onSubmitted: (_) => _login(),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.errorContainer,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.error_outline,
                          color: theme.colorScheme.error, size: 20),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          _error!,
                          style: TextStyle(color: theme.colorScheme.error),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 24),
              PrimaryButton(
                label: 'Đăng nhập',
                loading: auth.loading,
                icon: Icons.login,
                onPressed: _login,
              ),
              const SizedBox(height: 16),
              TextButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                        builder: (_) => const RegisterScreen()),
                  );
                },
                child: const Text('Chưa có tài khoản? Đăng ký'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
