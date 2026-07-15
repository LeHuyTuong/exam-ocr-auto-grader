<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            // Điểm thành phần Unit Test (vocabulary/grammar/listening/reading/
            // writing/speaking) — null với luồng đếm-câu-đúng cũ.
            $table->json('sub_scores')->nullable()->after('score');
            // Ảnh 2 (dải điểm cuối bài) khi chấm theo luồng 2-ảnh; image_url
            // gốc vẫn giữ ảnh 1 (tên) để không phá luồng cũ chỉ có 1 ảnh.
            $table->string('image_url_2')->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn(['sub_scores', 'image_url_2']);
        });
    }
};
