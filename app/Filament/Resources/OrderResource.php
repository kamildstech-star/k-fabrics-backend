<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\StitchingRequestsRelationManager;
use App\Models\Order;
use App\Services\Money;
use App\Services\OrderLifecycle;
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
                                    ->disabled(),
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
                                    ->disabled(),
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
                                Forms\Components\Placeholder::make('confirmation_warning')
                                    ->label('COD Confirmation Alert')
                                    ->visible(fn (?Order $record) => $record && $record->payment_method === 'cod' && ! $record->confirmed_at && $record->order_status === 'pending')
                                    ->content('⚠️ WARNING: This COD order has NOT yet been confirmed via WhatsApp bot or phone call. Confirm before dispatching to prevent RTO.'),
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
                                    ->required()
                                    ->disabled(),
                                Forms\Components\Select::make('payment_status')
                                    ->label('Payment Status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'paid' => 'Paid',
                                        'failed' => 'Failed',
                                        'refunded' => 'Refunded',
                                    ])
                                    ->required()
                                    ->disabled(),
                                Forms\Components\Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'cod' => 'Cash on Delivery (COD)',
                                        'bank_transfer' => 'Bank Transfer',
                                        'jazzcash' => 'JazzCash',
                                        'easypaisa' => 'EasyPaisa',
                                        'card' => 'Credit / Debit Card',
                                    ])
                                    ->required()
                                    ->disabled(),
                                Forms\Components\Select::make('confirmation_channel')
                                    ->label('Confirmation Channel')
                                    ->options([
                                        'none' => 'None / Unconfirmed',
                                        'whatsapp_bot' => 'WhatsApp Bot (Reply 1)',
                                        'manual_call' => 'Manual Phone Call',
                                        'advance_paid' => 'Advance Paid Transfer',
                                    ])
                                    ->disabled(),
                                Forms\Components\DateTimePicker::make('confirmed_at')
                                    ->label('Confirmed At')
                                    ->disabled(),
                                Forms\Components\DateTimePicker::make('phone_verified_at')
                                    ->label('Phone Verified At (OTP)')
                                    ->disabled(),
                                Forms\Components\TextInput::make('cancelled_reason')
                                    ->label('Cancellation Reason')
                                    ->disabled()
                                    ->visible(fn (?Order $record) => ! empty($record?->cancelled_reason)),
                                Forms\Components\Placeholder::make('cod_confirmation_hold')
                                    ->label('COD Stock Hold (Pakistan Time)')
                                    ->visible(fn (?Order $record): bool => $record?->cod_confirmation_expires_at !== null)
                                    ->content(fn (?Order $record): string => $record?->cod_confirmation_expires_at?->copy()->timezone('Asia/Karachi')->format('d M Y, g:i A') ?? ''),
                                Forms\Components\TextInput::make('courier_name')
                                    ->label('Courier Partner')
                                    ->placeholder('e.g. TCS or Leopards')
                                    ->helperText('Save the courier name and tracking number before dispatch.')
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('tracking_number')
                                    ->label('Tracking ID / Consignment #')
                                    ->placeholder('e.g. TCS-12345678')
                                    ->helperText('Use the consignment ID, not a tracking website address.')
                                    ->maxLength(100),
                            ]),

                        Forms\Components\Section::make('Financial Details')
                            ->schema([
                                Forms\Components\TextInput::make('subtotal')
                                    ->numeric()
                                    ->prefix('Rs.')
                                    ->required()
                                    ->disabled(),
                                Forms\Components\TextInput::make('shipping_fee')
                                    ->numeric()
                                    ->prefix('Rs.')
                                    ->required()
                                    ->disabled(),
                                Forms\Components\TextInput::make('discount_amount')
                                    ->numeric()
                                    ->prefix('Rs.')
                                    ->required()
                                    ->disabled(),
                                Forms\Components\TextInput::make('total_amount')
                                    ->numeric()
                                    ->prefix('Rs.')
                                    ->required()
                                    ->disabled(),
                                Forms\Components\Placeholder::make('verified_receipts')
                                    ->label('Verified net receipts')
                                    ->content(fn (?Order $record): string => 'PKR '.Money::decimal($record ? app(OrderLifecycle::class)->received($record) : 0)),
                                Forms\Components\Placeholder::make('advance_due')
                                    ->label('Required tailoring deposit')
                                    ->content(fn (?Order $record): string => 'PKR '.Money::decimal($record?->advance_due_minor ?? 0)),
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
                Tables\Columns\TextColumn::make('payment_status')->badge(),
                Tables\Columns\TextColumn::make('confirmation_channel')
                    ->label('Bot / Channel')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'whatsapp_bot' => 'success',
                        'manual_call' => 'info',
                        'advance_paid' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'whatsapp_bot' => 'WhatsApp Bot',
                        'manual_call' => 'Manual Call',
                        'advance_paid' => 'Advance Paid',
                        default => 'None',
                    }),
                Tables\Columns\TextColumn::make('confirmed_at')
                    ->label('Confirmation')
                    ->badge()
                    ->state(function (Order $record): string {
                        if ($record->confirmed_at) {
                            return 'Confirmed';
                        }
                        if (in_array($record->order_status, ['cancelled', 'returned'], true)) {
                            return 'Not applicable';
                        }
                        if ($record->payment_method === 'cod' && $record->order_status === 'pending') {
                            return 'Pending Confirmation';
                        }

                        return 'Awaiting payment or confirmation';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Confirmed' => 'success',
                        'Pending Confirmation' => 'warning',
                        'Awaiting payment or confirmation' => 'info',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Confirmed' => 'heroicon-m-check-badge',
                        'Pending Confirmation' => 'heroicon-m-exclamation-triangle',
                        default => 'heroicon-m-shield-check',
                    }),
                Tables\Columns\TextColumn::make('order_status')->badge(),
                Tables\Columns\TextColumn::make('cod_confirmation_expires_at')
                    ->label('COD Hold Deadline (PKT)')
                    ->formatStateUsing(fn ($state): string => $state->copy()->timezone('Asia/Karachi')->format('d M, g:i A'))
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
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
                Tables\Filters\SelectFilter::make('confirmation_channel')
                    ->options([
                        'whatsapp_bot' => 'WhatsApp Bot',
                        'manual_call' => 'Manual Call',
                        'advance_paid' => 'Advance Paid',
                        'none' => 'Unconfirmed / None',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('confirmManually')
                    ->label('Confirm COD')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $record) => $record->payment_method === 'cod' && $record->order_status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm COD Order Manually')
                    ->modalDescription('Confirm that the customer was contacted by phone and confirmed this order.')
                    ->action(fn (Order $record) => app(OrderLifecycle::class)->confirm($record, 'manual_call')),
                Tables\Actions\Action::make('printTailorSheet')
                    ->label('Tailor Sheet')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Order $record) => route('admin.orders.tailor-sheet', ['order' => $record]))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            PaymentsRelationManager::class,
            StitchingRequestsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
