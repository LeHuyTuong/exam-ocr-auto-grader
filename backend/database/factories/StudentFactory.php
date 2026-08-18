<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\FuzzyMatchService;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'full_name' => $name = fake()->name('vi_VN'),
            // Suy ra từ full_name (model cũng tự tính lại khi lưu) — trước đây
            // random một tên khác nên test sắp xếp theo tên chạy sai lung tung.
            'normalized_name' => (new FuzzyMatchService)->normalize($name),
            'sort_name' => Student::sortKeyFor($name),
            'aliases' => [],
        ];
    }
}
