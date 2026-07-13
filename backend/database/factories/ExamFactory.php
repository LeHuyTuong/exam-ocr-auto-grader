<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'name' => 'Bài thi '.fake()->date('d/m/Y'),
            'date' => today(),
            'total_questions' => fake()->randomElement([20, 30, 40, 50]),
            'max_score' => 10,
            'created_by' => User::factory(),
        ];
    }
}
