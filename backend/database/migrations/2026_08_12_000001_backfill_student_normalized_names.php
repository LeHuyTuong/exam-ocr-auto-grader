<?php

use App\Services\FuzzyMatchService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tính lại normalized_name cho mọi học sinh đang có.
     *
     * Học sinh thêm từ trang quản trị trước đây lấy nguyên tên CÓ DẤU làm khoá
     * (form fallback về full_name), nên danh sách sắp ABC bị "Đỗ"/"Ân" rơi xuống
     * cuối và dò tên OCR kém chính xác. Chỉ ghi lại một cột phái sinh nên chạy
     * lại nhiều lần vẫn an toàn.
     */
    public function up(): void
    {
        $normalizer = new FuzzyMatchService;

        DB::table('students')->orderBy('id')->chunkById(200, function ($students) use ($normalizer) {
            foreach ($students as $student) {
                $normalized = $normalizer->normalize($student->full_name);

                if ($normalized !== $student->normalized_name) {
                    DB::table('students')
                        ->where('id', $student->id)
                        ->update(['normalized_name' => $normalized]);
                }
            }
        });
    }

    /**
     * Không khôi phục được khoá cũ (đã là dữ liệu sai) và cũng không cần —
     * normalized_name luôn tự sinh lại từ full_name khi lưu.
     */
    public function down(): void {}
};
