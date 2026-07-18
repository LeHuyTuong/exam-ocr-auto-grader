<?php

namespace App\Filament\Resources;

use App\Filament\Actions\ExportExamGradesAction;
use App\Filament\Concerns\TogglableResource;
use App\Filament\Resources\GradeResource\Pages;
use App\Models\Grade;
use App\Support\SkillAssessment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GradeResource extends Resource
{
    use TogglableResource;

    protected static ?string $model = Grade::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Quản lý lớp';

    protected static ?int $navigationSort = 4;

    protected static function navigationToggleKey(): ?string
    {
        return 'navigation.grades';
    }

    protected static function navigationToggleDefault(): bool
    {
        return true;
    }

    protected static ?string $navigationLabel = 'Điểm';

    protected static ?string $pluralLabel = 'Điểm';

    protected static ?string $modelLabel = 'điểm';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('exam_id')
                    ->relationship('exam', 'name')
                    ->required(),
                Forms\Components\Select::make('student_id')
                    ->relationship('student', 'full_name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('class_id')
                    ->relationship('class', 'name')
                    ->required(),
                Forms\Components\TextInput::make('total_correct')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('score')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('image_url'),
                Forms\Components\TextInput::make('ai_confidence')
                    ->numeric(),
                Forms\Components\TextInput::make('ocr_raw_name'),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Chờ duyệt',
                        'confirmed' => 'Đã duyệt',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Học sinh')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('class.code')
                    ->label('Mã lớp')
                    ->sortable(),
                TextColumn::make('exam.name')
                    ->label('Bài thi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_correct')
                    ->label('Đúng')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('score')
                    ->label('Điểm')
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('weak_skills')
                    ->label('Kỹ năng cần cải thiện')
                    ->getStateUsing(fn (Grade $record): string => SkillAssessment::weakSkillsText($record->sub_scores))
                    ->placeholder('—')
                    ->color('danger')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'success',
                    }),
                TextColumn::make('created_at')
                    ->label('Thời gian')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                ExportExamGradesAction::headerAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGrades::route('/'),
            'create' => Pages\CreateGrade::route('/create'),
            'edit' => Pages\EditGrade::route('/{record}/edit'),
        ];
    }
}
