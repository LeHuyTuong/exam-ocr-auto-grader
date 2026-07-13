import 'package:flutter_test/flutter_test.dart';
import 'package:chamthi_mobile/main.dart';

void main() {
  testWidgets('App can be created', (WidgetTester tester) async {
    await tester.pumpWidget(const ChamThiApp());
  });
}
