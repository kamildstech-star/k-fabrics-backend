<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('kind')->badge(),
            Tables\Columns\TextColumn::make('amount_minor')->label('Amount')->money('PKR', divideBy: 100),
            Tables\Columns\TextColumn::make('reference')->copyable(),
            Tables\Columns\TextColumn::make('received_at')->dateTime(),
            Tables\Columns\TextColumn::make('operator.name')->label('Recorded by'),
        ])->defaultSort('received_at', 'desc');
    }
}
