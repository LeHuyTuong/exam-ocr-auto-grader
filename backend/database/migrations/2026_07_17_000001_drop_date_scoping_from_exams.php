<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "1 lớp = 1 danh sách điểm liên tục" thay vì 1 exam/lớp/ngày: exam theo
     * ngày khiến giáo viên nghỉ vài hôm là mất luôn quyền xem/chấm lại, và
     * app không có màn nào xem điểm ngoài "hôm nay". Dữ liệu exams/grades
     * hiện có chỉ là dữ liệu test (chưa có học sinh thật nộp bài) nên xoá
     * sạch, mỗi lớp sẽ tự tạo lại đúng 1 exam khi giáo viên chấm bài đầu tiên.
     */
    public function up(): void
    {
        DB::table('grades')->delete();
        DB::table('exams')->delete();

        Schema::table('exams', function (Blueprint $table) {
            $table->dropUnique(['class_id', 'date']);
            $table->dropColumn('date');
            $table->unique('class_id');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropUnique(['class_id']);
            $table->date('date')->nullable();
            $table->unique(['class_id', 'date']);
        });
    }
};
