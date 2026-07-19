<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bỏ ràng buộc "1 lớp = đúng 1 đề" (unique class_id) để một lớp có thể có nhiều
 * đề thi theo thời gian (kiểm tra tháng 1, tháng 2...). Thêm cột is_active đánh
 * dấu đề đang hoạt động. Bất biến "đúng 1 đề active/lớp" được đảm bảo ở tầng ứng
 * dụng qua Exam::activateExclusively() — không dùng unique index vì MySQL không
 * có partial/sparse unique tiện dụng ở đây (cho phép nhiều hàng is_active=false
 * cùng lớp). Các đề hiện có (mỗi lớp đúng 1) tự nhận is_active=true qua default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropUnique(['class_id']);
            $table->boolean('is_active')->default(true)->after('grading_mode');
            $table->index(['class_id', 'is_active']);
        });
    }

    public function down(): void
    {
        throw new \Exception('Irreversible: có thể đã tồn tại dữ liệu nhiều đề/lớp, không rollback được migration này.');
    }
};
