<?php

namespace App\Filament\Resources\SchoolClassResource\RelationManagers;

use App\Filament\Actions\ExportExamGradesAction;
use App\Models\Grade;
use App\Support\SkillAssessment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GradesRelationManager extends RelationManager
{
    protected static string $relationship = 'grades';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('total_correct')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('score')
                    ->numeric()
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Chờ duyệt',
                        'confirmed' => 'Đã duyệt',
                    ])
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Học sinh')
                    ->searchable()
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                ExportExamGradesAction::headerAction(fn () => $this->getOwnerRecord()->id),
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
}
