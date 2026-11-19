<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerMeasurementResource\Pages;
use App\Models\CustomerMeasurement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerMeasurementResource extends Resource
{
    protected static ?string $model = CustomerMeasurement::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Orders & Tailoring';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Customer Profile')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Customer Account')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('profile_name')
                            ->placeholder('e.g. Standard Fit, Loose Kurti Fit')
                            ->required(),
                        Forms\Components\Select::make('unit')
                            ->options([
                                'inches' => 'Inches',
                                'cm' => 'Centimeters (cm)',
                            ])
                            ->default('inches')
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Shirt / Kurta Measurements')
                    ->schema([
                        Forms\Components\TextInput::make('shirt_length')->label('Length')->numeric(),
                        Forms\Components\TextInput::make('shoulder')->label('Shoulder / Teera')->numeric(),
                        Forms\Components\TextInput::make('chest')->label('Chest / Bust')->numeric(),
                        Forms\Components\TextInput::make('waist')->label('Waist')->numeric(),
                        Forms\Components\TextInput::make('hips')->label('Hips / Chaak')->numeric(),
                        Forms\Components\TextInput::make('sleeve_length')->label('Sleeve Length')->numeric(),
                        Forms\Components\TextInput::make('armhole')->label('Armhole')->numeric(),
                        Forms\Components\TextInput::make('daman')->label('Daman')->numeric(),
                        Forms\Components\TextInput::make('neck_front')->label('Neck Depth (Front)')->numeric(),
                        Forms\Components\TextInput::make('neck_back')->label('Neck Depth (Back)')->numeric(),
                    ])->columns(5),

                Forms\Components\Section::make('Trouser / Bottom Measurements')
                    ->schema([
                        Forms\Components\TextInput::make('trouser_length')->label('Trouser Length')->numeric(),
                        Forms\Components\TextInput::make('trouser_waist')->label('Trouser Waist')->numeric(),
                        Forms\Components\TextInput::make('pancha')->label('Pancha / Ankle Opening')->numeric(),
                    ])->columns(3),

                Forms\Components\Section::make('Additional Notes')
                    ->schema([
                        Forms\Components\Textarea::make('extra_notes')
                            ->placeholder('Special fit preferences, posture, shoulder slopes...')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Guest Measurement'),
                Tables\Columns\TextColumn::make('profile_name')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('unit')
                    ->badge()
                    ->color('secondary'),
                Tables\Columns\TextColumn::make('shirt_length')
                    ->label('Shirt Length')
                    ->numeric(),
                Tables\Columns\TextColumn::make('chest')
                    ->label('Chest')
                    ->numeric(),
                Tables\Columns\TextColumn::make('trouser_length')
                    ->label('Trouser Length')
                    ->numeric(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('unit')
                    ->options([
                        'inches' => 'Inches',
                        'cm' => 'Centimeters',
                    ]),
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
            'index' => Pages\ListCustomerMeasurements::route('/'),
            'create' => Pages\CreateCustomerMeasurement::route('/create'),
            'edit' => Pages\EditCustomerMeasurement::route('/{record}/edit'),
        ];
    }
}
