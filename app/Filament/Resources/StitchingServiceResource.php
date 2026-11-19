<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StitchingServiceResource\Pages;
use App\Models\StitchingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StitchingServiceResource extends Resource
{
    protected static ?string $model = StitchingService::class;

    protected static ?string $navigationIcon = 'heroicon-o-scissors';

    protected static ?string $navigationGroup = 'Orders & Tailoring';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Tailoring Service Package')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Service Name')
                            ->placeholder('e.g. Simple 2-Piece, Fancy / Lining Suit')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('base_price')
                            ->label('Base Stitching Fee')
                            ->prefix('Rs.')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('estimated_days')
                            ->label('Estimated Turnaround (Days)')
                            ->numeric()
                            ->default(5)
                            ->suffix('days')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Offered Online')
                            ->default(true),
                        Forms\Components\Textarea::make('description')
                            ->label('Description & Inclusions')
                            ->placeholder('Describe what is included (lining, piping, cut style, etc.)')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('base_price')
                    ->label('Stitching Fee')
                    ->money('PKR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimated_days')
                    ->label('Turnaround')
                    ->suffix(' days')
                    ->badge()
                    ->color('secondary')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
                Tables\Columns\TextColumn::make('stitching_requests_count')
                    ->counts('stitchingRequests')
                    ->label('Total Bookings')
                    ->badge()
                    ->color('primary'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStitchingServices::route('/'),
            'create' => Pages\CreateStitchingService::route('/create'),
            'edit' => Pages\EditStitchingService::route('/{record}/edit'),
        ];
    }
}
