<?php

namespace Modules\Payment\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Payment\Enums\PaymentStatus;
use Modules\Payment\Models\Payment;

trait HasPayments
{
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable')->orderByDesc('id');
    }

    public function latestPayment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable')->latestOfMany();
    }

    public function successfulPayments(): MorphMany
    {
        return $this->payments()->where('status', PaymentStatus::PAID);
    }

    public function isPaid(): bool
    {
        return $this->successfulPayments()->exists();
    }

    public function totalPaidAmount(): float
    {
        return (float) $this->successfulPayments()->sum('amount');
    }
}
