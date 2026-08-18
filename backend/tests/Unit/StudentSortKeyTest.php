<?php

namespace Tests\Unit;

use App\Models\Student;
use PHPUnit\Framework\TestCase;

class StudentSortKeyTest extends TestCase
{
    /**
     * Ở Việt Nam gọi nhau bằng TÊN (chữ cuối), không phải họ (chữ đầu) — danh
     * sách lớp phải xếp "Khổng Đinh Ngọc Hân" ở vần H chứ không phải vần K.
     */
    public function test_sort_key_starts_with_given_name(): void
    {
        $this->assertSame('han khong dinh ngoc', Student::sortKeyFor('Khổng Đinh Ngọc Hân'));
        $this->assertSame('thu nguyen cao minh', Student::sortKeyFor('Nguyễn Cao Minh Thư'));
    }

    public function test_sort_key_handles_edge_cases(): void
    {
        $this->assertSame('', Student::sortKeyFor(null));
        $this->assertSame('', Student::sortKeyFor('   '));
        // Tên 1 chữ: chính nó là tên, không có phần họ đệm đi kèm.
        $this->assertSame('an', Student::sortKeyFor('An'));
    }

    /**
     * Danh sách lớp thật của giáo viên — thứ tự này là thứ tự phải thấy trên
     * màn hình điểm lẫn trong file Excel xuất ra.
     */
    public function test_real_class_list_sorts_by_given_name(): void
    {
        $names = [
            'Trịnh Hoàng Tuyết Trân',
            'Nguyễn Cao Minh Thư',
            'Khổng Đinh Ngọc Hân',
            'Nguyễn Tiến Đạt',
            'Đỗ Thị Hoa',
            'Trần Ngọc Hân',
            'Nguyễn Phi Yến',
            'Lê Hoàng Cường',
            'Trần Bảo Tín',
            'Phạm Võ Minh Ngọc',
            'Nguyễn Kim Quốc Bảo',
            'Trần Thị Bích',
            'Nguyễn Ngọc Bảo Trúc',
            'Nguyễn Thị Ngọc Hiếu',
            'Trần Ngọc Phương Thùy',
            'Phạm Minh Dung',
            'Nguyễn Thị Kim Thi',
            'Nguyễn Văn An',
        ];

        usort($names, fn ($a, $b) => Student::sortKeyFor($a) <=> Student::sortKeyFor($b));

        $this->assertSame([
            'Nguyễn Văn An',
            'Nguyễn Kim Quốc Bảo',
            'Trần Thị Bích',
            'Lê Hoàng Cường',
            'Nguyễn Tiến Đạt',
            'Phạm Minh Dung',
            // Trùng tên "Hân" thì xét tiếp họ + đệm: Khổng trước Trần.
            'Khổng Đinh Ngọc Hân',
            'Trần Ngọc Hân',
            'Nguyễn Thị Ngọc Hiếu',
            'Đỗ Thị Hoa',
            'Phạm Võ Minh Ngọc',
            'Nguyễn Thị Kim Thi',
            'Nguyễn Cao Minh Thư',
            'Trần Ngọc Phương Thùy',
            'Trần Bảo Tín',
            'Trịnh Hoàng Tuyết Trân',
            'Nguyễn Ngọc Bảo Trúc',
            'Nguyễn Phi Yến',
        ], $names);
    }
}
