<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Promotions & Settings';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Coupon Rules')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Coupon Code')
                            ->required()
                            ->unique(Coupon::class, 'code', ignoreRecord: true)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->maxLength(50),
                        Forms\Components\Select::make('discount_type')
                            ->label('Discount Type')
                            ->options([
                                'percentage' => 'Percentage (%)',
                                'fixed' => 'Fixed PKR Amount',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('discount_value')
                            ->label('Discount Value')
                            ->required()
                            ->numeric()
                            ->helperText('Enter percentage (e.g. 10) or flat PKR amount (e.g. 500)'),
                        Forms\Components\TextInput::make('min_order_amount')
                            ->label('Minimum Order Value')
                            ->prefix('Rs.')
                            ->default(0)
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('max_uses')
                            ->label('Total Usage Limit')
                            ->numeric()
                            ->placeholder('Unlimited'),
                        Forms\Components\TextInput::make('used_count')
                            ->label('Times Redeemed')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expiry Date & Time'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->weight('bold')
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('discount_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'percentage' => 'success',
                        'fixed' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('discount_value')
                    ->formatStateUsing(fn ($record) => $record->discount_type === 'percentage'
                        ? $record->discount_value . '%'
                        : 'Rs. ' . number_format($record->discount_value, 2))
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('min_order_amount')
                    ->label('Min Order')
                    ->money('PKR'),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('Usage')
                    ->formatStateUsing(fn ($record) => $record->used_count . ' / ' . ($record->max_uses ?? '∞')),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
