<?php

namespace App\Filament\Resources\StitchingRequestResource\Pages;

use App\Filament\Resources\StitchingRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStitchingRequest extends EditRecord
{
    protected static string $resource = StitchingRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('printTailorSheet')
                ->label('Print Tailor Sheet')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->url(fn () => route('admin.stitching-requests.tailor-sheet', ['stitchingRequest' => $this->record]))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}
