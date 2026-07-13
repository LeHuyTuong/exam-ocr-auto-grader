<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolClassFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('??-####'),
            'name' => fake()->randomElement(['Toán', 'Văn', 'Anh', 'Lý', 'Hoá', 'Sinh']),
            'level' => fake()->randomElement(['6', '7', '8', '9', '10', '11', '12']),
        ];
    }
}
