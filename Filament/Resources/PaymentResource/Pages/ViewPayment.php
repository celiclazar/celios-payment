<?php

namespace Modules\Payment\Filament\Resources\PaymentResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Modules\Payment\Enums\PaymentStatus;
use Modules\Payment\Facades\Payment as PaymentFacade;
use Modules\Payment\Filament\Resources\PaymentResource;
use Modules\Payment\Models\Payment;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
        ];
    }
}
