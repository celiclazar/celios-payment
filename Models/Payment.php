<?php

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Modules\Payment\Enums\PaymentStatus;
use Modules\Payment\Enums\TransactionType;

class Payment extends Model
{
    protected $table = 'payments';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'status' => PaymentStatus::class,
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'metadata' => 'array',
        'raw_response' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }

            if (empty($payment->reference)) {
                $payment->reference = 'PAY-'.strtoupper(Str::random(12));
            }
        });
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'payment_id')->orderByDesc('id');
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::PAID;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED;
    }

    public function isRefunded(): bool
    {
        return in_array($this->status, [PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED]);
    }

    public function canBeRefunded(): bool
    {
        return in_array($this->status, [PaymentStatus::PAID, PaymentStatus::PARTIALLY_REFUNDED])
            && $this->amount > $this->getRefundedAmount();
    }

    public function getRefundedAmount(): float
    {
        return (float) $this->transactions()
            ->where('type', TransactionType::REFUND->value)
            ->where('status', 'success')
            ->sum('amount');
    }

    public function getRemainingRefundableAmount(): float
    {
        return max(0, (float) $this->amount - $this->getRefundedAmount());
    }

    public function markAsPaid(?string $gatewayTransactionId = null, array $raw = []): void
    {
        $this->update([
            'status' => PaymentStatus::PAID,
            'gateway_transaction_id' => $gatewayTransactionId ?? $this->gateway_transaction_id,
            'paid_at' => now(),
            'raw_response' => array_merge($this->raw_response ?? [], $raw),
        ]);
    }

    public function markAsFailed(string $errorMessage, ?string $gatewayTransactionId = null, array $raw = []): void
    {
        $this->update([
            'status' => PaymentStatus::FAILED,
            'gateway_transaction_id' => $gatewayTransactionId ?? $this->gateway_transaction_id,
            'error_message' => $errorMessage,
            'failed_at' => now(),
            'raw_response' => array_merge($this->raw_response ?? [], $raw),
        ]);
    }

    public function markAsRefunded(float $refundAmount): void
    {
        $totalRefunded = $this->getRefundedAmount() + $refundAmount;
        $newStatus = $totalRefunded >= (float) $this->amount
            ? PaymentStatus::REFUNDED
            : PaymentStatus::PARTIALLY_REFUNDED;

        $this->update([
            'status' => $newStatus,
            'refunded_at' => now(),
        ]);
    }

    // Scopes
    public function scopePaid($query)
    {
        return $query->where('status', PaymentStatus::PAID);
    }

    public function scopePending($query)
    {
        return $query->where('status', PaymentStatus::PENDING);
    }

    public function scopeGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }
}
