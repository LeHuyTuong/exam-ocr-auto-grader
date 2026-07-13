<?php

namespace App\Filament\Resources;

use App\Filament\Exports\YleSubmissionExporter;
use App\Filament\Resources\YleSubmissionResource\Pages;
use App\Models\Yle\YleAnswer;
use App\Models\Yle\YleSubmission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Table;

class YleSubmissionResource extends Resource
{
    protected static ?string $model = YleSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'YLE Kết quả';

    protected static ?string $pluralLabel = 'YLE Kết quả';

    protected static ?string $modelLabel = 'kết quả YLE';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Thông tin submission')
                    ->schema([
                        Forms\Components\Select::make('yle_exam_id')
                            ->relationship('exam', 'name')
                            ->required(),
                        Forms\Components\Select::make('student_id')
                            ->relationship('student', 'full_name')
                            ->searchable()
                            ->nullable(),
                        Forms\Components\Select::make('class_id')
                            ->relationship('class', 'name')
                            ->required(),
                        Forms\Components\TextInput::make('auto_score')
                            ->label('Điểm tự động')
                            ->numeric(),
                        Forms\Components\TextInput::make('manual_score')
                            ->label('Điểm chấm tay')
                            ->numeric(),
                        Forms\Components\TextInput::make('total_score')
                            ->label('Tổng điểm')
                            ->numeric(),
                        Forms\Components\TextInput::make('max_score')
                            ->label('Điểm tối đa')
                            ->numeric(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Chờ xử lý',
                                'grading' => 'Đang chấm',
                                'auto_graded' => 'Đã chấm tự động',
                                'completed' => 'Hoàn tất',
                                'needs_review' => 'Cần kiểm tra',
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('exam.name')
                    ->label('Bài thi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('student.full_name')
                    ->label('Học sinh')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('class.code')
                    ->label('Lớp')
                    ->sortable(),
                Tables\Columns\TextColumn::make('auto_score')
                    ->label('Auto')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('manual_score')
                    ->label('Chấm tay')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_score')
                    ->label('Tổng')
                    ->numeric(2)
                    ->sortable()
                    ->color(fn ($record) => match (true) {
                        $record->total_score >= $record->max_score * 0.8 => 'success',
                        $record->total_score >= $record->max_score * 0.5 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('max_score')
                    ->label('Tối đa')
                    ->numeric(2),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'grading' => 'info',
                        'auto_graded' => 'warning',
                        'completed' => 'success',
                        'needs_review' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('exam_date')
                    ->label('Ngày thi')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Chờ xử lý',
                        'grading' => 'Đang chấm',
                        'auto_graded' => 'Đã chấm tự động',
                        'completed' => 'Hoàn tất',
                        'needs_review' => 'Cần kiểm tra',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('manualGrade')
                    ->label('Chấm tay')
                    ->icon('heroicon-o-pencil-square')
                    ->form(fn (YleSubmission $record): array => static::getManualGradeForm($record))
                    ->action(fn (array $data, YleSubmission $record) => static::saveManualGrade($data, $record))
                    ->modalWidth('lg')
                    ->visible(fn (YleSubmission $record): bool => $record->exam->parts()->where('is_auto_gradable', false)->exists()),
            ])
            ->bulkActions([])
            ->headerActions([
                ExportAction::make()
                    ->label('Xuất Excel')
                    ->exporter(YleSubmissionExporter::class),
            ]);
    }

    public static function getManualGradeForm(YleSubmission $record): array
    {
        $manualParts = $record->exam->parts()->where('is_auto_gradable', false)->get();
        $fields = [];

        foreach ($manualParts as $part) {
            $page = $record->pages->firstWhere('page_number', $part->page_number);

            if ($page?->image_url) {
                $fields[] = Forms\Components\Placeholder::make("image_{$part->id}")
                    ->label("Ảnh - {$part->title}")
                    ->content(fn () => new \Illuminate\Support\HtmlString(
                        "<img src=\"{$page->image_url}\" style=\"max-width:100%;max-height:250px;border-radius:8px;margin-bottom:8px\">"
                    ));
            }

            $fields[] = Forms\Components\TextInput::make("marks_{$part->id}")
                ->label("{$part->title} (0-{$part->max_marks})")
                ->numeric()
                ->minValue(0)
                ->maxValue($part->max_marks)
                ->default(0)
                ->required();
        }

        return $fields;
    }

    public static function saveManualGrade(array $data, YleSubmission $record): void
    {
        $totalManual = 0;

        $manualParts = $record->exam->parts()->where('is_auto_gradable', false)->get();

        foreach ($manualParts as $part) {
            $key = "marks_{$part->id}";
            $marks = isset($data[$key]) ? max(0, min((int) $data[$key], $part->max_marks)) : 0;
            $totalManual += $marks;

            foreach ($part->questions as $question) {
                YleAnswer::updateOrCreate(
                    [
                        'yle_submission_id' => $record->id,
                        'yle_question_id' => $question->id,
                    ],
                    [
                        'graded_by' => 'manual',
                        'is_correct' => null,
                        'marks_awarded' => 0,
                    ]
                );
            }
        }

        $record->update(['manual_score' => $totalManual]);

        // Recalculate totals
        $autoScore = YleAnswer::where('yle_submission_id', $record->id)
            ->where('graded_by', 'auto')
            ->sum('marks_awarded');

        $totalScore = $autoScore + $totalManual;

        $hasNeedsReview = YleAnswer::where('yle_submission_id', $record->id)
            ->where('graded_by', 'auto')
            ->where('ai_confidence', '<', 0.6)
            ->exists();

        $status = $hasNeedsReview ? 'needs_review' : 'completed';

        $record->update([
            'auto_score' => $autoScore,
            'total_score' => $totalScore,
            'status' => $status,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListYleSubmissions::route('/'),
            'view' => Pages\ViewYleSubmission::route('/{record}'),
        ];
    }
}
