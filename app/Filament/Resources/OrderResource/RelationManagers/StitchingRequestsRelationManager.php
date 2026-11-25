<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StitchingRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'stitchingRequests';

    protected static ?string $recordTitleAttribute = 'request_type';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('request_type')
                    ->options([
                        'custom_fitting' => 'Custom Fitting (Ready-to-Wear)',
                        'standalone_stitching' => 'Standalone Stitching (Customer Fabric)',
                    ])
                    ->required(),
                Forms\Components\Select::make('stitching_service_id')
                    ->label('Service')
                    ->relationship('stitchingService', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('measurement_id')
                    ->label('Measurement Profile')
                    ->relationship('measurement', 'profile_name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('status')
                    ->options([
                        'awaiting_fabric' => 'Awaiting Fabric',
                        'cutting' => 'Cutting',
                        'stitching' => 'Stitching',
                        'finishing' => 'Finishing',
                        'ready' => 'Ready',
                    ])
                    ->required(),
                Forms\Components\Select::make('fabric_delivery_method')
                    ->options([
                        'courier_by_customer' => 'Sent via Courier by Customer',
                        'rider_pickup' => 'Rider Pickup (Local)',
                        'shop_dropoff' => 'Drop-off at Shop',
                    ]),
                Forms\Components\DateTimePicker::make('fabric_received_at')
                    ->label('Fabric Received Date/Time'),
                Forms\Components\TextInput::make('stitching_fee')
                    ->numeric()
                    ->prefix('Rs.')
                    ->default(0),
                Forms\Components\TextInput::make('neck_style'),
                Forms\Components\TextInput::make('sleeve_style'),
                Forms\Components\TextInput::make('trouser_style'),
                Forms\Components\Textarea::make('special_instructions')
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('request_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'custom_fitting' => 'primary',
                        'standalone_stitching' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('stitchingService.name')
                    ->label('Service')
                    ->placeholder('Custom Fit Add-on'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'awaiting_fabric' => 'gray',
                        'cutting' => 'info',
                        'stitching' => 'warning',
                        'finishing' => 'primary',
                        'ready' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('fabric_delivery_method')
                    ->badge()
                    ->placeholder('Store Fabric'),
                Tables\Columns\TextColumn::make('fabric_received_at')
                    ->dateTime()
                    ->placeholder('Not yet received'),
                Tables\Columns\TextColumn::make('stitching_fee')
                    ->money('PKR'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
