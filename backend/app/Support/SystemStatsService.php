<?php

namespace App\Support;

use App\Models\Exam;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Thống kê toàn hệ thống — dùng cho dashboard chính.
 */
class SystemStatsService
{
    public function totalClasses(): int
    {
        return SchoolClass::count();
    }

    public function totalStudents(): int
    {
        return Student::count();
    }

    public function totalExams(): int
    {
        return Exam::count();
    }

    public function totalGraded(): int
    {
        return Grade::count();
    }

    /**
     * % cải thiện điểm toàn hệ thống: trung bình % thay đổi điểm TB
     * (bài mới nhất vs bài liền trước) của CÁC LỚP có đủ >=2 bài đã chấm.
     * null khi chưa có lớp nào đủ dữ liệu.
     */
    public function improvementPercent(): ?float
    {
        $percents = [];
        foreach (SchoolClass::all() as $class) {
            $p = (new ClassStatsService($class))->trendPercent();
            if ($p !== null) {
                $percents[] = $p;
            }
        }
        if (empty($percents)) {
            return null;
        }

        return round(array_sum($percents) / count($percents), 1);
    }

    /**
     * Điểm TB toàn hệ thống theo tháng (dựa created_at của grade) — cho line chart.
     * Dùng collection thay vì DB-specific to_char() để tương thích cả SQLite & Postgres.
     *
     * @return list<array{month:string,avg:?float,count:int}>
     */
    public function scoreTrendByMonth(): array
    {
        $grades = Grade::query()
            ->whereNotNull('created_at')
            ->orderBy('created_at')
            ->get(['score', 'created_at']);

        $grouped = $grades->groupBy(fn ($g) => $g->created_at?->format('Y-m'));

        $rows = [];
        foreach ($grouped as $month => $items) {
            if ($month === null || $items->isEmpty()) {
                continue;
            }
            $rows[] = [
                'month' => $month,
                'avg' => round((float) $items->avg('score'), 2),
                'count' => $items->count(),
            ];
        }
        usort($rows, fn ($a, $b) => strcmp($a['month'], $b['month']));

        return $rows;
    }

    /**
     * Bảng xếp hạng lớp theo % tiến bộ — cho bảng dashboard hệ thống.
     *
     * @return list<array{id:int,code:string,name:string,level:?string,students_count:int,trend:?float,latest_avg:?float}>
     */
    public function classImprovementRanking(): array
    {
        $rows = [];
        foreach (SchoolClass::withCount('students')->get() as $class) {
            $svc = new ClassStatsService($class);
            $rows[] = [
                'id' => $class->id,
                'code' => $class->code,
                'name' => $class->name,
                'level' => $class->level,
                'students_count' => $class->students_count,
                'trend' => $svc->trendPercent(),
                'latest_avg' => $svc->latestAverage(),
            ];
        }
        // Tiến bộ giảm dần; lớp chưa đủ dữ liệu đẩy xuống cuối.
        usort($rows, function ($a, $b) {
            $at = $a['trend'];
            $bt = $b['trend'];
            if ($at === null && $bt === null) {
                return 0;
            }
            if ($at === null) {
                return 1;
            }
            if ($bt === null) {
                return -1;
            }

            return $bt <=> $at;
        });

        return $rows;
    }
}
