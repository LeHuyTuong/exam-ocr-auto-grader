<?php

namespace App\Filament\Pages;

use App\Support\Settings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Trang Cài đặt — admin bật/tắt các mục sidebar (và truy cập resource tương ứng).
 * YLE Exams / YLE Kết quả mặc định TẮT (tính năng phát triển sau).
 */
class ManageSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $slug = 'settings';

    protected static ?string $navigationLabel = 'Cài đặt';

    protected static ?string $title = 'Cài đặt hệ thống';

    protected static ?string $navigationGroup = 'Hệ thống';

    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.manage-settings';

    public ?array $data = [];

    /** @return list<array{key:string,label:string,default:bool,group:string}> */
    public static function toggleDefs(): array
    {
        return [
            ['key' => 'navigation.school_classes', 'label' => 'Lớp học', 'default' => true, 'group' => 'Quản lý lớp'],
            ['key' => 'navigation.students', 'label' => 'Học sinh', 'default' => true, 'group' => 'Quản lý lớp'],
            ['key' => 'navigation.exams', 'label' => 'Bài thi', 'default' => true, 'group' => 'Quản lý lớp'],
            ['key' => 'navigation.grades', 'label' => 'Điểm', 'default' => true, 'group' => 'Quản lý lớp'],
            ['key' => 'navigation.users', 'label' => 'Người dùng', 'default' => true, 'group' => 'Hệ thống'],
            ['key' => 'navigation.yle_exams', 'label' => 'YLE Exams', 'default' => false, 'group' => 'YLE (Cambridge)'],
            ['key' => 'navigation.yle_submissions', 'label' => 'YLE Kết quả', 'default' => false, 'group' => 'YLE (Cambridge)'],
        ];
    }

    public function mount(): void
    {
        $defaults = collect(static::toggleDefs())
            ->mapWithKeys(fn (array $d) => [$d['key'] => Settings::getBool($d['key'], $d['default'])])
            ->all();

        $this->form->fill($defaults);
    }

    public function form(Form $form): Form
    {
        $sections = collect(static::toggleDefs())
            ->groupBy('group')
            ->map(fn ($items, $group) => Section::make($group)
                ->schema($items->map(fn (array $d) => Toggle::make($d['key'])
                    ->label($d['label'])
                    ->onColor('success')
                    ->offColor('gray')
                )->all())
                ->columns(2))
            ->values()
            ->all();

        return $form
            ->schema($sections)
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (static::toggleDefs() as $d) {
            Settings::set($d['key'], (bool) ($data[$d['key']] ?? false));
        }

        Settings::flush();

        Notification::make()
            ->title('Đã lưu cài đặt')
            ->body('Các mục đã bị ẩn sẽ không còn truy cập được qua URL.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function hasFormActions(): bool
    {
        return false;
    }
}
