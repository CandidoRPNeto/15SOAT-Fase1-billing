<?php

namespace App\Providers;

use App\Application\Billing\BillingMessageRouter;
use App\Application\Ports\MessageConsumer;
use App\Application\Ports\MessagePublisher;
use App\Application\Ports\MessageRouter;
use App\Application\Ports\PaymentGateway;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqMessageConsumer;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqMessagePublisher;
use App\Infrastructure\Payments\MercadoPagoPaymentGateway;
use App\Infrastructure\Payments\OfflinePaymentGateway;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RabbitMqConnectionFactory::class, fn () => new RabbitMqConnectionFactory(config('messaging.rabbitmq')));
        $this->app->bind(MessageRouter::class, BillingMessageRouter::class);
        $this->app->singleton(MessagePublisher::class, RabbitMqMessagePublisher::class);
        $this->app->singleton(MessageConsumer::class, RabbitMqMessageConsumer::class);
        $this->app->singleton(PaymentGateway::class, fn () => $this->paymentGateway((string) config('payments.gateway')));
    }

    private function paymentGateway(string $name): PaymentGateway
    {
        return match ($name) {
            'mercadopago' => new MercadoPagoPaymentGateway(config('payments.mercadopago')),
            'offline' => new OfflinePaymentGateway((string) config('payments.offline.base_url')),
            default => throw new InvalidArgumentException("Unknown PAYMENT_GATEWAY '{$name}'; expected mercadopago|offline"),
        };
    }

    public function boot(): void {}
}
