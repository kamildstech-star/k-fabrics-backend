<?php

namespace App\Filament\Resources\CustomerMeasurementResource\Pages;

use App\Filament\Resources\CustomerMeasurementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerMeasurements extends ListRecords
{
    protected static string $resource = CustomerMeasurementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
