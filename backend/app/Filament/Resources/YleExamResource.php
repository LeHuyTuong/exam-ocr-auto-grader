<?php

namespace App\Filament\Resources;

use App\Filament\Resources\YleExamResource\Pages;
use App\Models\Yle\YleExam;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class YleExamResource extends Resource
{
    protected static ?string $model = YleExam::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'YLE Exams';

    protected static ?string $pluralLabel = 'YLE Exams';

    protected static ?string $modelLabel = 'YLE exam';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Thông tin chung')
                    ->schema([
                        Forms\Components\Select::make('level')
                            ->options([
                                'starters' => 'Starters',
                                'movers' => 'Movers',
                                'flyers' => 'Flyers',
                            ])
                            ->required(),
                        Forms\Components\Select::make('skill')
                            ->options([
                                'listening' => 'Listening',
                                'reading_writing' => 'Reading & Writing',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('total_marks')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('total_pages')
                            ->numeric()
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Parts & Questions')
                    ->schema([
                        Forms\Components\Repeater::make('parts')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('part_number')
                                    ->numeric()
                                    ->required()
                                    ->label('Part #'),
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('question_type')
                                    ->options([
                                        'matching' => 'Matching (nối)',
                                        'fill_blank' => 'Fill blank (điền)',
                                        'mcq_abc' => 'MCQ A/B/C',
                                        'colouring' => 'Colouring (tô màu)',
                                        'tick_cross' => 'Tick/Cross',
                                        'yes_no' => 'Yes/No',
                                        'word_order' => 'Word order (xếp chữ)',
                                        'word_from_box' => 'Word from box',
                                        'one_word' => 'One word answer',
                                    ])
                                    ->required()
                                    ->live(),
                                Forms\Components\Toggle::make('is_auto_gradable')
                                    ->label('Auto-gradable?')
                                    ->default(false),
                                Forms\Components\TextInput::make('max_marks')
                                    ->numeric()
                                    ->required(),
                                Forms\Components\TextInput::make('page_number')
                                    ->numeric()
                                    ->required(),
                                Forms\Components\TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0),

                                Forms\Components\Repeater::make('questions')
                                    ->relationship()
                                    ->schema([
                                        Forms\Components\TextInput::make('question_number')
                                            ->numeric()
                                            ->required()
                                            ->label('Q #'),
                                        Forms\Components\TextInput::make('prompt')
                                            ->maxLength(255)
                                            ->nullable(),
                                        Forms\Components\TextInput::make('correct_answer')
                                            ->maxLength(255)
                                            ->nullable()
                                            ->visible(fn (Forms\Get $get) => $get('../../is_auto_gradable')),
                                        Forms\Components\TagsInput::make('accepted_variants')
                                            ->placeholder('Thêm variant')
                                            ->visible(fn (Forms\Get $get) => $get('../../is_auto_gradable')),
                                        Forms\Components\TextInput::make('points')
                                            ->numeric()
                                            ->default(1),
                                    ])
                                    ->columns(3)
                                    ->addActionLabel('Thêm câu hỏi')
                                    ->reorderable(false),
                            ])
                            ->columns(3)
                            ->addActionLabel('Thêm part')
                            ->reorderable(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'starters' => 'success',
                        'movers' => 'warning',
                        'flyers' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('skill')
                    ->badge(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_marks')
                    ->numeric()
                    ->sortable()
                    ->label('Tổng điểm'),
                Tables\Columns\TextColumn::make('total_pages')
                    ->numeric()
                    ->sortable()
                    ->label('Số trang'),
                Tables\Columns\TextColumn::make('parts_count')
                    ->counts('parts')
                    ->label('Số phần'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('level')
                    ->options([
                        'starters' => 'Starters',
                        'movers' => 'Movers',
                        'flyers' => 'Flyers',
                    ]),
                Tables\Filters\SelectFilter::make('skill')
                    ->options([
                        'listening' => 'Listening',
                        'reading_writing' => 'Reading & Writing',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListYleExams::route('/'),
            'create' => Pages\CreateYleExam::route('/create'),
            'edit' => Pages\EditYleExam::route('/{record}/edit'),
        ];
    }
}
