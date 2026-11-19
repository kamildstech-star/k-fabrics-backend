<?php

namespace App\Filament\Resources\StitchingRequestResource\Pages;

use App\Filament\Resources\StitchingRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStitchingRequests extends ListRecords
{
    protected static string $resource = StitchingRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
