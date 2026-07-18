<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\TogglableResource;
use App\Filament\Resources\SchoolClassResource\Pages;
use App\Filament\Resources\SchoolClassResource\RelationManagers\ExamsRelationManager;
use App\Filament\Resources\SchoolClassResource\RelationManagers\GradesRelationManager;
use App\Filament\Resources\SchoolClassResource\RelationManagers\StudentsRelationManager;
use App\Filament\Resources\SchoolClassResource\Widgets\ClassScoreTrendChart;
use App\Filament\Resources\SchoolClassResource\Widgets\ClassSkillAveragesChart;
use App\Filament\Resources\SchoolClassResource\Widgets\ClassStatsOverview;
use App\Filament\Resources\SchoolClassResource\Widgets\WeakStudentsTable;
use App\Models\SchoolClass;
use App\Support\ClassStatsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchoolClassResource extends Resource
{
    use TogglableResource;

    protected static ?string $model = SchoolClass::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Quản lý lớp';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationToggleKey = 'navigation.school_classes';

    protected static bool $navigationToggleDefault = true;

    protected static ?string $navigationLabel = 'Lớp học';

    protected static function navigationToggleKey(): ?string
    {
        return 'navigation.school_classes';
    }

    protected static function navigationToggleDefault(): bool
    {
        return true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('level')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Mã lớp')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Tên lớp')
                    ->searchable()
                    ->sortable()
                    ->description(fn (SchoolClass $record): ?string => $record->level ? 'Khối '.$record->level : null),
                TextColumn::make('level')
                    ->label('Khối')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('score_trend')
                    ->label('Điểm TB & xu hướng')
                    ->getStateUsing(function (SchoolClass $record): ?string {
                        $summary = (new ClassStatsService($record))->trendSummary();
                        if ($summary['latest'] === null) {
                            return null;
                        }
                        $text = number_format((float) $summary['latest'], 2);
                        if ($summary['trend'] !== null) {
                            $arrow = $summary['trend'] >= 0 ? '↑' : '↓';
                            $sign = $summary['trend'] >= 0 ? '+' : '';
                            $text .= ' '.$arrow.' '.$sign.$summary['trend'].'%';
                        }

                        return $text;
                    })
                    ->placeholder('Chưa chấm')
                    ->badge()
                    ->color(function (?string $state): string {
                        if ($state === null) {
                            return 'gray';
                        }
                        if (str_contains($state, '↓')) {
                            return 'danger';
                        }
                        if (str_contains($state, '↑')) {
                            return 'success';
                        }

                        return 'gray';
                    }),
                TextColumn::make('students_count')
                    ->counts('students')
                    ->label('Sĩ số')
                    ->sortable(),
                TextColumn::make('exams_count')
                    ->counts('exams')
                    ->label('Số bài thi')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn (SchoolClass $record): string => static::getUrl('view', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [
            StudentsRelationManager::class,
            ExamsRelationManager::class,
            GradesRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            ClassStatsOverview::class,
            ClassScoreTrendChart::class,
            ClassSkillAveragesChart::class,
            WeakStudentsTable::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchoolClasses::route('/'),
            'create' => Pages\CreateSchoolClass::route('/create'),
            'view' => Pages\ViewSchoolClass::route('/{record}'),
            'edit' => Pages\EditSchoolClass::route('/{record}/edit'),
        ];
    }
}
