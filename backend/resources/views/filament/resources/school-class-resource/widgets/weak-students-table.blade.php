<x-filament-widgets::widget>
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Học sinh cần hỗ trợ</h2>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                Học sinh có kỹ năng dưới ngưỡng 9/9/9/4/4/9 (tính trên điểm TB các lần Unit Test).
            </p>
        </div>
        <div class="p-2">
            @if (count($students) === 0)
                <p class="px-2 py-4 text-sm text-gray-500 dark:text-gray-400">
                    Chưa có học sinh nào cần hỗ trợ — hoặc lớp chưa có bài Unit Test (chấm tay).
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                <th class="px-2 py-2 font-medium">Học sinh</th>
                                <th class="px-2 py-2 font-medium">Điểm TB</th>
                                <th class="px-2 py-2 font-medium">SL yếu</th>
                                <th class="px-2 py-2 font-medium">Cần cải thiện</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $s)
                                <tr class="border-b border-gray-50 dark:border-gray-800/50">
                                    <td class="px-2 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $s['full_name'] }}</td>
                                    <td class="px-2 py-2">{{ number_format((float) $s['avg_score'], 2) }}</td>
                                    <td class="px-2 py-2">{{ count($s['weak_skills']) }}</td>
                                    <td class="px-2 py-2">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($s['weak_skills_labels'] as $label)
                                                <span class="inline-flex items-center rounded-md bg-danger-500/10 px-2 py-0.5 text-xs font-medium text-danger-700 dark:text-danger-400">{{ $label }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
