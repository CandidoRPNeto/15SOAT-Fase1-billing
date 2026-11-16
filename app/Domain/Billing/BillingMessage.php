<?php

namespace App\Domain\Billing;

/**
 * Routing keys the Billing Service consumes (cmd.billing.*) and produces
 * (evt.billing.*). Contract: docs/asyncapi.yaml; commands: OS Service's asyncapi.
 */
enum BillingMessage: string
{
    case CREATE_BUDGET = 'cmd.billing.create_budget';
    case VOID_BUDGET = 'cmd.billing.void_budget';
    case REQUEST_PAYMENT = 'cmd.billing.request_payment';

    case BUDGET_CREATED = 'evt.billing.budget_created';
    case BUDGET_CREATION_FAILED = 'evt.billing.budget_creation_failed';
    case BUDGET_APPROVED = 'evt.billing.budget_approved';
    case BUDGET_REJECTED = 'evt.billing.budget_rejected';
    case PAYMENT_REQUESTED = 'evt.billing.payment_requested';
    case PAYMENT_CONFIRMED = 'evt.billing.payment_confirmed';
}
