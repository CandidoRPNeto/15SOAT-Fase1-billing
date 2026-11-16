<?php

namespace App\Domain\Billing;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
}
