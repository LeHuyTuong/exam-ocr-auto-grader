<?php

/**
 * Khung đánh giá kỹ năng (dùng cho export Excel + dashboard lớp).
 *
 * Một kỹ năng được coi là "đạt" khi điểm >= `pass`, "cần cải thiện" khi < `pass`.
 * Ngưỡng theo đúng công thức Excel của giáo viên:
 *   Từ vựng/Ngữ pháp/Nghe/Nói (max 10) -> pass 9
 *   Đọc/Viết (max 5) -> pass 4
 *
 * `sub_scores` trong bảng `grades` chỉ có với bài Unit Test chấm tay
 * (grading_mode = graded); bài "đếm câu đúng" không có điểm thành phần.
 */
return [

    'thresholds' => [
        'vocabulary' => ['label' => 'Từ vựng', 'max' => 10, 'pass' => 9],
        'grammar' => ['label' => 'Ngữ pháp', 'max' => 10, 'pass' => 9],
        'listening' => ['label' => 'Kỹ năng nghe', 'max' => 10, 'pass' => 9],
        'reading' => ['label' => 'Kỹ năng đọc', 'max' => 5, 'pass' => 4],
        'writing' => ['label' => 'Kỹ năng viết', 'max' => 5, 'pass' => 4],
        'speaking' => ['label' => 'Kỹ năng nói', 'max' => 10, 'pass' => 9],
    ],

    // Tổng max của 6 kỹ năng.
    'total_max' => 50,

    // Thứ tự cột khi export (khớp cột C-H của template giáo viên).
    'export_order' => ['vocabulary', 'grammar', 'listening', 'reading', 'writing', 'speaking'],
];
