<?php

namespace Modules\Payment\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Payment\Enums\PaymentStatus;
use Modules\Payment\Facades\Payment as PaymentFacade;
use Modules\Payment\Filament\Resources\PaymentResource\Pages\ListPayments;
use Modules\Payment\Filament\Resources\PaymentResource\Pages\ViewPayment;
use Celios\Core\Filament\Traits\HasModuleToggle;
use Modules\Payment\Models\Payment;

class PaymentResource extends Resource
{
    use HasModuleToggle;

    public const MODULE_KEY = 'payment';

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('sidebar.group_payments');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Payment Information')
                    ->columnSpan(1)
                    ->schema([
                        Placeholder::make('reference')
                            ->label('Reference')
                            ->content(fn (?Payment $record) => $record?->reference),

                        Placeholder::make('gateway')
                            ->label('Gateway')
                            ->content(fn (?Payment $record) => strtoupper($record?->gateway ?? '')),

                        Placeholder::make('gateway_transaction_id')
                            ->label('Gateway Transaction ID')
                            ->content(fn (?Payment $record) => $record?->gateway_transaction_id ?: '—'),

                        Placeholder::make('amount')
                            ->label('Amount')
                            ->content(fn (?Payment $record) => $record ? number_format((float) $record->amount, 2).' '.$record->currency : '—'),

                        Placeholder::make('status')
                            ->label('Status')
                            ->content(fn (?Payment $record) => $record?->status?->label()),

                        Placeholder::make('paid_at')
                            ->label('Paid At')
                            ->content(fn (?Payment $record) => $record?->paid_at ? $record->paid_at->format('Y-m-d H:i:s') : '—'),
                    ]),

                Section::make('Customer & Details')
                    ->columnSpan(1)
                    ->schema([
                        Placeholder::make('customer_name')
                            ->label('Customer Name')
                            ->content(fn (?Payment $record) => $record?->customer_name ?: '—'),

                        Placeholder::make('customer_email')
                            ->label('Customer Email')
                            ->content(fn (?Payment $record) => $record?->customer_email ?: '—'),

                        Placeholder::make('customer_phone')
                            ->label('Phone')
                            ->content(fn (?Payment $record) => $record?->customer_phone ?: '—'),

                        Placeholder::make('customer_ip')
                            ->label('IP Address')
                            ->content(fn (?Payment $record) => $record?->customer_ip ?: '—'),

                        Placeholder::make('error_message')
                            ->label('Error Message')
                            ->visible(fn (?Payment $record) => ! empty($record?->error_message))
                            ->content(fn (?Payment $record) => $record?->error_message),
                    ]),

                Section::make('Metadata & Raw Response')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        Placeholder::make('metadata')
                            ->label('Metadata')
                            ->content(fn (?Payment $record) => json_encode($record?->metadata, JSON_PRETTY_PRINT)),

                        Placeholder::make('raw_response')
                            ->label('Raw Provider Response')
                            ->content(fn (?Payment $record) => json_encode($record?->raw_response, JSON_PRETTY_PRINT)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('gateway')
                    ->label('Gateway')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->color('info'),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn (Payment $record) => number_format((float) $record->amount, 2).' '.$record->currency)
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Payment $record) => $record->status->color())
                    ->formatStateUsing(fn (Payment $record) => $record->status->label()),

                TextColumn::make('customer_email')
                    ->label('Customer')
                    ->description(fn (Payment $record) => $record->customer_name ?: null)
                    ->searchable(),

                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('gateway')
                    ->options([
                        'stripe' => 'Stripe',
                        'mock' => 'Mock / Sandbox',
                        'bank_transfer' => 'Bank Transfer',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        PaymentStatus::PENDING->value => 'Pending',
                        PaymentStatus::PAID->value => 'Paid',
                        PaymentStatus::FAILED->value => 'Failed',
                        PaymentStatus::REFUNDED->value => 'Refunded',
                        PaymentStatus::PARTIALLY_REFUNDED->value => 'Partially Refunded',
                        PaymentStatus::ACTION_REQUIRED->value => 'Action Required',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('mark_as_paid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Payment $record) => $record->status === PaymentStatus::PENDING)
                    ->requiresConfirmation()
                    ->action(function (Payment $record) {
                        $record->markAsPaid('manual_'.time());
                        Notification::make()
                            ->title('Payment marked as paid successfully')
                            ->success()
                            ->send();
                    }),

                Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Payment $record) => $record->canBeRefunded())
                    ->schema([
                        TextInput::make('amount')
                            ->label('Refund Amount')
                            ->numeric()
                            ->required()
                            ->default(fn (Payment $record) => $record->getRemainingRefundableAmount())
                            ->maxValue(fn (Payment $record) => $record->getRemainingRefundableAmount()),
                        TextInput::make('reason')
                            ->label('Refund Reason')
                            ->placeholder('e.g. Customer return'),
                    ])
                    ->action(function (Payment $record, array $data) {
                        try {
                            $response = PaymentFacade::refund($record, (float) $data['amount'], $data['reason'] ?? null);
                            if ($response->isSuccessful) {
                                Notification::make()
                                    ->title('Refund processed successfully')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Refund failed')
                                    ->body($response->errorMessage)
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Refund error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
