<?php

namespace Modules\Payment\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payment\Models\PaymentGatewayConfig;

/**
 * @mixin PaymentGatewayConfig
 */
class PaymentGatewayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
            'is_test_mode' => (bool) $this->is_test_mode,
            'supported_currencies' => $this->supported_currencies ?? [],
            'sort_order' => $this->sort_order ?? 0,
        ];
    }
}
