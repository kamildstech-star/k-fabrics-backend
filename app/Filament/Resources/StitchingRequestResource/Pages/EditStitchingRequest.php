<?php

namespace App\Filament\Resources\StitchingRequestResource\Pages;

use App\Filament\Resources\StitchingRequestResource;
use App\Services\OrderLifecycle;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStitchingRequest extends EditRecord
{
    protected static string $resource = StitchingRequestResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(OrderLifecycle::class)->updateStitching($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('printTailorSheet')
                ->label('Print Tailor Sheet')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->url(fn () => route('admin.stitching-requests.tailor-sheet', ['stitchingRequest' => $this->record]))
                ->openUrlInNewTab(),
        ];
    }
}
