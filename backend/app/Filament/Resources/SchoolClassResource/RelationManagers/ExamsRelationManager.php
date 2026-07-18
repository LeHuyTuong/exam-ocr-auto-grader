<?php

namespace App\Filament\Resources\SchoolClassResource\RelationManagers;

use App\Filament\Actions\ExportExamGradesAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExamsRelationManager extends RelationManager
{
    protected static string $relationship = 'exams';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('grades')->withAvg('grades', 'score'))
            ->columns([
                TextColumn::make('name')
                    ->label('Bài thi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('grading_mode')
                    ->label('Kiểu chấm')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'graded' => 'Unit Test',
                        default => 'Đếm câu đúng',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'graded' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('total_questions')
                    ->numeric()
                    ->label('Số câu')
                    ->sortable(),
                TextColumn::make('max_score')
                    ->numeric()
                    ->label('Điểm tối đa')
                    ->sortable(),
                TextColumn::make('grades_count')
                    ->label('Đã chấm')
                    ->sortable(),
                TextColumn::make('grades_avg_score')
                    ->label('Điểm TB')
                    ->numeric(2)
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Tạo lúc')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                ExportExamGradesAction::rowAction(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
