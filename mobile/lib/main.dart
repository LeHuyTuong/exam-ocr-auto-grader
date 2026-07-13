import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'services/api_client.dart';
import 'services/auth_service.dart';
import 'theme/app_theme.dart';
import 'screens/login_screen.dart';
import 'screens/home_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await ApiClient().init();

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthService()),
      ],
      child: const ChamThiApp(),
    ),
  );
}

class ChamThiApp extends StatefulWidget {
  const ChamThiApp({super.key});

  @override
  State<ChamThiApp> createState() => _ChamThiAppState();
}

class _ChamThiAppState extends State<ChamThiApp> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AuthService>().tryRestoreSession();
    });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Chấm Thi',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      home: Consumer<AuthService>(
        builder: (context, auth, _) {
          if (!auth.restored) {
            return const _SplashScreen();
          }
          if (auth.isAuthenticated) {
            return const HomeScreen();
          }
          return const LoginScreen();
        },
      ),
    );
  }
}

class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.school,
              size: 80,
              color: Theme.of(context).colorScheme.primary,
            ),
            const SizedBox(height: 24),
            const CircularProgressIndicator(),
          ],
        ),
      ),
    );
  }
}
