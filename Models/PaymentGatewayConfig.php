<?php

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewayConfig extends Model
{
    protected $table = 'payment_gateways';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_test_mode' => 'boolean',
        'settings' => 'array',
        'supported_currencies' => 'array',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
