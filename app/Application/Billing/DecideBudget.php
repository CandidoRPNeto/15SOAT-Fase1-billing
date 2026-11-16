<?php

namespace App\Application\Billing;

use App\Domain\Billing\BillingMessage;
use App\Domain\Billing\BudgetDecisionException;
use App\Domain\Billing\BudgetStatus;
use App\Models\Budget;
use Illuminate\Support\Facades\DB;

/**
 * Client's approval/rejection of a budget (REST, via gateway). The decision
 * and its saga event are committed together (outbox).
 *
 * Example: $decide->approve($budget);
 */
class DecideBudget
{
    public function __construct(private readonly BillingEventEmitter $events) {}

    public function approve(Budget $budget): Budget
    {
        return $this->decide($budget, BudgetStatus::APPROVED, BillingMessage::BUDGET_APPROVED);
    }

    public function reject(Budget $budget): Budget
    {
        return $this->decide($budget, BudgetStatus::REJECTED, BillingMessage::BUDGET_REJECTED);
    }

    private function decide(Budget $budget, BudgetStatus $to, BillingMessage $event): Budget
    {
        if (! $budget->status->isDecidable()) {
            throw new BudgetDecisionException(
                "Budget {$budget->id} is {$budget->status->value}; expected ".BudgetStatus::PENDING_APPROVAL->value
            );
        }

        DB::transaction(function () use ($budget, $to, $event) {
            $budget->update(['status' => $to, 'decided_at' => now()]);
            $this->events->emit($event, $budget->saga_id, $budget->correlation_id, [
                'service_order_id' => $budget->service_order_id,
                'budget_id' => $budget->id,
            ]);
        });

        return $budget;
    }
}
