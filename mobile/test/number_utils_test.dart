import 'package:chamthi_mobile/utils/number_utils.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('parseScore', () {
    test('đọc được điểm lẻ với dấu chấm và dấu phẩy', () {
      expect(parseScore('7.5'), 7.5);
      // Bàn phím tiếng Việt cho ra dấu phẩy — trước đây int.tryParse trả null
      // nên 7,5 bị lưu thành 0.
      expect(parseScore('7,5'), 7.5);
      expect(parseScore(' 8 '), 8);
    });

    test('trả null khi ô trống hoặc không phải số', () {
      expect(parseScore(''), isNull);
      expect(parseScore('  '), isNull);
      expect(parseScore('abc'), isNull);
    });
  });

  group('formatScore', () {
    test('bỏ phần thập phân thừa, giữ nửa điểm', () {
      expect(formatScore(8), '8');
      expect(formatScore(8.0), '8');
      expect(formatScore(7.5), '7.5');
      expect(formatScore(7.25), '7.25');
    });
  });
}
