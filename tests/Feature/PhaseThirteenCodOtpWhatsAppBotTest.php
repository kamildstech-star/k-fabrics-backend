<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmationWhatsApp;
use App\Models\Order;
use App\Models\OtpVerification;
use App\Models\ProductVariant;
use App\Services\WhatsAppOrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseThirteenCodOtpWhatsAppBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['checkout.cod_mode' => 'otp']);
        $this->seed();
    }

    public function test_otp_is_throttled_and_has_a_strict_single_use_checkout_token(): void
    {
        Queue::fake();
        $payload = $this->checkoutPayload();
        $idempotencyKey = (string) Str::uuid();

        $this->postJson('/api/v1/orders', $payload, ['Idempotency-Key' => $idempotencyKey])
            ->assertUnprocessable()->assertJsonValidationErrors('verification_token');

        $send = $this->postJson('/api/v1/checkout/send-otp', ['phone' => $payload['customer_phone']])
            ->assertOk()->assertJsonPath('channel', 'sms');
        $this->postJson('/api/v1/checkout/send-otp', ['phone' => $payload['customer_phone']])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
        $verification = $this->postJson('/api/v1/checkout/verify-otp', [
            'phone' => $payload['customer_phone'],
            'code' => $send->json('debug_code'),
        ])->assertOk();

        $this->postJson('/api/v1/orders', $payload, ['Idempotency-Key' => $idempotencyKey])
            ->assertUnprocessable()->assertJsonValidationErrors('verification_token');

        $payload['verification_token'] = $verification->json('verification_token');
        $first = $this->postJson('/api/v1/orders', $payload, ['Idempotency-Key' => $idempotencyKey])
            ->assertCreated()->assertJsonPath('data.order_status', 'pending');
        $this->assertNotNull($first->json('data.phone_verified_at'));
        $this->postJson('/api/v1/orders', $payload, ['Idempotency-Key' => $idempotencyKey])
            ->assertSuccessful()->assertJsonPath('data.order_number', $first->json('data.order_number'))
            ->assertJsonPath('data.tracking_token', $first->json('data.tracking_token'));

        $this->postJson('/api/v1/orders', $payload, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('verification_token');

        $this->assertNotNull(OtpVerification::latest('id')->firstOrFail()->consumed_at);
    }

    public function test_sms_sends_through_configured_transport_without_disclosing_the_code(): void
    {
        config([
            'services.twilio.account_sid' => 'AC_TEST',
            'services.twilio.auth_token' => 'fictional-test-token',
            'services.twilio.messaging_service_sid' => 'MG_TEST',
        ]);
        Http::preventStrayRequests();
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM_TEST'], 201)]);

        $this->postJson('/api/v1/checkout/send-otp', ['phone' => '03005556677'])
            ->assertOk()->assertJsonMissingPath('debug_code');
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->data()['To'] === '+923005556677'
            && $request->data()['MessagingServiceSid'] === 'MG_TEST');
    }

    public function test_manual_mode_does_not_claim_to_send_otp(): void
    {
        config(['checkout.cod_mode' => 'manual']);
        $this->postJson('/api/v1/checkout/send-otp', ['phone' => '03005556677'])->assertServiceUnavailable();
    }

    public function test_disabled_whatsapp_never_processes_requests(): void
    {
        $this->postJson('/api/v1/webhooks/whatsapp', [])->assertNotFound();
    }

    public function test_whatsapp_verifies_handshake_and_request_origin(): void
    {
        $this->configureWhatsApp();
        $this->get('/api/v1/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=fixture-verify-token&hub.challenge=fixture-challenge')
            ->assertOk()->assertContent('fixture-challenge');
        $this->get('/api/v1/webhooks/whatsapp?hub.mode=subscribe&hub.challenge=fixture-challenge')->assertForbidden();
        $this->postJson('/api/v1/webhooks/whatsapp', [])->assertForbidden();
    }

    public function test_configured_whatsapp_sends_an_order_template_via_the_queue_handler(): void
    {
        $this->configureWhatsApp();
        Http::preventStrayRequests();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'fixture-message']]], 200)]);
        $values = $this->checkoutPayload() + ['order_number' => 'KF-2026-9001', 'subtotal' => 3000, 'total_amount' => 3000];
        unset($values['items']);
        $order = Order::create($values)->refresh();

        $confirmation = app(WhatsAppOrderConfirmation::class);
        (new SendOrderConfirmationWhatsApp($order))->handle($confirmation);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->data()['to'] === '923005556677'
            && $request->data()['template']['components'][1]['parameters'][0]['payload'] === $confirmation->button($order, 'confirm')
            && $request->data()['template']['components'][2]['parameters'][0]['payload'] === $confirmation->button($order, 'cancel'));
    }

    public function test_a_verified_order_specific_whatsapp_button_updates_only_that_order(): void
    {
        $this->configureWhatsApp();
        $payload = $this->checkoutPayload() + ['order_number' => 'KF-2026-9001'];
        unset($payload['items']);
        $values = $payload + ['subtotal' => 3000, 'total_amount' => 3000, 'payment_method' => 'cod'];
        $order = Order::create($values);
        $second = Order::create(array_replace($values, ['order_number' => 'KF-2026-9002']));
        $confirmation = app(WhatsAppOrderConfirmation::class);
        $webhook = [
            'object' => 'whatsapp_business_account',
            'entry' => [['changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => '123456'],
                    'messages' => [[
                        'type' => 'button',
                        'from' => '923005556677',
                        'button' => ['payload' => $confirmation->button($order, 'confirm')],
                    ]],
                ],
            ]]]],
        ];
        $body = json_encode($webhook, JSON_THROW_ON_ERROR);
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'fixture-app-secret'),
        ];

        $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], $headers, $body)->assertNoContent();
        $this->assertSame('confirmed', $order->fresh()->order_status);
        $this->assertSame('whatsapp_bot', $order->fresh()->confirmation_channel);
        $this->assertSame('pending', $second->fresh()->order_status);
    }

    private function checkoutPayload(): array
    {
        $variant = ProductVariant::where('is_active', true)->where('stock_quantity', '>', 2)->where('size', '!=', 'Custom')->firstOrFail();

        return [
            'customer_name' => 'Fixture Customer',
            'customer_phone' => '03005556677',
            'customer_email' => 'fixture@example.test',
            'shipping_address' => 'Fixture Street',
            'city' => 'Lahore',
            'payment_method' => 'cod',
            'items' => [['product_id' => $variant->product_id, 'variant_id' => $variant->id, 'quantity' => 1]],
        ];
    }

    private function configureWhatsApp(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.verify_token' => 'fixture-verify-token',
            'services.whatsapp.app_secret' => 'fixture-app-secret',
            'services.whatsapp.access_token' => 'fixture-access-token',
            'services.whatsapp.phone_number_id' => '123456',
            'services.whatsapp.api_version' => 'v23.0',
            'services.whatsapp.confirmation_template' => 'fixture_order_confirmation',
        ]);
    }
}
