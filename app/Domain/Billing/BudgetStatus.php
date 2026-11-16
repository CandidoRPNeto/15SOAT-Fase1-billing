<?php

namespace App\Domain\Billing;

enum BudgetStatus: string
{
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case VOID = 'void';

    public function isDecidable(): bool
    {
        return $this === self::PENDING_APPROVAL;
    }
}
