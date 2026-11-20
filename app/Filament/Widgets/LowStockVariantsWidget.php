<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Models\ProductVariant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockVariantsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Low Stock Inventory Alerts (Restock Needed)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProductVariant::query()
                    ->with('product')
                    ->where('is_active', true)
                    ->where('stock_quantity', '<=', 3)
                    ->orderBy('stock_quantity', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.fabric_type')
                    ->label('Fabric')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('size')
                    ->label('Size')
                    ->badge(),

                Tables\Columns\TextColumn::make('color')
                    ->label('Color')
                    ->placeholder('Standard'),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),

                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Remaining Stock')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state == 0 ? 'SOLD OUT (0)' : "{$state} Units Left")
                    ->color(fn ($state) => $state == 0 ? 'danger' : 'warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Variant Price')
                    ->money('PKR', divideBy: 1)
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Restock / Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (ProductVariant $record) => ProductResource::getUrl('edit', ['record' => $record->product_id])),
            ])
            ->paginated([5, 10, 25]);
    }
}
