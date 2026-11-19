<?php

namespace App\Filament\Resources\StitchingServiceResource\Pages;

use App\Filament\Resources\StitchingServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStitchingServices extends ListRecords
{
    protected static string $resource = StitchingServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
