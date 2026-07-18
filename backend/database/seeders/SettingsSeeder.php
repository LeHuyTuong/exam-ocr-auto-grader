<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seed cài đặt mặc định cho bảng settings — đảm bảo YLE mặc định TẮT trên
 * production ngay khi deploy (trang Cài đặt vẫn cho admin bật lại bất cứ lúc nào).
 * Idempotent: chỉ tạo row nếu chưa có.
 */
class SettingsSeeder extends Seeder
{
    /** @var array<string,bool> */
    private const DEFAULTS = [
        'navigation.school_classes' => true,
        'navigation.students' => true,
        'navigation.exams' => true,
        'navigation.grades' => true,
        'navigation.users' => true,
        'navigation.yle_exams' => false,
        'navigation.yle_submissions' => false,
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
