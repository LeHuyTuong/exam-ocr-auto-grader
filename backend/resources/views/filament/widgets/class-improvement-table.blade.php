<x-filament-widgets::widget>
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Xếp hạng lớp theo tiến bộ</h2>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                % thay đổi điểm TB: bài mới nhất so với bài chấm liền trước đó.
            </p>
        </div>
        <div class="overflow-x-auto">
            @if (count($classes) === 0)
                <p class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">Chưa có lớp nào.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-3 py-2 font-medium">#</th>
                            <th class="px-3 py-2 font-medium">Lớp</th>
                            <th class="px-3 py-2 font-medium">Khối</th>
                            <th class="px-3 py-2 font-medium">Sĩ số</th>
                            <th class="px-3 py-2 font-medium">Điểm TB mới nhất</th>
                            <th class="px-3 py-2 font-medium">Xu hướng</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($classes as $i => $c)
                            <tr class="border-b border-gray-50 dark:border-gray-800/50">
                                <td class="px-3 py-2 text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $c['code'] }} — {{ $c['name'] }}
                                </td>
                                <td class="px-3 py-2">{{ $c['level'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $c['students_count'] }}</td>
                                <td class="px-3 py-2">{{ $c['latest_avg'] !== null ? number_format((float) $c['latest_avg'], 2) : '—' }}</td>
                                <td class="px-3 py-2">
                                    @if ($c['trend'] === null)
                                        <span class="text-gray-400">—</span>
                                    @elseif ($c['trend'] >= 0)
                                        <span class="inline-flex items-center rounded-md bg-success-500/10 px-2 py-0.5 text-xs font-medium text-success-700 dark:text-success-400">↑ +{{ $c['trend'] }}%</span>
                                    @else
                                        <span class="inline-flex items-center rounded-md bg-danger-500/10 px-2 py-0.5 text-xs font-medium text-danger-700 dark:text-danger-400">↓ {{ $c['trend'] }}%</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
