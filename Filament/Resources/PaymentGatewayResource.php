<?php

namespace Modules\Payment\Filament\Resources;

use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Payment\Filament\Resources\PaymentGatewayResource\Pages\CreatePaymentGateway;
use Modules\Payment\Filament\Resources\PaymentGatewayResource\Pages\EditPaymentGateway;
use Modules\Payment\Filament\Resources\PaymentGatewayResource\Pages\ListPaymentGateways;
use Celios\Core\Filament\Traits\HasModuleToggle;
use Modules\Payment\Models\PaymentGatewayConfig;

class PaymentGatewayResource extends Resource
{
    use HasModuleToggle;

    public const MODULE_KEY = 'payment';

    protected static ?string $model = PaymentGatewayConfig::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('sidebar.group_payments');
    }

    public static function getModelLabel(): string
    {
        return 'Payment Gateway';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Payment Gateways';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Gateway Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->label('Gateway Name'),

                                TextInput::make('code')
                                    ->required()
                                    ->disabled(fn (string $operation) => $operation === 'edit')
                                    ->unique(ignoreRecord: true)
                                    ->label('Identifier Code'),
                            ]),

                        Textarea::make('description')
                            ->rows(2)
                            ->label('Description'),

                        Grid::make(3)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active / Enabled')
                                    ->default(true),

                                Toggle::make('is_test_mode')
                                    ->label('Test / Sandbox Mode')
                                    ->default(true),

                                TextInput::make('sort_order')
                                    ->label('Display Order')
                                    ->numeric()
                                    ->default(0),
                            ]),

                        TagsInput::make('supported_currencies')
                            ->label('Supported Currencies (ISO codes, e.g. EUR, USD, RSD)')
                            ->placeholder('Add currency code and press Enter'),

                        KeyValue::make('settings')
                            ->label('Gateway Credentials & Custom Settings')
                            ->keyLabel('Setting Key (e.g. secret_key, key, account_number)')
                            ->valueLabel('Value'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Gateway')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('info'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                IconColumn::make('is_test_mode')
                    ->label('Test Mode')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Sort')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('sort_order', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentGateways::route('/'),
            'create' => CreatePaymentGateway::route('/create'),
            'edit' => EditPaymentGateway::route('/{record}/edit'),
        ];
    }
}
