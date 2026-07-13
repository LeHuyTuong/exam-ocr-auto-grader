<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'student_id' => Student::factory(),
            'class_id' => SchoolClass::factory(),
            'total_correct' => fake()->numberBetween(10, 50),
            'score' => fake()->randomFloat(2, 1, 10),
            'image_url' => fake()->url(),
            'ai_confidence' => fake()->randomFloat(2, 0.5, 1),
            'ocr_raw_name' => fake()->name(),
            'status' => 'confirmed',
            'confirmed_by' => User::factory(),
        ];
    }
}
