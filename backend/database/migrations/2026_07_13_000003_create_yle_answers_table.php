<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yle_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yle_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('yle_question_id')->constrained()->cascadeOnDelete();
            $table->string('student_answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unsignedTinyInteger('marks_awarded')->default(0);
            $table->string('graded_by')->default('auto'); // auto|manual
            $table->decimal('ai_confidence', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['yle_submission_id', 'yle_question_id'], 'yle_answers_subm_question_unique');
            $table->index('yle_submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yle_answers');
    }
};
