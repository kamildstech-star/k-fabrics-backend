<?php

namespace Tests\Feature;

use App\Jobs\SendOrderReceipt;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderReceiptDelivery;
use App\Services\OrderTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class PrivateOrderReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['checkout.cod_mode' => 'manual', 'checkout.receipt_email_enabled' => false]);
        $this->seed();
    }

    public function test_redacted_receipt_can_be_retrieved_only_with_tracking_credentials(): void
    {
        $order = $this->place()->assertCreated()
            ->assertJsonPath('data.receipt.currency', 'PKR')
            ->assertJsonCount(1, 'data.receipt.lines')
            ->assertJsonMissingPath('data.receipt.customer_email');
        $keys = [
            'order_number' => $order->json('data.order_number'),
            'tracking_token' => $order->json('data.tracking_token'),
        ];

        $response = $this->postJson('/api/v1/orders/receipt', $keys)
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data', $order->json('data.receipt'))
            ->assertJsonMissingPath('data.tracking_token')
            ->assertJsonMissingPath('data.customer_name')
            ->assertJsonMissingPath('data.customer_email')
            ->assertJsonMissingPath('data.shipping_address')
            ->assertJsonMissingPath('data.customer_phone')
            ->assertJsonMissingPath('data.notes');
        $response->assertDontSee('private fixture address')->assertDontSee('customer@example.test');
        $this->postJson('/api/v1/orders/receipt', array_replace($keys, ['tracking_token' => str_repeat('0', 64)]))->assertNotFound();
        $this->getJson('/api/v1/orders/receipt')->assertStatus(405);
        $this->postJson('/api/v1/orders/track', $keys)->assertOk()->assertJsonMissingPath('data.receipt');
    }

    public function test_email_is_queued_once_only_for_matching_verified_account_on_async_queue(): void
    {
        $this->mailConfig();
        Queue::fake();
        $key = (string) Str::uuid();
        $this->place()->assertCreated();
        Queue::assertNotPushed(SendOrderReceipt::class);

        $user = User::factory()->create(['email' => 'customer@example.test', 'email_verified_at' => null, 'role' => 'customer']);
        $this->actingAs($user);
        $this->place()->assertCreated();
        Queue::assertNotPushed(SendOrderReceipt::class);

        $user->forceFill(['email_verified_at' => now()])->save();
        $this->place('mismatch@example.test')->assertCreated();
        Queue::assertNotPushed(SendOrderReceipt::class);
        $result = $this->place(key: $key)->assertCreated();
        $this->place(key: $key)->assertOk()->assertJsonPath('data.id', $result->json('data.id'));
        Queue::assertPushed(SendOrderReceipt::class, 1);
        Queue::assertPushed(SendOrderReceipt::class, fn ($job) => $job->orderId === $result->json('data.id'));
    }

    public function test_receipt_sender_uses_trusted_destination_and_persists_acceptance_only_once(): void
    {
        $this->mailConfig();
        Queue::fake();
        $user = User::factory()->create(['email' => 'customer@example.test', 'role' => 'customer']);
        $this->actingAs($user);
        $response = $this->place()->assertCreated();
        $client = Mockery::mock();
        Mail::shouldReceive('mailer')->once()->with('smtp')->andReturn($client);
        $client->shouldReceive('raw')->once()->andReturnUsing(function (string $body, callable $callback) use ($response) {
            $message = new Message(new Email);
            $callback($message);
            $destination = $message->getSymfonyMessage()->getTo();
            $this->assertCount(1, $destination);
            $this->assertSame('customer@example.test', $destination[0]->getAddress());
            $this->assertStringContainsString($response->json('data.tracking_token'), $body);
            $this->assertStringContainsString("https://shop.example.test/track-order\n", $body);
            $this->assertStringNotContainsString('private fixture address', $body);

            return new \stdClass;
        });

        $order = Order::findOrFail($response->json('data.id'));
        $job = new SendOrderReceipt($order->id);
        $job->handle(app(OrderReceiptDelivery::class), app(OrderTracking::class));
        $job->handle(app(OrderReceiptDelivery::class), app(OrderTracking::class));
        $this->assertNotNull($order->fresh()->receipt_email_sent_at);
    }

    public function test_receipt_queue_error_does_not_invalidate_order(): void
    {
        $this->mailConfig();
        $delivery = Mockery::mock(OrderReceiptDelivery::class);
        $delivery->shouldReceive('eligible')->once()->andThrow(new RuntimeException('Fixture service failure'));
        $this->app->instance(OrderReceiptDelivery::class, $delivery);
        $created = $this->place()->assertCreated();
        $this->assertDatabaseHas('orders', ['id' => $created->json('data.id')]);
    }

    private function mailConfig(): void
    {
        config([
            'checkout.receipt_email_enabled' => true,
            'checkout.receipt_email_mailer' => 'smtp',
            'checkout.receipt_tracking_url' => 'https://shop.example.test/track-order',
            'cors.allowed_origins' => ['https://shop.example.test'],
            'sanctum.stateful' => ['shop.example.test'],
            'queue.default' => 'database',
        ]);
    }

    private function place(string $email = 'customer@example.test', ?string $key = null)
    {
        $variant = ProductVariant::where('size', '!=', 'Custom')->where('stock_quantity', '>=', 5)->firstOrFail();

        return $this->postJson('/api/v1/orders', [
            'customer_name' => 'private fixture name',
            'customer_phone' => '03005556677',
            'customer_email' => $email,
            'shipping_address' => 'private fixture address',
            'city' => 'Lahore',
            'notes' => 'private fixture notes',
            'payment_method' => 'cod',
            'items' => [['product_id' => $variant->product_id, 'variant_id' => $variant->id, 'quantity' => 1]],
        ], ['Idempotency-Key' => $key ?? (string) Str::uuid(), 'Origin' => 'https://shop.example.test']);
    }
}
