<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Yle\YleExam;
use App\Models\Yle\YlePart;
use App\Models\Yle\YleQuestion;
use App\Support\YleTemplates;
use Illuminate\Database\Seeder;

class YleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = env('SEED_ADMIN_EMAIL', '');
        $admin = $adminEmail ? User::where('email', $adminEmail)->first() : null;

        if (! $admin) {
            $this->command->warn('Skipping YLE demo seed: no admin user found. Run DatabaseSeeder first or set SEED_ADMIN_EMAIL.');
            return;
        }

        $template = YleTemplates::get('starters', 'listening');
        if (! $template) {
            return;
        }

        $exam = YleExam::create([
            'level' => 'starters',
            'skill' => 'listening',
            'name' => $template['name'],
            'total_marks' => $template['total_marks'],
            'total_pages' => $template['total_pages'],
            'created_by' => $admin->id,
        ]);

        foreach ($template['parts'] as $partData) {
            $questions = $partData['questions'];
            unset($partData['questions']);

            $part = YlePart::create(array_merge($partData, [
                'yle_exam_id' => $exam->id,
            ]));

            foreach ($questions as $qData) {
                YleQuestion::create(array_merge($qData, [
                    'yle_part_id' => $part->id,
                ]));
            }
        }

        $this->command->info("Created demo YLE exam: {$exam->name} (ID: {$exam->id})");

        // Create R&W template too
        $rwTemplate = YleTemplates::get('starters', 'reading_writing');
        if ($rwTemplate) {
            $rwExam = YleExam::create([
                'level' => 'starters',
                'skill' => 'reading_writing',
                'name' => $rwTemplate['name'],
                'total_marks' => $rwTemplate['total_marks'],
                'total_pages' => $rwTemplate['total_pages'],
                'created_by' => $admin->id,
            ]);

            foreach ($rwTemplate['parts'] as $partData) {
                $questions = $partData['questions'];
                unset($partData['questions']);

                $part = YlePart::create(array_merge($partData, [
                    'yle_exam_id' => $rwExam->id,
                ]));

                foreach ($questions as $qData) {
                    YleQuestion::create(array_merge($qData, [
                        'yle_part_id' => $part->id,
                    ]));
                }
            }

            $this->command->info("Created demo YLE exam: {$rwExam->name} (ID: {$rwExam->id})");
        }
    }
}
