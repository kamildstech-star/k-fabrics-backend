<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\StitchingRequestsRelationManager;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Orders & Tailoring';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Order Summary')
                            ->schema([
                                Forms\Components\TextInput::make('order_number')
                                    ->label('Order Number')
                                    ->required()
                                    ->disabled()
                                    ->dehydrated(),
                                Forms\Components\Select::make('user_id')
                                    ->label('Registered Customer')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->placeholder('Guest Order')
                                    ->disabled(),
                                Forms\Components\TextInput::make('customer_name')
                                    ->required(),
                                Forms\Components\TextInput::make('customer_phone')
                                    ->tel()
                                    ->required(),
                                Forms\Components\TextInput::make('customer_email')
                                    ->email(),
                                Forms\Components\TextInput::make('city')
                                    ->required(),
                                Forms\Components\Textarea::make('shipping_address')
                                    ->required()
                                    ->columnSpanFull(),
                                Forms\Components\Textarea::make('notes')
                                    ->label('Customer / Internal Notes')
                                    ->columnSpanFull(),
                            ])->columns(2),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Status & Fulfillment')
                            ->schema([
                                Forms\Components\Select::make('order_status')
                                    ->label('Order Status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'confirmed' => 'Confirmed',
                                        'stitching' => 'In Tailoring / Stitching',
                                        'packed' => 'Packed',
                                        'shipped' => 'Shipped',
                                        'delivered' => 'Delivered',
                                        'cancelled' => 'Cancelled',
                                        'returned' => 'Returned',
                                    ])
                                    ->required(),
                                Forms\Components\Select::make('payment_status')
                                    ->label('Payment Status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'paid' => 'Paid',
                                        'failed' => 'Failed',
                                        'refunded' => 'Refunded',
                                    ])
                                    ->required(),
                                Forms\Components\Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'cod' => 'Cash on Delivery (COD)',
                                        'bank_transfer' => 'Bank Transfer',
                                        'jazzcash' => 'JazzCash',
                                        'easypaisa' => 'EasyPaisa',
                                        'card' => 'Credit / Debit Card',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('courier_name')
                                    ->label('Courier Partner')
                                    ->placeholder('e.g. TCS, Leopards, Trax'),
                                Forms\Components\TextInput::make('tracking_number')
                                    ->label('Tracking ID / Consignment #')
                                    ->placeholder('e.g. TCS-12345678'),
                            ]),

                        Forms\Components\Section::make('Financial Details')
                            ->schema([
                                Forms\Components\TextInput::make('subtotal')
                                    ->numeric()
                                    ->prefix('Rs.')
                                    ->required(),
                                Forms\Components\TextInput::make('shipping_fee')
                                    ->numeric()
                                    ->prefix('Rs.')
                                    ->required(),
                                Forms\Components\TextInput::make('discount_amount')
                                    ->numeric()
                                    ->prefix('Rs.')
                                    ->required(),
                                Forms\Components\TextInput::make('total_amount')
                                    ->numeric()
                                    ->prefix('Rs.')
                                    ->required(),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_phone')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->badge()
                    ->color('secondary'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('PKR')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\SelectColumn::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
                Tables\Columns\SelectColumn::make('order_status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'stitching' => 'Stitching',
                        'packed' => 'Packed',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                        'returned' => 'Returned',
                    ]),
                Tables\Columns\TextColumn::make('courier_name')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('tracking_number')
                    ->placeholder('—')
                    ->copyable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('order_status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'stitching' => 'Stitching',
                        'packed' => 'Packed',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'cod' => 'COD',
                        'bank_transfer' => 'Bank Transfer',
                        'jazzcash' => 'JazzCash',
                        'easypaisa' => 'EasyPaisa',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            StitchingRequestsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
