<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->integer('total_correct');
            $table->decimal('score', 5, 2);
            $table->string('image_url')->nullable();
            $table->decimal('ai_confidence', 5, 2)->nullable();
            $table->string('ocr_raw_name')->nullable();
            $table->string('status')->default('confirmed');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
