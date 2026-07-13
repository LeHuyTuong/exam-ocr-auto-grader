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
}
