<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\ProductResource\RelationManagers\VariantsRelationManager;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Shop & Catalog';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('General Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $operation, $state, Set $set) {
                                        if ($operation === 'create') {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->unique(Product::class, 'slug', ignoreRecord: true)
                                    ->maxLength(255),
                                Forms\Components\Select::make('category_id')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Forms\Components\RichEditor::make('description')
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Forms\Components\Section::make('Fabric & Styling')
                            ->schema([
                                Forms\Components\TextInput::make('fabric_type')
                                    ->label('Fabric Type')
                                    ->placeholder('e.g. Lawn, Linen, Organza, Cotton')
                                    ->required()
                                    ->maxLength(100),
                                Forms\Components\Select::make('piece_type')
                                    ->label('Piece Configuration')
                                    ->options([
                                        '1-piece' => '1-Piece (Kurti)',
                                        '2-piece' => '2-Piece (Shirt + Trouser)',
                                        '3-piece' => '3-Piece (Complete Suit)',
                                    ])
                                    ->required(),
                            ])->columns(2),

                        Forms\Components\Section::make('Fit, size guide & care')
                            ->description('Use verified measurements for this article only. Leave the size chart blank if measurements are not available.')
                            ->schema([
                                Forms\Components\Select::make('size_chart_type')
                                    ->label('What the chart measures')
                                    ->options([
                                        'garment' => 'Finished garment circumference',
                                        'body' => 'Recommended body circumference',
                                    ])
                                    ->requiredWith('size_chart'),
                                Forms\Components\Repeater::make('size_chart')
                                    ->label('Size chart (inches)')
                                    ->defaultItems(0)
                                    ->schema([
                                        Forms\Components\TextInput::make('size')->required()->distinct()->maxLength(20),
                                        Forms\Components\TextInput::make('bust')->required()->numeric()->minValue(0.1)->maxValue(120)->step(0.1),
                                        Forms\Components\TextInput::make('waist')->numeric()->minValue(0.1)->maxValue(120)->step(0.1),
                                        Forms\Components\TextInput::make('hips')->numeric()->minValue(0.1)->maxValue(120)->step(0.1),
                                        Forms\Components\TextInput::make('length')->label('Shirt length')->numeric()->minValue(0.1)->maxValue(120)->step(0.1),
                                    ])->columns(5),
                                Forms\Components\Textarea::make('fit_notes')
                                    ->helperText('Record the cut or fit, and model height and size worn if available.')
                                    ->maxLength(1000),
                                Forms\Components\Textarea::make('care_instructions')->maxLength(1000),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Pricing')
                            ->schema([
                                Forms\Components\TextInput::make('base_price')
                                    ->label('Regular Price')
                                    ->prefix('Rs.')
                                    ->required()
                                    ->numeric(),
                                Forms\Components\TextInput::make('sale_price')
                                    ->label('Sale Price')
                                    ->prefix('Rs.')
                                    ->numeric()
                                    ->placeholder('Optional sale price'),
                            ]),

                        Forms\Components\Section::make('Status & Visibility')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'active' => 'Active',
                                        'archived' => 'Archived',
                                    ])
                                    ->default('active')
                                    ->required(),
                                Forms\Components\Toggle::make('is_featured')
                                    ->label('Feature on Homepage')
                                    ->default(false),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('primaryImage.image_url')
                    ->label('Thumbnail')
                    ->square(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('category.name')
                    ->badge()
                    ->color('secondary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fabric_type')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('piece_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('base_price')
                    ->label('Price')
                    ->money('PKR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Sale Price')
                    ->money('PKR')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Featured'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'draft' => 'warning',
                        'archived' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('variants_count')
                    ->counts('variants')
                    ->label('Variants')
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('piece_type')
                    ->options([
                        '1-piece' => '1-Piece',
                        '2-piece' => '2-Piece',
                        '3-piece' => '3-Piece',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured Only'),
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
            VariantsRelationManager::class,
            ImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
