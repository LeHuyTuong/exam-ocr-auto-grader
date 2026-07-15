<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            // Quyết định màn quét nào áp dụng khi giáo viên quay lại lớp này
            // sau này trong ngày (exam "hôm nay" đã tồn tại, không hỏi lại):
            // "counting" = đếm câu đúng (luồng cũ), "graded" = Unit Test đã
            // chấm tay, chụp 2 ảnh (tên + dải điểm đỏ).
            $table->string('grading_mode')->default('counting')->after('max_score');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('grading_mode');
        });
    }
};
