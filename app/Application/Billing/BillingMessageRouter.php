<?php

namespace App\Application\Billing;

use App\Application\Ports\MessageRouter;
use App\Domain\Billing\BillingMessage;
use App\Domain\Messaging\MessageEnvelope;
use InvalidArgumentException;

/**
 * Routes saga commands addressed to Billing.
 */
class BillingMessageRouter implements MessageRouter
{
    public function __construct(
        private readonly CreateBudget $createBudget,
        private readonly VoidBudget $voidBudget,
        private readonly RequestPayment $requestPayment,
    ) {}

    public function bindings(): array
    {
        return ['cmd.billing.*'];
    }

    public function route(MessageEnvelope $envelope): void
    {
        match (BillingMessage::tryFrom($envelope->type)) {
            BillingMessage::CREATE_BUDGET => $this->createBudget->handle($envelope),
            BillingMessage::VOID_BUDGET => $this->voidBudget->handle($envelope),
            BillingMessage::REQUEST_PAYMENT => $this->requestPayment->handle($envelope),
            default => throw new InvalidArgumentException("Unsupported message type '{$envelope->type}'; expected one of cmd.billing.create_budget|void_budget|request_payment"),
        };
    }
}
