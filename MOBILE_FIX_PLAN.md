# Kế hoạch sửa bug Mobile (sau review flow)

Thứ tự đề xuất: làm từ trên xuống. Mỗi bug độc lập, sửa xong test riêng được.
Build APK mới (v1.0.6-test) sau khi xong nhóm 🔴 + 🟠.

---

## 🔴 CRITICAL

### Fix #1 — Thêm cửa vào cho flow YLE
- **Triệu chứng:** Toàn bộ màn hình YLE (quét đề Cambridge) không mở được từ app — code "mồ côi".
- **File:** `mobile/lib/screens/home_screen.dart`
- **Nguyên nhân:** Home chỉ có 3 card, không có nút nào dẫn tới `YleAnswerKeyScreen`/`YleScanScreen`.
- **Cách sửa (ĐÃ CHỐT thiết kế: card riêng → màn chọn):**
  1. Thêm 1 card thứ 4 ở `home_screen.dart`: "Chấm đề Cambridge YLE" (icon `Icons.school`), `onTap` → push màn **YleHomeScreen** (màn mới).
  2. `YleHomeScreen` có 2 lựa chọn: **"Nhập đáp án"** → `YleAnswerKeyScreen`; **"Quét bài chấm"** → màn chọn paper.
  3. Màn chọn paper: list `GET /yle/exams` → chọn paper → chọn lớp (tái dùng logic `class_select_screen`) → push `YleScanScreen(exam, classId, className)`.
- **Test:** Mở app → thấy card YLE → vào được màn nhập đáp án và màn quét (camera lên).

### Fix #2 — Camera chạy nền sau khi bấm "Sửa chi tiết"
- **Triệu chứng:** Bấm "Sửa chi tiết" ở bottom sheet → sang màn Xác nhận, nhưng camera vẫn quét nền → bottom sheet lạ tự bật đè, hoặc lỗi "camera already streaming" khi quay lại.
- **File:** `mobile/lib/screens/scan_screen.dart` (`_showQuickConfirm` dòng ~212-256, `_startImageStream` dòng ~123-126)
- **Nguyên nhân:** Nhánh `onEditDetail` không đặt `_processing=false` trước khi đóng sheet → `.whenComplete` tưởng còn xử lý → `_resetAfterCapture()` restart stream nền. Sau đó `onSaved` của ConfirmScreen restart lần 2 → double stream. `_startImageStream` không kiểm  tra stream đang chạy.
- **Cách sửa:**
  1. Trong `_startImageStream`, guard: `if (_cameraController == null || _cameraController!.value.isStreamingImages) return;` trước khi `startImageStream`.
  2. Trong `onEditDetail`: đặt cờ để `.whenComplete` KHÔNG tự reset (vd set `_processing=false` và một cờ `_navigatingToDetail=true`, hoặc đơn giản là **dừng camera hẳn** khi rời sang ConfirmScreen rồi khởi động lại khi `onSaved`/quay lại). Cách gọn: trước `Navigator.push(ConfirmScreen)` gọi `_stopCamera()`; trong `onSaved` và sau khi push trả về thì `_initCamera()` lại.
- **Test:** Chụp 1 ảnh → "Sửa chi tiết" → lưu → quay lại scanner: camera chạy 1 luồng, không có sheet lạ, không lỗi streaming.

---

## 🟠 MEDIUM

### Fix #3 — Màn Thống kê crash khi điểm là chuỗi
- **Triệu chứng:** Mở Thống kê → màn đỏ nếu API trả điểm dạng `"8.50"`.
- **File:** `mobile/lib/screens/dashboard_screen.dart` (`_formatScore` dòng ~114-117)
- **Cách sửa:** Đổi `_formatScore` chịu được mọi kiểu:
  ```dart
  String _formatScore(dynamic score) {
    if (score == null) return '0.0';
    final n = score is num ? score : num.tryParse(score.toString());
    return (n ?? 0).toStringAsFixed(1);
  }
  ```
  (Song song: nên sửa `DashboardController` backend cast `(float)` cho các field điểm để trả number — kiểm tra & sửa nếu đang trả string.)
- **Test:** Mở Thống kê với lớp có điểm → hiện số đúng, không văng.

### Fix #4 — Sửa tên OCR vô tình tạo học sinh trùng
- **Triệu chứng:** Sửa lỗi chính tả tên của học sinh đã chọn → khi lưu tạo học sinh MỚI thay vì gán vào học sinh cũ.
- **File:** `mobile/lib/screens/confirm_screen.dart` (dòng ~176, `onChanged`)
- **Nguyên nhân:** `onChanged: (_) => setState(() => _createNew = true)` — gõ bất kỳ ký tự nào đều bật cờ tạo mới.
- **Cách sửa:** Bỏ auto-set `_createNew` trong `onChanged`. Chỉ bật `_createNew=true` khi người dùng **chủ động bấm nút "Học sinh mới"** (giống `quick_confirm_sheet`). Sửa tên chỉ đổi giá trị tên gửi lên, giữ nguyên `student_id` đã chọn (nếu có). Nếu muốn: khi đang chọn 1 candidate mà sửa tên khác hẳn thì mới hỏi.
- **Test:** Chọn 1 gợi ý học sinh → sửa 1 ký tự tên → lưu → điểm gán vào ĐÚNG học sinh cũ (không tạo trùng).

### Fix #5 — Token hết hạn không tự về Login
- **Triệu chứng:** Token hết hạn/refresh fail → app kẹt, mọi thứ 401, phải Logout tay.
- **File:** `mobile/lib/services/api_client.dart` (`_tryRefreshToken`), `mobile/lib/services/auth_service.dart`, `mobile/lib/main.dart`
- **Nguyên nhân:** Refresh fail chỉ `clearToken()`, không xóa `AuthService._user` + không `notifyListeners()`.
- **Cách sửa:** Cho `ApiClient` một callback "onSessionExpired" (hoặc dùng một `ValueNotifier`/stream) mà `AuthService` lắng nghe; khi refresh fail → `AuthService` set `_user=null` + `notifyListeners()` → `main.dart` tự rebuild về Login. Cách đơn giản: `ApiClient` giữ `VoidCallback? onUnauthorized`, `main`/`AuthService` gán nó để clear user.
- **Test:** Làm token hết hạn (hoặc xóa token trên server) → gọi 1 API → app tự đá về màn Login, không kẹt.

### Fix #6 — Chặn vòng lặp refresh vô hạn
- **Triệu chứng:** 1 endpoint trả 401 vì lý do khác (phân quyền) → refresh → retry → refresh... lặp.
- **File:** `mobile/lib/services/api_client.dart` (interceptor onError dòng ~37-54)
- **Cách sửa:** Đánh dấu request đã retry 1 lần (vd thêm `options.extra['retried']=true`); nếu đã retried mà vẫn 401 → không refresh nữa, để lỗi đi qua (và trigger onUnauthorized ở Fix #5). Chỉ refresh khi request chưa từng retry.
- **Test:** Giả lập endpoint luôn 401 → chỉ refresh đúng 1 lần rồi dừng, không lặp.

---

## 🟡 NHỎ (sửa kèm cho gọn)

### Fix #7 — YLE: card "AI đọc được tên" luôn trống
- **File:** `mobile/lib/screens/yle/yle_scan_screen.dart` (dòng ~204-240)
- **Cách sửa:** Đọc `ocrRawName` từ `uploadResult` (kết quả `uploadPage`) truyền vào `YleStudentConfirmScreen`, thay vì lấy `_submission!.ocrRawName` (đang null ở trang 1).
- **Test:** Quét trang 1 có tên → màn xác nhận hiện tên AI đọc được.

### Fix #8 — YLE: tạo submission lỗi → chụp im lặng
- **File:** `mobile/lib/screens/yle/yle_scan_screen.dart` (`_createSubmission`, `_captureAndProcess` dòng ~203-206)
- **Cách sửa:** Nếu `_submission == null` khi chụp → hiện snackbar "Chưa tạo được phiên chấm, thử lại" + nút thử tạo lại, thay vì reset im lặng. Cân nhắc retry `_createSubmission`.
- **Test:** Tắt mạng → vào quét YLE → bấm chụp → có thông báo rõ ràng.

### Fix #9 — quick_confirm: setState sau dispose + lệch bộ đếm
- **File:** `mobile/lib/widgets/quick_confirm_sheet.dart` (dòng ~119, 122, 126)
- **Cách sửa:** Bọc `if (mounted)` quanh các `setState`; và đảm bảo `onSaved` được gọi kể cả khi sheet đã đóng (để `_gradedCount` không lệch) — vd gọi `widget.onSaved()` trước khi phụ thuộc `mounted`, hoặc lưu xong mới đóng sheet.
- **Test:** Chụp → trong lúc đang lưu thì kéo đóng sheet → không có log lỗi, bộ đếm vẫn tăng đúng.

### Fix #10 — Số câu = 0 gây điểm NaN
- **File:** `mobile/lib/screens/class_select_screen.dart` (`_ExamDialog` validate), `mobile/lib/widgets/quick_confirm_sheet.dart` (tính điểm dòng ~97-100)
- **Cách sửa:** Validate `totalQuestions >= 1` trong dialog (không cho lưu nếu ≤0); và khi tính điểm guard chia 0 (`total > 0 ? correct/total*max : 0`).
- **Test:** Nhập 0 câu → dialog báo lỗi không cho bắt đầu; hoặc điểm ra 0 thay vì NaN.

---

## Sau khi sửa
1. `dart analyze lib` (qua symlink `/tmp/chamthi_mobile`) = 0 lỗi.
2. `flutter build apk --release` → phát hành **v1.0.6-test**.
3. Test thật trên điện thoại: luồng thường (chọn lớp → quét → sửa chi tiết → lưu) + luồng YLE (vào được, quét, xác nhận HS, chấm tay, kết quả).
