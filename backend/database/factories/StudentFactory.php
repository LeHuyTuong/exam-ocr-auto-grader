<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'full_name' => fake()->name('vi_VN'),
            'normalized_name' => fake()->name('vi_VN'),
            'aliases' => [],
        ];
    }
}
