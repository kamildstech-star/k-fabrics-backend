<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $recordTitleAttribute = 'alt_text';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('image_url')
                    ->label('Image URL')
                    ->required()
                    ->url()
                    ->placeholder('https://images.unsplash.com/...')
                    ->maxLength(500)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('alt_text')
                    ->label('Alt Text')
                    ->placeholder('e.g. Ivory Suit Front Angle')
                    ->maxLength(255),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Display Order')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_primary')
                    ->label('Primary / Thumbnail Image')
                    ->default(false),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Preview')
                    ->square(),
                Tables\Columns\TextColumn::make('image_url')
                    ->label('URL')
                    ->limit(40)
                    ->color('gray'),
                Tables\Columns\TextColumn::make('alt_text')
                    ->searchable(),
                Tables\Columns\ToggleColumn::make('is_primary')
                    ->label('Primary'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
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
