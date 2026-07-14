<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yle_submission_pages', function (Blueprint $table) {
            // Cho phép null để khi lưu ảnh (Cloudinary) thất bại vẫn ghi được trang,
            // tránh 500 do vi phạm ràng buộc NOT NULL.
            $table->string('image_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('yle_submission_pages', function (Blueprint $table) {
            $table->string('image_url')->nullable(false)->change();
        });
    }
};
