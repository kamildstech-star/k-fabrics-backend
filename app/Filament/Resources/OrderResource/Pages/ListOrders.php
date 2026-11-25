<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Orders'),
            'cod_confirmation' => Tab::make('COD Needs Confirmation')
                ->badge(fn (): int => Order::query()->pendingCodConfirmation()->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->pendingCodConfirmation()
                    ->orderByRaw('CASE WHEN cod_confirmation_expires_at IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('cod_confirmation_expires_at')),
        ];
    }
}
