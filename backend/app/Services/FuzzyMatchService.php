<?php

namespace App\Services;

use App\Models\Student;

class FuzzyMatchService
{
    public function findCandidates(string $rawName, int $classId): array
    {
        $normalized = $this->normalize($rawName);
        $students = Student::where('class_id', $classId)->get();

        $candidates = [];

        foreach ($students as $student) {
            $bestSimilarity = 0;

            $namesToCheck = [$student->normalized_name];

            if ($student->aliases) {
                foreach ($student->aliases as $alias) {
                    $namesToCheck[] = $this->normalize($alias);
                }
            }

            foreach ($namesToCheck as $name) {
                $sim = $this->similarity($normalized, $name);
                if ($sim > $bestSimilarity) {
                    $bestSimilarity = $sim;
                }
            }

            if ($bestSimilarity > 0) {
                $candidates[] = [
                    'studentId' => $student->id,
                    'fullName' => $student->full_name,
                    'similarity' => round($bestSimilarity, 4),
                ];
            }
        }

        usort($candidates, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($candidates, 0, 5);
    }

    public function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');

        $charMap = [
            'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
            'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
            'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
            'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
            'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
            'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
            'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
            'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
            'đ' => 'd',
        ];

        $name = strtr($name, $charMap);
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    public function similarity(string $a, string $b): float
    {
        $a = trim(preg_replace('/\s+/', ' ', $a));
        $b = trim(preg_replace('/\s+/', ' ', $b));

        if ($a === $b) {
            return 1.0;
        }

        $lev = levenshtein($a, $b);
        $maxLen = max(mb_strlen($a), mb_strlen($b));

        if ($maxLen === 0) {
            return 1.0;
        }

        return 1 - ($lev / $maxLen);
    }
}
