<?php

namespace App\Filament\Resources\StitchingServiceResource\Pages;

use App\Filament\Resources\StitchingServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStitchingService extends EditRecord
{
    protected static string $resource = StitchingServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
