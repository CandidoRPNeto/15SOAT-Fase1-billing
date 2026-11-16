<?php

namespace Database\Factories;

use App\Domain\Billing\PaymentStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'service_order_id' => $this->faker->unique()->numberBetween(1, 100000),
            'service_order_number' => 'OS-2026-'.$this->faker->unique()->numerify('#####'),
            'client_id' => 10,
            'amount' => 230.00,
            'status' => PaymentStatus::PENDING,
            'provider' => 'fake',
            'checkout_url' => 'https://checkout.test/x',
            'saga_id' => (string) Str::uuid(),
            'correlation_id' => 'corr-factory',
        ];
    }
}
