<?php

namespace App\Support;

use App\Models\SchoolClass;

/**
 * Thống kê cho 1 lớp — dùng cho trang chi tiết lớp, badge xu hướng ở list,
 * widget dashboard lớp.
 *
 * 2 khái niệm thống kê khác nhau (đừng nhầm):
 * - XU HƯỚNG THEO TỪNG ĐỀ: averageScoreByExam() gộp điểm theo exam_id — mỗi đề
 *   đã có grade = 1 điểm trên biểu đồ / 1 "lần" so sánh xu hướng. Dùng cho
 *   ClassScoreTrendChart, latestAverage(), trendPercent(), trendSummary().
 * - BỨC TRANH TỔNG QUAN: averageScore(), skillAverages(), weakStudents() gộp
 *   xuyên suốt mọi đề (lấy grade mới nhất mỗi học sinh / mọi grade) — phản ánh
 *   tình hình hiện tại của lớp chứ không phân biệt theo từng đề riêng biệt.
 */
class ClassStatsService
{
    public function __construct(private SchoolClass $class) {}

    /**
     * Điểm TB theo từng đề của lớp — mỗi phần tử = 1 đề đã có grade, kèm điểm TB
     * các grade của đề đó. Sắp xếp theo created_at của đề (cũ -> mới). Bỏ qua đề
     * chưa có grade nào (không đẩy điểm null vào chuỗi) — áp dụng cho cả biểu đồ
     * xu hướng lẫn latestAverage()/trendPercent() (so sánh 2 đề gần nhất đã có điểm).
     *
     * @return list<array{examId:int,examName:string,count:int,avg:float}>
     */
    public function averageScoreByExam(): array
    {
        $exams = $this->class->exams()
            ->orderBy('created_at')
            ->get(['id', 'name']);

        $averages = $this->class->grades()
            ->selectRaw('exam_id, COUNT(*) as count, AVG(score) as avg')
            ->whereIn('exam_id', $exams->pluck('id'))
            ->groupBy('exam_id')
            ->get()
            ->keyBy('exam_id');

        $sessions = [];
        foreach ($exams as $exam) {
            $row = $averages->get($exam->id);
            if ($row === null) {
                continue; // đề chưa có grade — bỏ qua
            }
            $sessions[] = [
                'examId' => $exam->id,
                'examName' => $exam->name,
                'count' => (int) $row->count,
                'avg' => round((float) $row->avg, 2),
            ];
        }

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

    /** Điểm TB của đề gần nhất đã có grade. */
    public function latestAverage(): ?float
    {
        $sessions = $this->averageScoreByExam();
        if (empty($sessions)) {
            return null;
        }

        return end($sessions)['avg'];
    }

    /**
     * % thay đổi điểm TB: đề gần nhất vs đề ngay trước đó (đều phải có grade).
     * null khi chưa có đủ 2 đề đã chấm (chưa thể so sánh) hoặc đề trước = 0.
     */
    public function trendPercent(): ?float
    {
        $sessions = $this->averageScoreByExam();
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
     * Tóm tắt xu hướng: latest = điểm TB đề gần nhất đã có grade,
     * trend = % thay đổi vs đề liền trước (null nếu < 2 đề đã chấm hoặc prev = 0).
     *
     * @return array{latest:?float,trend:?float}
     */
    public function trendSummary(): array
    {
        $sessions = $this->averageScoreByExam();
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
