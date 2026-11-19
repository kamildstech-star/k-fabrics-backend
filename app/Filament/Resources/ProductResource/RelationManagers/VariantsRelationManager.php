<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $recordTitleAttribute = 'sku';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('size')
                    ->options([
                        'XS' => 'Extra Small (XS)',
                        'S' => 'Small (S)',
                        'M' => 'Medium (M)',
                        'L' => 'Large (L)',
                        'XL' => 'Extra Large (XL)',
                        'XXL' => 'Double Extra Large (XXL)',
                        'Custom' => 'Custom Fit',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('color')
                    ->placeholder('e.g. Ivory, Sage Green')
                    ->maxLength(50),
                Forms\Components\ColorPicker::make('color_hex')
                    ->label('Color Swatch'),
                Forms\Components\TextInput::make('price_override')
                    ->label('Price Override (PKR)')
                    ->numeric()
                    ->prefix('Rs.')
                    ->placeholder('Leave blank to use base price'),
                Forms\Components\TextInput::make('stock_quantity')
                    ->label('Stock Quantity')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Available for Purchase')
                    ->default(true),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('size')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Custom' => 'warning',
                        default => 'primary',
                    }),
                Tables\Columns\TextColumn::make('color')
                    ->searchable(),
                Tables\Columns\ColorColumn::make('color_hex')
                    ->label('Swatch'),
                Tables\Columns\TextColumn::make('price_override')
                    ->label('Price Override')
                    ->money('PKR')
                    ->placeholder('Default'),
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state <= 0 => 'danger',
                        $state < 10 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('size')
                    ->options([
                        'XS' => 'XS',
                        'S' => 'S',
                        'M' => 'M',
                        'L' => 'L',
                        'XL' => 'XL',
                        'XXL' => 'XXL',
                        'Custom' => 'Custom',
                    ]),
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
