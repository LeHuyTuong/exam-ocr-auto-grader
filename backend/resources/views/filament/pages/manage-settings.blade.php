<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-3">
            <x-filament::button type="submit" color="primary" icon="heroicon-o-check">
                Lưu cài đặt
            </x-filament::button>

            <span class="text-sm text-gray-500 dark:text-gray-400">
                Sau khi lưu, làm mới trang để sidebar cập nhật.
            </span>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
