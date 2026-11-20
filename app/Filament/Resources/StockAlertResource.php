<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAlertResource\Pages;
use App\Models\StockAlert;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class StockAlertResource extends Resource
{
    protected static ?string $model = StockAlert::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Shop & Catalog';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Stock Notification Request')
                    ->schema([
                        Forms\Components\Select::make('product_variant_id')
                            ->relationship('variant', 'sku')
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('contact')
                            ->label('Customer Contact (WhatsApp or Email)')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DateTimePicker::make('notified_at')
                            ->label('Notified Date & Time'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('variant.product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->limit(35),
                Tables\Columns\TextColumn::make('variant.size')
                    ->label('Size')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contact')
                    ->label('Customer Contact')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-chat-bubble-left-ellipsis'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested At')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('notified_at')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Notified' : 'Pending')
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('notified_at')
                    ->label('Notification Status')
                    ->placeholder('All Alerts')
                    ->trueLabel('Notified')
                    ->falseLabel('Pending Notification')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('notified_at'),
                        false: fn ($query) => $query->whereNull('notified_at'),
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('markNotified')
                    ->label('Mark Notified')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (StockAlert $record) => is_null($record->notified_at))
                    ->action(fn (StockAlert $record) => $record->update(['notified_at' => now()])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markAllNotified')
                        ->label('Mark Selected Notified')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['notified_at' => now()])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAlerts::route('/'),
        ];
    }
}
