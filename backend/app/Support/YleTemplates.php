<?php

namespace App\Support;

class YleTemplates
{
    public static function all(): array
    {
        return [
            'starters' => [
                'listening' => self::startersListening(),
                'reading_writing' => self::startersReadingWriting(),
                'speaking' => self::speaking('Starters'),
            ],
            'movers' => [
                'listening' => self::moversListening(),
                'reading_writing' => self::moversReadingWriting(),
                'speaking' => self::speaking('Movers'),
            ],
            'flyers' => [
                'listening' => self::flyersListening(),
                'reading_writing' => self::flyersReadingWriting(),
                'speaking' => self::speaking('Flyers'),
            ],
        ];
    }

    public static function get(string $level, string $skill): ?array
    {
        return data_get(self::all(), "$level.$skill");
    }

    public static function startersListening(): array
    {
        return [
            'name' => 'Starters Listening',
            'total_marks' => 20,
            'total_pages' => 3,
            'parts' => [
                [
                    'part_number' => 1,
                    'title' => 'Part 1 — Nối tên với hình',
                    'question_type' => 'matching',
                    'is_auto_gradable' => false,
                    'max_marks' => 5,
                    'page_number' => 1,
                    'sort_order' => 1,
                    'questions' => [
                        ['question_number' => 1, 'prompt' => null, 'points' => 1],
                        ['question_number' => 2, 'prompt' => null, 'points' => 1],
                        ['question_number' => 3, 'prompt' => null, 'points' => 1],
                        ['question_number' => 4, 'prompt' => null, 'points' => 1],
                        ['question_number' => 5, 'prompt' => null, 'points' => 1],
                    ],
                ],
                [
                    'part_number' => 2,
                    'title' => 'Part 2 — Nghe và viết tên hoặc số',
                    'question_type' => 'fill_blank',
                    'is_auto_gradable' => true,
                    'max_marks' => 5,
                    'page_number' => 2,
                    'sort_order' => 2,
                    'questions' => [
                        ['question_number' => 1, 'prompt' => 'Question 1', 'points' => 1],
                        ['question_number' => 2, 'prompt' => 'Question 2', 'points' => 1],
                        ['question_number' => 3, 'prompt' => 'Question 3', 'points' => 1],
                        ['question_number' => 4, 'prompt' => 'Question 4', 'points' => 1],
                        ['question_number' => 5, 'prompt' => 'Question 5', 'points' => 1],
                    ],
                ],
                [
                    'part_number' => 3,
                    'title' => 'Part 3 — Tick (✓) A, B or C',
                    'question_type' => 'mcq_abc',
                    'is_auto_gradable' => true,
                    'max_marks' => 5,
                    'page_number' => 2,
                    'sort_order' => 3,
                    'questions' => [
                        ['question_number' => 1, 'prompt' => null, 'points' => 1],
                        ['question_number' => 2, 'prompt' => null, 'points' => 1],
                        ['question_number' => 3, 'prompt' => null, 'points' => 1],
                        ['question_number' => 4, 'prompt' => null, 'points' => 1],
                        ['question_number' => 5, 'prompt' => null, 'points' => 1],
                    ],
                ],
                [
                    'part_number' => 4,
                    'title' => 'Part 4 — Nghe và tô màu',
                    'question_type' => 'colouring',
                    'is_auto_gradable' => false,
                    'max_marks' => 5,
                    'page_number' => 3,
                    'sort_order' => 4,
                    'questions' => [
                        ['question_number' => 1, 'prompt' => null, 'points' => 1],
                        ['question_number' => 2, 'prompt' => null, 'points' => 1],
                        ['question_number' => 3, 'prompt' => null, 'points' => 1],
                        ['question_number' => 4, 'prompt' => null, 'points' => 1],
                        ['question_number' => 5, 'prompt' => null, 'points' => 1],
                    ],
                ],
            ],
        ];
    }

    public static function startersReadingWriting(): array
    {
        return [
            'name' => 'Starters Reading & Writing',
            'total_marks' => 25,
            'total_pages' => 5,
            'parts' => [
                [
                    'part_number' => 1,
                    'title' => 'Part 1 — Tick (✓) or Cross (✗)',
                    'question_type' => 'tick_cross',
                    'is_auto_gradable' => true,
                    'max_marks' => 5,
                    'page_number' => 1,
                    'sort_order' => 1,
                    'questions' => [
                        ['question_number' => 1, 'prompt' => null, 'points' => 1],
                        ['question_number' => 2, 'prompt' => null, 'points' => 1],
                        ['question_number' => 3, 'prompt' => null, 'points' => 1],
                        ['question_number' => 4, 'prompt' => null, 'points' => 1],
                        ['question_number' => 5, 'prompt' => null, 'points' => 1],
                    ],
                ],
                [
                    'part_number' => 2,
                    'title' => 'Part 2 — Viết yes hoặc no',
                    'question_type' => 'yes_no',
                    'is_auto_gradable' => true,
                    'max_marks' => 5,
                    'page_number' => 2,
                    'sort_order' => 2,
                    'questions' => [
                        ['question_number' => 1, 'prompt' => null, 'points' => 1],
                        ['question_number' => 2, 'prompt' => null, 'points' => 1],
                        ['question_number' => 3, 'prompt' => null, 'points' => 1],
                        ['question_number' => 4, 'prompt' => null, 'points' => 1],
                        ['question_number' => 5, 'prompt' => null, 'points' => 1],
                    ],
                ],
                [
                    'part_number' => 3,
                    'title' => 'Part 3 — Sắp xếp chữ cái thành từ',
                    'question_type' => 'word_order',
                    'is_auto_gradable' => true,
                    'max_marks' => 5,
                    'page_number' => 3,
                    'sort_order' => 3,
                    'questions' => [
                        ['question_number' => 1, 'prompt' => null, 'points' => 1],
                        ['question_number' => 2, 'prompt' => null, 'points' => 1],
                        ['question_number' => 3, 'prompt' => null, 'points' => 1],
                        ['question_number' => 4, 'prompt' => null, 'points' => 1],
                        ['question_number' => 5, 'prompt' => null, 'points' => 1],
                    ],
                ],
                [
                    'part_number' => 4,
                    'title' => 'Part 4 — Chọn từ trong khung điền vào chỗ trống',
                    'question_type' => 'word_from_box',
                    'is_auto_gradable' => true,
                    'max_marks' => 5,
                    'page_number' => 4,
                    'sort_order' => 4,
                    'questions' => [
                        ['question_number' => 1, 'prompt' => null, 'points' => 1],
                        ['question_number' => 2, 'prompt' => null, 'points' => 1],
                        ['question_number' => 3, 'prompt' => null, 'points' => 1],
                        ['question_number' => 4, 'prompt' => null, 'points' => 1],
                        ['question_number' => 5, 'prompt' => null, 'points' => 1],
                    ],
                ],
                [
                    'part_number' => 5,
                    'title' => 'Part 5 — Đọc và trả lời 1 từ',
                    'question_type' => 'one_word',
                    'is_auto_gradable' => true,
                    'max_marks' => 5,
                    'page_number' => 5,
                    'sort_order' => 5,
                    'questions' => [
                        ['question_number' => 1, 'prompt' => null, 'points' => 1],
                        ['question_number' => 2, 'prompt' => null, 'points' => 1],
                        ['question_number' => 3, 'prompt' => null, 'points' => 1],
                        ['question_number' => 4, 'prompt' => null, 'points' => 1],
                        ['question_number' => 5, 'prompt' => null, 'points' => 1],
                    ],
                ],
            ],
        ];
    }

    public static function moversListening(): array
    {
        return [
            'name' => 'Movers Listening',
            'total_marks' => 25,
            'total_pages' => 5,
            'parts' => [
                [
                    'part_number' => 1,
                    'title' => 'Part 1 — Nghe và nối tên với hình',
                    'question_type' => 'matching',
                    'is_auto_gradable' => false,
                    'max_marks' => 5,
                    'page_number' => 1,
                    'sort_order' => 1,
                    'questions' => self::numberedQuestions(5),
                ],
                [
                    'part_number' => 2,
                    'title' => 'Part 2 — Nghe và điền vào phiếu thông tin',
                    'question_type' => 'fill_blank',
                    'is_auto_gradable' => true,
                    'max_marks' => 5,
                    'page_number' => 2,
                    'sort_order' => 2,
                    'questions' => self::numberedQuestions(5),
                ],
                [
                    'part_number' => 3,
                    'title' => 'Part 3 — Nghe và nối chữ cái (A-H)',
                    'question_type' => 'match_letter',
                    'is_auto_gradable' => true,
                    'max_marks' => 5,
                    'page_number' => 3,
                    'sort_order' => 3,
                    'questions' => self::numberedQuestions(5),
                ],
                [
                    'part_number' => 4,
                    'title' => 'Part 4 — Nghe và tick (✓) A, B hoặc C',
                    'question_type' => 'mcq_abc',
                    'is_auto_gradable' => true,
                    'max_marks' => 5,
                    'page_number' => 4,
                    'sort_order' => 4,
                    'questions' => self::numberedQuestions(5),
                ],
                [
                    'part_number' => 5,
                    'title' => 'Part 5 — Nghe, tô màu và viết',
                    'question_type' => 'colouring',
                    'is_auto_gradable' => false,
                    'max_marks' => 5,
                    'page_number' => 5,
                    'sort_order' => 5,
                    'questions' => self::numberedQuestions(5),
                ],
            ],
        ];
    }

    public static function flyersListening(): array
    {
        return [
            'name' => 'Flyers Listening',
            'total_marks' => 25,
            'total_pages' => 5,
            'parts' => [
                [
                    'part_number' => 1,
                    'title' => 'Part 1 — Nghe và nối tên với hình',
                    'question_type' => 'matching',
                    'is_auto_gradable' => false,
                    'max_marks' => 5,
                    'page_number' => 1,
                    'sort_order' => 1,
                    'questions' => self::numberedQuestions(5),
                ],
                [
                    'part_number' => 2,
                    'title' => 'Part 2 — Nghe và điền vào phiếu thông tin',
                    'question_type' => 'fill_blank',
                    'is_auto_gradable' => true,
                    'max_marks' => 5,
                    'page_number' => 2,
                    'sort_order' => 2,
                    'questions' => self::numberedQuestions(5),
                ],
                [
                    'part_number' => 3,
                    'title' => 'Part 3 — Nghe và nối chữ cái (A-H)',
                    'question_type' => 'match_letter',
                    'is_auto_gradable' => true,
                    'max_marks' => 5,
                    'page_number' => 3,
                    'sort_order' => 3,
                    'questions' => self::numberedQuestions(5),
                ],
                [
                    'part_number' => 4,
                    'title' => 'Part 4 — Nghe và tick (✓) A, B hoặc C',
                    'question_type' => 'mcq_abc',
                    'is_auto_gradable' => true,
                    'max_marks' => 5,
                    'page_number' => 4,
                    'sort_order' => 4,
                    'questions' => self::numberedQuestions(5),
                ],
                [
                    'part_number' => 5,
                    'title' => 'Part 5 — Nghe và điền từ còn thiếu',
                    'question_type' => 'fill_blank',
                    'is_auto_gradable' => true,
                    'max_marks' => 5,
                    'page_number' => 5,
                    'sort_order' => 5,
                    'questions' => self::numberedQuestions(5),
                ],
            ],
        ];
    }

    public static function moversReadingWriting(): array
    {
        return [
            'name' => 'Movers Reading & Writing',
            'total_marks' => 35,
            'total_pages' => 6,
            'parts' => [
                [
                    'part_number' => 1,
                    'title' => 'Part 1 — Đọc định nghĩa, chọn từ trong khung',
                    'question_type' => 'word_from_box',
                    'is_auto_gradable' => true,
                    'max_marks' => 6,
                    'page_number' => 1,
                    'sort_order' => 1,
                    'questions' => self::numberedQuestions(6),
                ],
                [
                    'part_number' => 2,
                    'title' => 'Part 2 — Đọc và viết yes hoặc no',
                    'question_type' => 'yes_no',
                    'is_auto_gradable' => true,
                    'max_marks' => 6,
                    'page_number' => 2,
                    'sort_order' => 2,
                    'questions' => self::numberedQuestions(6),
                ],
                [
                    'part_number' => 3,
                    'title' => 'Part 3 — Hoàn thành hội thoại (chọn chữ cái)',
                    'question_type' => 'match_letter',
                    'is_auto_gradable' => true,
                    'max_marks' => 5,
                    'page_number' => 3,
                    'sort_order' => 3,
                    'questions' => self::numberedQuestions(5),
                ],
                [
                    'part_number' => 4,
                    'title' => 'Part 4 — Đọc và tick (✓) A, B hoặc C',
                    'question_type' => 'mcq_abc',
                    'is_auto_gradable' => true,
                    'max_marks' => 6,
                    'page_number' => 4,
                    'sort_order' => 4,
                    'questions' => self::numberedQuestions(6),
                ],
                [
                    'part_number' => 5,
                    'title' => 'Part 5 — Đọc câu chuyện, điền từ còn thiếu',
                    'question_type' => 'fill_blank',
                    'is_auto_gradable' => true,
                    'max_marks' => 6,
                    'page_number' => 5,
                    'sort_order' => 5,
                    'questions' => self::numberedQuestions(6),
                ],
                [
                    'part_number' => 6,
                    'title' => 'Part 6 — Trả lời câu hỏi về bức tranh (1 từ)',
                    'question_type' => 'one_word',
                    'is_auto_gradable' => true,
                    'max_marks' => 6,
                    'page_number' => 6,
                    'sort_order' => 6,
                    'questions' => self::numberedQuestions(6),
                ],
            ],
        ];
    }

    public static function flyersReadingWriting(): array
    {
        return [
            'name' => 'Flyers Reading & Writing',
            'total_marks' => 44,
            'total_pages' => 7,
            'parts' => [
                [
                    'part_number' => 1,
                    'title' => 'Part 1 — Đọc định nghĩa, chọn từ trong khung',
                    'question_type' => 'word_from_box',
                    'is_auto_gradable' => true,
                    'max_marks' => 7,
                    'page_number' => 1,
                    'sort_order' => 1,
                    'questions' => self::numberedQuestions(7),
                ],
                [
                    'part_number' => 2,
                    'title' => 'Part 2 — Điền từ trong khung vào đoạn văn',
                    'question_type' => 'word_from_box',
                    'is_auto_gradable' => true,
                    'max_marks' => 7,
                    'page_number' => 2,
                    'sort_order' => 2,
                    'questions' => self::numberedQuestions(7),
                ],
                [
                    'part_number' => 3,
                    'title' => 'Part 3 — Hoàn thành hội thoại (chọn chữ cái A-H)',
                    'question_type' => 'match_letter',
                    'is_auto_gradable' => true,
                    'max_marks' => 7,
                    'page_number' => 3,
                    'sort_order' => 3,
                    'questions' => self::numberedQuestions(7),
                ],
                [
                    'part_number' => 4,
                    'title' => 'Part 4 — Đọc đoạn văn, chọn Đúng/Sai',
                    'question_type' => 'yes_no',
                    'is_auto_gradable' => true,
                    'max_marks' => 7,
                    'page_number' => 4,
                    'sort_order' => 4,
                    'questions' => self::numberedQuestions(7),
                ],
                [
                    'part_number' => 5,
                    'title' => 'Part 5 — Đọc và chọn từ đúng A, B hoặc C',
                    'question_type' => 'mcq_abc',
                    'is_auto_gradable' => true,
                    'max_marks' => 7,
                    'page_number' => 5,
                    'sort_order' => 5,
                    'questions' => self::numberedQuestions(7),
                ],
                [
                    'part_number' => 6,
                    'title' => 'Part 6 — Đọc câu chuyện, điền 1 từ vào chỗ trống',
                    'question_type' => 'one_word',
                    'is_auto_gradable' => true,
                    'max_marks' => 7,
                    'page_number' => 6,
                    'sort_order' => 6,
                    'questions' => self::numberedQuestions(7),
                ],
                [
                    'part_number' => 7,
                    'title' => 'Part 7 — Viết đoạn văn ngắn theo tranh gợi ý',
                    'question_type' => 'free_writing',
                    'is_auto_gradable' => false,
                    'max_marks' => 2,
                    'page_number' => 7,
                    'sort_order' => 7,
                    'questions' => self::numberedQuestions(2),
                ],
            ],
        ];
    }

    /**
     * A Speaking "paper" has no scannable pages — the teacher enters one
     * holistic score (0–5) directly after the oral test, no answer key needed.
     */
    public static function speaking(string $levelLabel): array
    {
        return [
            'name' => "{$levelLabel} Speaking",
            'total_marks' => 5,
            'total_pages' => 0,
            'parts' => [
                [
                    'part_number' => 1,
                    'title' => 'Speaking — Điểm tổng (giám khảo chấm trực tiếp)',
                    'question_type' => 'speaking',
                    'is_auto_gradable' => false,
                    'max_marks' => 5,
                    'page_number' => 1,
                    'sort_order' => 1,
                    'questions' => [],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{question_number: int, prompt: null, points: int}>
     */
    private static function numberedQuestions(int $count): array
    {
        return array_map(
            fn (int $i) => ['question_number' => $i, 'prompt' => null, 'points' => 1],
            range(1, $count)
        );
    }
}
