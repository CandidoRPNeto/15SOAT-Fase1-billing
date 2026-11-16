<?php

namespace App\Models;

use App\Domain\Billing\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $service_order_id
 * @property string $service_order_number
 * @property int $client_id
 * @property string $amount
 * @property PaymentStatus $status
 * @property string $provider
 * @property string|null $preference_id
 * @property string|null $checkout_url
 * @property string|null $provider_payment_id
 * @property string $saga_id
 * @property string $correlation_id
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $confirmed_at
 */
class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'service_order_id', 'service_order_number', 'client_id', 'amount', 'status', 'provider', 'preference_id',
        'checkout_url', 'provider_payment_id', 'saga_id', 'correlation_id', 'last_checked_at', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'last_checked_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }
}
