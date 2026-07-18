<?php

namespace App\Support;

use App\Models\SchoolClass;

/**
 * Thống kê cho 1 lớp — dùng cho trang chi tiết lớp, badge xu hướng ở list,
 * widget dashboard lớp.
 *
 * Lưu ý mô hình dữ liệu: "1 lớp = 1 exam liên tục" (unique class_id trên exams).
 * Mỗi lần chấm/regrade tạo grade mới (có created_at). Vậy "lần chấm thi" được
 * định nghĩa là một ĐỢT CHẤM theo ngày (group grades theo created_at date), chứ
 * KHÔNG phải theo exam.
 */
class ClassStatsService
{
    public function __construct(private SchoolClass $class) {}

    /**
     * Các đợt chấm theo ngày — mỗi phần tử = 1 ngày có grade, kèm điểm TB.
     * Sắp xếp tăng dần theo ngày (cũ -> mới).
     *
     * @return list<array{date:string,count:int,avg:?float}>
     */
    public function gradingSessionsByDate(): array
    {
        $grades = $this->class->grades()
            ->whereNotNull('created_at')
            ->orderBy('created_at')
            ->get(['score', 'created_at']);

        $grouped = $grades->groupBy(fn ($g) => $g->created_at?->format('Y-m-d'));

        $sessions = [];
        foreach ($grouped as $date => $items) {
            if ($date === null || $items->isEmpty()) {
                continue;
            }
            $sessions[] = [
                'date' => $date,
                'count' => $items->count(),
                'avg' => round((float) $items->avg('score'), 2),
            ];
        }
        usort($sessions, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $sessions;
    }

    /**
     * Điểm TB hiện tại của lớp = trung bình grade MỚI NHẤT của mỗi học sinh
     * (lấy grade có id lớn nhất per student — grade mới = id lớn khi regrade).
     */
    public function averageScore(): ?float
    {
        $latestGradeIds = $this->class->grades()
            ->selectRaw('MAX(id) as id')
            ->where('class_id', $this->class->id)
            ->groupBy('student_id')
            ->pluck('id');

        if ($latestGradeIds->isEmpty()) {
            return null;
        }

        $avg = $this->class->grades()
            ->whereIn('id', $latestGradeIds)
            ->avg('score');

        return $avg !== null ? round((float) $avg, 2) : null;
    }

    public function totalStudents(): int
    {
        return $this->class->students()->count();
    }

    public function totalExams(): int
    {
        return $this->class->exams()->count();
    }

    public function totalGrades(): int
    {
        return $this->class->grades()->count();
    }

    /** Điểm TB của đợt chấm gần nhất (có grade). */
    public function latestAverage(): ?float
    {
        $sessions = $this->gradingSessionsByDate();
        if (empty($sessions)) {
            return null;
        }

        return end($sessions)['avg'];
    }

    /**
     * % thay đổi điểm TB: đợt chấm gần nhất vs đợt chấm ngay trước đó.
     * null khi chưa có đủ 2 đợt chấm (chưa thể so sánh) hoặc đợt trước = 0.
     */
    public function trendPercent(): ?float
    {
        $sessions = $this->gradingSessionsByDate();
        if (count($sessions) < 2) {
            return null;
        }
        $latest = end($sessions)['avg'];
        $prev = $sessions[count($sessions) - 2]['avg'];
        if ($prev == 0) {
            return null;
        }

        return round((($latest - $prev) / $prev) * 100, 1);
    }

    /**
     * Tóm tắt xu hướng: latest = điểm TB đợt chấm mới nhất,
     * trend = % thay đổi vs đợt chấm liền trước (null nếu < 2 đợt hoặc prev = 0).
     *
     * @return array{latest:?float,trend:?float}
     */
    public function trendSummary(): array
    {
        $sessions = $this->gradingSessionsByDate();
        if (empty($sessions)) {
            return ['latest' => null, 'trend' => null];
        }
        $latest = end($sessions)['avg'];
        $trend = null;
        if (count($sessions) >= 2) {
            $prev = $sessions[count($sessions) - 2]['avg'];
            if ($prev != 0) {
                $trend = round((($latest - $prev) / $prev) * 100, 1);
            }
        }

        return ['latest' => $latest, 'trend' => $trend];
    }

    /**
     * Điểm TB từng kỹ năng của lớp + đánh giá đạt/yếu theo ngưỡng.
     * Tính trên tất cả grade có sub_scores (mỗi grade = 1 lần chấm).
     *
     * @return array<string,array{label:string,max:int,pass:int,avg:?float,weak:bool}>
     */
    public function skillAverages(): array
    {
        $subScoresList = $this->class->grades()
            ->whereNotNull('sub_scores')
            ->pluck('sub_scores')
            ->filter()
            ->values();

        $result = [];
        foreach (SkillAssessment::thresholds() as $key => $cfg) {
            $avg = SkillAssessment::averageSkill($key, $subScoresList);
            $result[$key] = [
                'label' => $cfg['label'],
                'max' => $cfg['max'],
                'pass' => $cfg['pass'],
                'avg' => $avg,
                'weak' => $avg !== null && SkillAssessment::isWeak($key, $avg),
            ];
        }

        return $result;
    }

    /**
     * Học sinh cần hỗ trợ: có >=1 kỹ năng dưới ngưỡng (tính trên điểm TB các lần chấm
     * trong lớp). Sắp xếp: nhiều kỹ năng yếu lên đầu.
     *
     * @return list<array{id:int,full_name:string,avg_score:?float,weak_skills:list<string>,weak_skills_labels:list<string>}>
     */
    public function weakStudents(): array
    {
        $students = $this->class->students()
            ->with('grades')
            ->orderBy('full_name')
            ->get();

        $weak = [];
        foreach ($students as $student) {
            $grades = $student->grades;
            if ($grades->isEmpty()) {
                continue;
            }

            $weakKeys = [];
            foreach (SkillAssessment::thresholds() as $key => $cfg) {
                $vals = $grades->pluck('sub_scores')
                    ->filter()
                    ->map(fn ($s) => $s[$key] ?? null)
                    ->filter()
                    ->values();
                if ($vals->isNotEmpty()) {
                    $avg = round((float) $vals->avg(), 2);
                    if (SkillAssessment::isWeak($key, $avg)) {
                        $weakKeys[] = $key;
                    }
                }
            }

            if (empty($weakKeys)) {
                continue;
            }

            $latestGrade = $grades->sortByDesc('id')->first();
            $labels = array_map(fn ($k) => SkillAssessment::thresholds()[$k]['label'], $weakKeys);
            $weak[] = [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'avg_score' => $latestGrade ? round((float) $latestGrade->score, 2) : null,
                'weak_skills' => $weakKeys,
                'weak_skills_labels' => $labels,
            ];
        }

        usort($weak, fn ($a, $b) => count($b['weak_skills']) <=> count($a['weak_skills']));

        return $weak;
    }

    /**
     * Điểm TB theo từng kỹ năng của 1 học sinh (trong lớp) — cho tab Học sinh.
     *
     * @return array<string,?float>
     */
    public function studentSkillAverages(int $studentId): array
    {
        $subScoresList = $this->class->grades()
            ->where('student_id', $studentId)
            ->whereNotNull('sub_scores')
            ->pluck('sub_scores')
            ->filter()
            ->values();

        $result = [];
        foreach (SkillAssessment::thresholds() as $key => $cfg) {
            $result[$key] = SkillAssessment::averageSkill($key, $subScoresList);
        }

        return $result;
    }
}
