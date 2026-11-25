<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StitchingRequestResource\Pages;
use App\Models\StitchingRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StitchingRequestResource extends Resource
{
    protected static ?string $model = StitchingRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Orders & Tailoring';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Tailoring Job Details')
                            ->schema([
                                Forms\Components\Select::make('order_id')
                                    ->label('Order Reference')
                                    ->relationship('order', 'order_number')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disabled(),
                                Forms\Components\Select::make('request_type')
                                    ->label('Request Type')
                                    ->options([
                                        'custom_fitting' => 'Custom Fitting (Ready-to-Wear)',
                                        'standalone_stitching' => 'Standalone Tailoring (Customer Fabric)',
                                    ])
                                    ->required()
                                    ->disabled(),
                                Forms\Components\Select::make('stitching_service_id')
                                    ->label('Service Package')
                                    ->relationship('stitchingService', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->disabled(),
                                Forms\Components\Select::make('measurement_id')
                                    ->label('Measurement Profile')
                                    ->relationship('measurement', 'profile_name')
                                    ->searchable()
                                    ->preload()
                                    ->disabled(),
                                Forms\Components\TextInput::make('stitching_fee')
                                    ->label('Stitching Fee')
                                    ->prefix('Rs.')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->disabled(),
                            ])->columns(2),

                        Forms\Components\Section::make('Design & Styling Customizations')
                            ->schema([
                                Forms\Components\TextInput::make('neck_style')
                                    ->placeholder('e.g. V-neck, Boat neck, Round with slit'),
                                Forms\Components\TextInput::make('sleeve_style')
                                    ->placeholder('e.g. Straight cuff, Bell sleeves, Full sleeves'),
                                Forms\Components\TextInput::make('trouser_style')
                                    ->placeholder('e.g. Straight trouser, Flared pants, Shalwar'),
                                Forms\Components\Textarea::make('special_instructions')
                                    ->columnSpanFull(),
                            ])->columns(3),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Fabric Handover & Tailor Progress')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Current Progress')
                                    ->options([
                                        'awaiting_fabric' => 'Awaiting Fabric or Advance',
                                        'cutting' => 'Master Cutting',
                                        'stitching' => 'Stitching in Progress',
                                        'finishing' => 'Hand Finishing & Quality Check',
                                        'ready' => 'Ready / Tailoring Complete',
                                    ])
                                    ->default('awaiting_fabric')
                                    ->required(),
                                Forms\Components\Select::make('fabric_delivery_method')
                                    ->label('Fabric Inbound Method')
                                    ->options([
                                        'courier_by_customer' => 'Sent via Courier by Customer',
                                        'rider_pickup' => 'Rider Pickup (Local)',
                                        'shop_dropoff' => 'Drop-off at Shop',
                                    ]),
                                Forms\Components\DateTimePicker::make('fabric_received_at')
                                    ->label('Fabric Received Date & Time')
                                    ->helperText('Mark when fabric arrives at workshop'),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                Tables\Columns\TextColumn::make('request_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'custom_fitting' => 'primary',
                        'standalone_stitching' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('stitchingService.name')
                    ->label('Service')
                    ->placeholder('Custom Fitting'),
                Tables\Columns\TextColumn::make('status')->label('Tailoring Status')->badge(),
                Tables\Columns\TextColumn::make('fabric_delivery_method')
                    ->label('Inbound Method')
                    ->badge()
                    ->placeholder('Store Fabric'),
                Tables\Columns\TextColumn::make('fabric_received_at')
                    ->label('Fabric Received')
                    ->dateTime()
                    ->placeholder('Pending Receipt'),
                Tables\Columns\TextColumn::make('stitching_fee')
                    ->label('Fee')
                    ->money('PKR'),
                Tables\Columns\TextColumn::make('design_reference_image')
                    ->label('Reference')
                    ->formatStateUsing(fn (): string => 'View image')
                    ->url(fn (StitchingRequest $record): ?string => str_starts_with($record->design_reference_image ?? '', 'stitching_references/')
                        ? route('admin.stitching-requests.reference', ['stitchingRequest' => $record])
                        : null)
                    ->openUrlInNewTab(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'awaiting_fabric' => 'Awaiting Fabric',
                        'cutting' => 'Cutting',
                        'stitching' => 'Stitching',
                        'finishing' => 'Finishing',
                        'ready' => 'Ready',
                    ]),
                Tables\Filters\SelectFilter::make('request_type')
                    ->options([
                        'custom_fitting' => 'Custom Fitting',
                        'standalone_stitching' => 'Standalone Stitching',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('printTailorSheet')
                    ->label('Tailor Sheet')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (StitchingRequest $record) => route('admin.stitching-requests.tailor-sheet', ['stitchingRequest' => $record]))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStitchingRequests::route('/'),
            'edit' => Pages\EditStitchingRequest::route('/{record}/edit'),
        ];
    }
}
