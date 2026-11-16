<?php

namespace Tests\Unit;

use App\Application\Messaging\OutboxRecorder;
use App\Application\Ports\MessageConsumer;
use App\Application\Ports\MessagePublisher;
use App\Domain\Messaging\MessageEnvelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\InMemoryMessageConsumer;
use Tests\Fakes\InMemoryMessagePublisher;
use Tests\TestCase;

class BusWorkCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_relays_outbox_and_consumes_commands(): void
    {
        $publisher = new InMemoryMessagePublisher;
        $consumer = new InMemoryMessageConsumer;
        $this->app->instance(MessagePublisher::class, $publisher);
        $this->app->instance(MessageConsumer::class, $consumer);
        (new OutboxRecorder)->record(MessageEnvelope::create('evt.billing.budget_created', 's', 'c', []));
        $consumer->queue[] = MessageEnvelope::create('cmd.billing.void_budget', 's', 'c', ['service_order_id' => 1]);

        $this->artisan('bus:work', ['--iterations' => 1, '--poll' => 0])->assertSuccessful();

        $this->assertSame(['evt.billing.budget_created'], $publisher->publishedTypes());
        $this->assertSame([], $consumer->deadLettered);
        $this->assertDatabaseCount('processed_messages', 1);
    }
}
