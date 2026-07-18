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

    /** true nếu $value KHÔNG đạt (dưới ngưỡng). null/thiếu key => false. */
    public static function isWeak(string $key, mixed $value): bool
    {
        $thresholds = self::thresholds();
        if (! isset($thresholds[$key])) {
            return false;
        }
        if ($value === null || $value === '') {
            return false;
        }

        return (float) $value < (float) $thresholds[$key]['pass'];
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
            if (array_key_exists($key, $subScores) && $subScores[$key] !== null && $subScores[$key] !== '') {
                if ((float) $subScores[$key] < (float) $cfg['pass']) {
                    $weak[] = $cfg['label'];
                }
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
            if (array_key_exists($key, $subScores) && $subScores[$key] !== null && $subScores[$key] !== '') {
                if ((float) $subScores[$key] < (float) $cfg['pass']) {
                    $weak[] = $key;
                }
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
            if (is_array($sub) && array_key_exists($key, $sub) && $sub[$key] !== null && $sub[$key] !== '') {
                $values[] = (float) $sub[$key];
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
            if (array_key_exists($key, $subScores) && $subScores[$key] !== null && $subScores[$key] !== '') {
                $total += (float) $subScores[$key];
                $has = true;
            }
        }

        return $has ? round($total, 2) : null;
    }
}
