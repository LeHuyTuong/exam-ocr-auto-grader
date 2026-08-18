<?php

namespace App\Support;

/**
 * Đánh giá kỹ năng theo khung ngưỡng cố định (config/skills.php).
 *
 * Tương đương công thức Excel:
 *   =TEXTJOIN(", ";TRUE;IF(vocab<9;"Từ vựng";"");IF(gram<9;"Ngữ pháp";"");...)
 *
 * Một kỹ năng chỉ bị đánh dấu yếu khi CÓ điểm và điểm đó < ngưỡng `pass`.
 * Kỹ năng chưa chấm (null / thiếu key) thì bỏ qua (không tính yếu).
 */
class SkillAssessment
{
    /** @return array<string, array{label:string,max:int,pass:int}> */
    public static function thresholds(): array
    {
        return config('skills.thresholds', []);
    }

    /** @return array<string, string> key => label */
    public static function labels(): array
    {
        return array_map(fn (array $cfg) => $cfg['label'], self::thresholds());
    }

    /** Thứ tự cột export (khớp template giáo viên). @return list<string> */
    public static function exportOrder(): array
    {
        return array_values(config('skills.export_order', array_keys(self::thresholds())));
    }

    public static function totalMax(): int
    {
        return (int) config('skills.total_max', 50);
    }

    /**
     * Đọc 1 ô điểm về float, chấp nhận cả "7,5" (giáo viên/AI ghi kiểu Việt) lẫn
     * "7.5". (float) "7,5" trong PHP ra 7.0 — mất nửa điểm mà không báo lỗi gì,
     * nên mọi chỗ đọc điểm đều phải đi qua đây.
     */
    public static function toScore(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /** true nếu $value KHÔNG đạt (dưới ngưỡng). null/thiếu key => false. */
    public static function isWeak(string $key, mixed $value): bool
    {
        $thresholds = self::thresholds();
        if (! isset($thresholds[$key])) {
            return false;
        }
        $score = self::toScore($value);
        if ($score === null) {
            return false;
        }

        return $score < (float) $thresholds[$key]['pass'];
    }

    /**
     * Trả về list label các kỹ năng yếu.
     *
     * @param  array<string,mixed>|null  $subScores
     * @return list<string>
     */
    public static function weakSkillsLabels(?array $subScores): array
    {
        if (empty($subScores)) {
            return [];
        }
        $weak = [];
        foreach (self::thresholds() as $key => $cfg) {
            $score = self::toScore($subScores[$key] ?? null);
            if ($score !== null && $score < (float) $cfg['pass']) {
                $weak[] = $cfg['label'];
            }
        }

        return $weak;
    }

    /**
     * Tương đương TEXTJOIN(", "; TRUE; ...): join label kỹ năng yếu, rỗng nếu đạt hết.
     *
     * @param  array<string,mixed>|null  $subScores
     */
    public static function weakSkillsText(?array $subScores): string
    {
        return implode(', ', self::weakSkillsLabels($subScores));
    }

    /** Trả về list KEY các kỹ năng yếu (dùng cho badge/UI). @param array<string,mixed>|null $subScores @return list<string> */
    public static function weakSkillKeys(?array $subScores): array
    {
        if (empty($subScores)) {
            return [];
        }
        $weak = [];
        foreach (self::thresholds() as $key => $cfg) {
            $score = self::toScore($subScores[$key] ?? null);
            if ($score !== null && $score < (float) $cfg['pass']) {
                $weak[] = $key;
            }
        }

        return $weak;
    }

    /**
     * Trung bình cộng 1 kỹ năng qua một tập sub_scores.
     *
     * @param  iterable<array<string,mixed>|null>  $subScoresList
     */
    public static function averageSkill(string $key, iterable $subScoresList): ?float
    {
        $values = [];
        foreach ($subScoresList as $sub) {
            $score = is_array($sub) ? self::toScore($sub[$key] ?? null) : null;
            if ($score !== null) {
                $values[] = $score;
            }
        }
        if (empty($values)) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }

    /** Tổng 6 kỹ năng từ sub_scores; null nếu sub_scores rỗng/không có điểm nào. @param array<string,mixed>|null $subScores */
    public static function totalFromSubScores(?array $subScores): ?float
    {
        if (empty($subScores)) {
            return null;
        }
        $total = 0.0;
        $has = false;
        foreach (self::thresholds() as $key => $cfg) {
            $score = self::toScore($subScores[$key] ?? null);
            if ($score !== null) {
                $total += $score;
                $has = true;
            }
        }

        return $has ? round($total, 2) : null;
    }
}
