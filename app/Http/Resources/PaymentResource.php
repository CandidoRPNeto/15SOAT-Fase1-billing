<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_order_id' => $this->service_order_id,
            'service_order_number' => $this->service_order_number,
            'amount' => (float) $this->amount,
            'status' => $this->status->value,
            'provider' => $this->provider,
            'checkout_url' => $this->checkout_url,
            'provider_payment_id' => $this->provider_payment_id,
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
        ];
    }
}
