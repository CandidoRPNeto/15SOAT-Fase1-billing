<?php

namespace Database\Factories;

use App\Domain\Billing\BudgetStatus;
use App\Models\Budget;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Budget> */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'service_order_id' => $this->faker->unique()->numberBetween(1, 100000),
            'service_order_number' => 'OS-2026-'.$this->faker->unique()->numerify('#####'),
            'client_id' => 10,
            'status' => BudgetStatus::PENDING_APPROVAL,
            'total' => 230.00,
            'saga_id' => (string) Str::uuid(),
            'correlation_id' => 'corr-factory',
        ];
    }
}
