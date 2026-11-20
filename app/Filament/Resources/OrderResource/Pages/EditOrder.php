<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('printTailorSheet')
                ->label('Print Tailor Sheet')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->url(fn () => route('admin.orders.tailor-sheet', ['order' => $this->record]))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}
