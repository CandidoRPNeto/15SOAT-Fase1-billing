<?php

namespace App\Application\Billing;

use App\Domain\Billing\BudgetStatus;
use App\Domain\Messaging\MessageEnvelope;
use App\Models\Budget;
use Illuminate\Support\Facades\Log;

/**
 * Compensation for cmd.billing.void_budget. Succeeds even when no budget
 * exists (the create command may never have arrived).
 */
class VoidBudget
{
    public function handle(MessageEnvelope $command): void
    {
        $voided = Budget::where('service_order_id', $command->payloadValue('service_order_id'))
            ->where('status', '!=', BudgetStatus::VOID)
            ->update([
                'status' => BudgetStatus::VOID,
                'void_reason' => mb_substr((string) ($command->payload['reason'] ?? 'compensação da saga'), 0, 255),
            ]);

        Log::warning('billing.budget_voided', ['service_order_id' => $command->payloadValue('service_order_id'), 'count' => $voided]);
    }
}
