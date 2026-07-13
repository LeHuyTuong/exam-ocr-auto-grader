<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yle_exams', function (Blueprint $table) {
            $table->id();
            $table->string('level'); // starters, movers, flyers
            $table->string('skill'); // listening, reading_writing
            $table->string('name');
            $table->unsignedTinyInteger('total_marks')->default(0);
            $table->unsignedTinyInteger('total_pages')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['level', 'skill']);
        });

        Schema::create('yle_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yle_exam_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('part_number');
            $table->string('title');
            $table->string('question_type'); // matching|fill_blank|mcq_abc|colouring|tick_cross|yes_no|word_from_box|one_word
            $table->boolean('is_auto_gradable')->default(false);
            $table->unsignedTinyInteger('max_marks')->default(0);
            $table->unsignedTinyInteger('page_number');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['yle_exam_id', 'part_number']);
        });

        Schema::create('yle_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yle_part_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('question_number');
            $table->string('prompt')->nullable();
            $table->string('correct_answer')->nullable();
            $table->json('accepted_variants')->nullable();
            $table->unsignedTinyInteger('points')->default(1);
            $table->timestamps();

            $table->unique(['yle_part_id', 'question_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yle_questions');
        Schema::dropIfExists('yle_parts');
        Schema::dropIfExists('yle_exams');
    }
};
