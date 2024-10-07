<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\SelectColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ToggleButtons;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\Resources\OrderResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\OrderResource\RelationManagers\AddressRelationManager;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make()->schema([
                    Section::make('Order Information')
                        ->schema([
                            Select::make('user_id')
                                ->relationship('user', 'name')
                                ->required()
                                ->searchable()
                                ->preload(),
                            Select::make('payment_method')
                                ->options([
                                    'stripe' => 'Stripe',
                                    'cod' => 'Cash on Delivery'
                                ])
                                ->required(),
                            Select::make('payment_status')
                                ->options([
                                    'pending' => 'Pending',
                                    'paid' => 'Paid',
                                    'failed' => 'Failed',
                                ])
                                ->default('pending')
                                ->required(),
                            ToggleButtons::make('status')
                                ->inline()
                                ->default('new')
                                ->required()
                                ->options([
                                    'new' => 'New',
                                    'processing' => 'Processing',
                                    'shipped' => 'Shipped',
                                    'delivered' => 'Delivered',
                                    'canceled' => 'Canceled',
                                ])
                                ->colors([
                                    'new' => 'info',
                                    'processing' => 'warning',
                                    'shipped' => 'success',
                                    'delivered' => 'success',
                                    'canceled' => 'danger',
                                ])
                                ->icons([
                                    'new' => 'heroicon-m-sparkles',
                                    'processing' => 'heroicon-m-arrow-path',
                                    'shipped' => 'heroicon-m-truck',
                                    'delivered' => 'heroicon-m-check-badge',
                                    'canceled' => 'heroicon-m-x-circle',
                                ]),
                                Select::make('currency')
                                    ->options([
                                        'idr' => 'IDR',
                                        'inr' => 'INR',
                                        'usd' => 'USD',
                                    ])
                                    ->default('idr')
                                    ->required(),
                                Select::make('shipping_method')
                                    ->options([
                                        'jnt' => 'JNT',
                                        'jne' => 'JNE',
                                        'siCepat' => 'Si Cepat',
                                    ])
                                    ->required(),

                                Textarea::make('notes')
                                    ->columnSpanFull(),
                        ])->columns(2),

                        Section::make('Order Items')
                            ->schema([
                                Repeater::make('items')
                                    ->relationship()
                                    ->schema([
                                        Select::make('product_id')
                                            ->relationship('product', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->distinct()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->columnSpan(4)
                                            ->reactive()
                                            ->afterStateUpdated(function($state, Set $set, Get $get) {
                                                $unitPrice = Product::find($state)?->price ?? 0;
                                                $set('unit_amount', $unitPrice);
                                                $set('total_amount', $unitPrice * $get('quantity'));
                                            }),
                                            // ->afterStateUpdated(fn($state, Set $set) => $set('unit_amount', Product::find($state)?->price ?? 0))
                                            // ->afterStateUpdated(fn($state, Set $set) => $set('total_amount', Product::find($state)?->price ?? 0)),
                                        TextInput::make('quantity')
                                            ->numeric()
                                            ->required()
                                            ->default(1)    
                                            ->minValue(1)
                                            ->columnSpan(2)
                                            ->reactive()
                                            ->afterStateUpdated(fn($state, Set $set, Get $get) => $set('total_amount', $state * $get('unit_amount'))),
                                            // ->afterStateUpdated(fn($state, Set $set, Get $get) => $set('total_amount', $state*$get('unit_amount'))),
                                        TextInput::make('unit_amount')
                                            ->numeric()
                                            ->required()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(3),
                                        TextInput::make('total_amount')
                                            ->numeric()
                                            ->required()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(3),
                                        
                                    ])->columns(12),

                                    Placeholder::make('grand_total_placeholder')
                                        ->label('Grand Total')
                                        ->content(function(Get $get, Set $set) {
                                            $total = 0;
                                            if (!$repeaters = $get('items')) {
                                                return $total;
                                            }

                                            foreach ($repeaters as $key => $value) {
                                                $total += $get("items.{$key}.total_amount");
                                            }
                                            $set('grand_total', $total);
                                            return Number::currency($total, 'IDR');

                                        }),

                                        Hidden::make('grand_total')
                                            ->default(0),
                            ]),
                ])->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('grand_total')
                    ->numeric()
                    ->sortable()
                    ->searchable()
                    ->money('IDR'),
                TextColumn::make('payment_method')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('shipping_method')
                    ->searchable()
                    ->sortable(),
                SelectColumn::make('status')
                    ->options([
                        'new' => 'New',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'canceled' => 'Canceled',
                    ])
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
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
            AddressRelationManager::class
        ];
    }
    
    public static function getNavigationBadge() : ?string {
        return static::getModel()::count();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
