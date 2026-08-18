<?php

use App\Models\Student;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Khoá sắp xếp danh sách lớp theo TÊN (chữ cuối) thay vì họ — đúng cách gọi
     * tên ở Việt Nam: "Khổng Đinh Ngọc Hân" nằm ở vần H chứ không phải vần K.
     * Tính sẵn ra cột riêng để ORDER BY chạy được ngay trong SQL (danh sách điểm
     * có phân trang, không sắp lại được ở tầng PHP).
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('sort_name')->default('')->after('normalized_name')->index();
        });

        DB::table('students')->orderBy('id')->chunkById(200, function ($students) {
            foreach ($students as $student) {
                DB::table('students')
                    ->where('id', $student->id)
                    ->update(['sort_name' => Student::sortKeyFor($student->full_name)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['sort_name']);
            $table->dropColumn('sort_name');
        });
    }
};
