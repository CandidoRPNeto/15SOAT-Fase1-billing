<?php

namespace Tests\Unit;

use App\Application\Ports\PaymentGateway;
use App\Infrastructure\Payments\MercadoPagoPaymentGateway;
use App\Infrastructure\Payments\OfflinePaymentGateway;
use InvalidArgumentException;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_resolves_gateway_from_config(): void
    {
        config(['payments.gateway' => 'mercadopago']);
        $this->app->forgetInstance(PaymentGateway::class);
        $this->assertInstanceOf(MercadoPagoPaymentGateway::class, app(PaymentGateway::class));

        config(['payments.gateway' => 'offline']);
        $this->app->forgetInstance(PaymentGateway::class);
        $this->assertInstanceOf(OfflinePaymentGateway::class, app(PaymentGateway::class));
    }

    public function test_unknown_gateway_names_the_valid_options(): void
    {
        config(['payments.gateway' => 'paypal']);
        $this->app->forgetInstance(PaymentGateway::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown PAYMENT_GATEWAY 'paypal'; expected mercadopago|offline");
        app(PaymentGateway::class);
    }
}
