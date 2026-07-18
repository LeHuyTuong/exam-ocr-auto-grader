<?php

namespace App\Filament\Resources\SchoolClassResource\RelationManagers;

use App\Models\Student;
use App\Support\SkillAssessment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    protected static ?string $recordTitleAttribute = 'full_name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('full_name')
                    ->required(),
                Forms\Components\TextInput::make('normalized_name')
                    ->hint('Để trống = dùng tên học sinh')
                    ->dehydrateStateUsing(fn ($state, $get) => filled($state) ? $state : $get('full_name')),
                Forms\Components\Textarea::make('aliases')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('grades'))
            ->columns([
                TextColumn::make('full_name')
                    ->label('Học sinh')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('grades_count')
                    ->counts('grades')
                    ->label('Số bài đã chấm')
                    ->sortable(),
                TextColumn::make('avg_score')
                    ->label('Điểm TB')
                    ->getStateUsing(fn (Student $record): ?string => $record->grades->isNotEmpty() ? number_format((float) $record->grades->avg('score'), 2) : null)
                    ->placeholder('—'),
                TextColumn::make('best_score')
                    ->label('Cao nhất')
                    ->getStateUsing(fn (Student $record): ?string => $record->grades->isNotEmpty() ? number_format((float) $record->grades->max('score'), 2) : null)
                    ->placeholder('—'),
                TextColumn::make('worst_score')
                    ->label('Thấp nhất')
                    ->getStateUsing(fn (Student $record): ?string => $record->grades->isNotEmpty() ? number_format((float) $record->grades->min('score'), 2) : null)
                    ->placeholder('—'),
                TextColumn::make('weak_skills')
                    ->label('Kỹ năng cần cải thiện')
                    ->getStateUsing(function (Student $record): string {
                        $subScoresList = $record->grades->pluck('sub_scores')->filter()->values();
                        $weak = [];
                        foreach (SkillAssessment::thresholds() as $key => $cfg) {
                            $avg = SkillAssessment::averageSkill($key, $subScoresList);
                            if ($avg !== null && SkillAssessment::isWeak($key, $avg)) {
                                $weak[] = $cfg['label'];
                            }
                        }

                        return implode(', ', $weak);
                    })
                    ->placeholder('Đạt')
                    ->color('danger')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
