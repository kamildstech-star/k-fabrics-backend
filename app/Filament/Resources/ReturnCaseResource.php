<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReturnCaseResource\Pages\ListReturnCases;
use App\Models\ReturnCase;
use App\Models\User;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ReturnCaseResource extends Resource
{
    protected static ?string $model = ReturnCase::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationGroup = 'Orders & Fulfillment';

    protected static ?string $navigationLabel = 'Returns & Exchanges';

    public static function canViewAny(): bool
    {
        return Auth::user()?->role === 'admin';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('reference')->label('Request reference')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('order.order_number')->label('Order')->searchable()
                    ->url(fn (ReturnCase $record): string => OrderResource::getUrl('edit', ['record' => $record->order_id])),
                Tables\Columns\TextColumn::make('type')->formatStateUsing(fn (string $state): string => ReturnCase::TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => ReturnCase::STATUSES[$state] ?? $state),
                Tables\Columns\TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('handler.name')->label('Last handled by')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ReturnCase::STATUSES),
                Tables\Filters\SelectFilter::make('type')->options(ReturnCase::TYPES),
            ])
            ->actions([
                Tables\Actions\Action::make('readRequest')
                    ->label('View request')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->infolist(fn (Infolist $infolist): Infolist => $infolist->schema([
                        TextEntry::make('reason')->formatStateUsing(fn (string $state): string => ReturnCase::REASONS[$state] ?? $state),
                        TextEntry::make('description')->label('Customer description')->columnSpanFull(),
                        TextEntry::make('staff_note')->label('Internal staff note')->placeholder('No note')->columnSpanFull(),
                    ])),
                Tables\Actions\Action::make('advance')
                    ->label('Update status')
                    ->visible(fn (ReturnCase $record): bool => $record->nextOptions() !== [])
                    ->modalDescription('This only changes the support case. No refund, exchange shipment or stock update happens here.')
                    ->form(fn (ReturnCase $record): array => [
                        Forms\Components\Select::make('status')->options($record->nextOptions())->required(),
                        Forms\Components\Textarea::make('staff_note')->label('Private staff note')->maxLength(1000)->rows(3),
                    ])
                    ->fillForm(fn (ReturnCase $record): array => ['staff_note' => $record->staff_note])
                    ->action(function (ReturnCase $record, array $data): void {
                        $staff = Auth::user();
                        abort_unless($staff instanceof User, 403);
                        $record->advance($data['status'], $staff, $data['staff_note'] ?? null);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListReturnCases::route('/')];
    }
}
