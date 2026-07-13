<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yle_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yle_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->date('exam_date');
            $table->string('ocr_raw_name')->nullable();
            $table->decimal('auto_score', 5, 2)->nullable();
            $table->decimal('manual_score', 5, 2)->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->decimal('max_score', 5, 2)->default(0);
            $table->string('status')->default('pending'); // pending|grading|auto_graded|completed|needs_review
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['yle_exam_id', 'class_id']);
            $table->index('student_id');
            $table->index('status');
        });

        Schema::create('yle_submission_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yle_submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('page_number');
            $table->string('image_url');
            $table->json('ai_raw_response')->nullable();
            $table->timestamps();

            $table->unique(['yle_submission_id', 'page_number']);
            $table->index('yle_submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yle_submission_pages');
        Schema::dropIfExists('yle_submissions');
    }
};
