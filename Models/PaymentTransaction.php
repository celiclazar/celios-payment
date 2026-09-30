<?php

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payment\Enums\TransactionType;

class PaymentTransaction extends Model
{
    protected $table = 'payment_transactions';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => TransactionType::class,
        'amount' => 'decimal:2',
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PaymentTransaction $tx) {
            if (empty($tx->created_at)) {
                $tx->created_at = now();
            }
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
