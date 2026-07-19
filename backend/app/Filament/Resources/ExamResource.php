<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\TogglableResource;
use App\Filament\Resources\ExamResource\Pages;
use App\Models\Exam;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExamResource extends Resource
{
    use TogglableResource;

    protected static ?string $model = Exam::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Quản lý lớp';

    protected static ?int $navigationSort = 3;

    protected static function navigationToggleKey(): ?string
    {
        return 'navigation.exams';
    }

    protected static function navigationToggleDefault(): bool
    {
        return true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('class_id')
                    ->relationship('class', 'name')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('total_questions')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                Forms\Components\TextInput::make('max_score')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                Forms\Components\Select::make('grading_mode')
                    ->options([
                        'counting' => 'Đếm câu đúng',
                        'graded' => 'Unit Test đã chấm tay',
                    ])
                    ->default('counting')
                    ->required(),
                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('class.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Đang chấm' : 'Đã khoá')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('total_questions')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_score')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('grading_mode')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'graded' => 'Unit Test đã chấm tay',
                        default => 'Đếm câu đúng',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExams::route('/'),
            'create' => Pages\CreateExam::route('/create'),
            'edit' => Pages\EditExam::route('/{record}/edit'),
        ];
    }
}
