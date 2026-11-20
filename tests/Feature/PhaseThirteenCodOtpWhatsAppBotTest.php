<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmationWhatsApp;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OtpVerification;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PhaseThirteenCodOtpWhatsAppBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_send_otp_success_with_valid_phone(): void
    {
        $response = $this->postJson('/api/v1/checkout/send-otp', [
            'phone' => '+923001234567',
            'channel' => 'whatsapp',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('phone', '03001234567')
            ->assertJsonPath('channel', 'whatsapp')
            ->assertJsonStructure([
                'success',
                'message',
                'phone',
                'channel',
                'expires_in_seconds',
                'cooldown_seconds',
                'debug_code',
            ]);

        $this->assertDatabaseHas('otp_verifications', [
            'phone' => '03001234567',
            'purpose' => 'checkout_cod',
            'attempts' => 0,
        ]);
    }

    public function test_send_otp_validation_failure_for_invalid_phone(): void
    {
        $response = $this->postJson('/api/v1/checkout/send-otp', [
            'phone' => '12345',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_send_otp_rate_limiting_after_three_requests(): void
    {
        $phone = '03009998877';

        // 3 successful requests
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/checkout/send-otp', ['phone' => $phone])
                ->assertStatus(200);
        }

        // 4th request must be rate limited
        $response = $this->postJson('/api/v1/checkout/send-otp', ['phone' => $phone]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_verify_otp_success(): void
    {
        $phone = '03123456789';
        $code = '7890';

        OtpVerification::create([
            'phone' => $phone,
            'purpose' => 'checkout_cod',
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/checkout/verify-otp', [
            'phone' => $phone,
            'code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('phone', $phone)
            ->assertJsonStructure(['success', 'message', 'phone', 'verification_token', 'verified_at']);

        $this->assertNotNull(OtpVerification::where('phone', $phone)->first()->verified_at);
    }

    public function test_verify_otp_wrong_code_increments_attempts(): void
    {
        $phone = '03123456789';
        $code = '7890';

        $otp = OtpVerification::create([
            'phone' => $phone,
            'purpose' => 'checkout_cod',
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/checkout/verify-otp', [
            'phone' => $phone,
            'code' => '0000',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertEquals(1, $otp->fresh()->attempts);
    }

    public function test_cod_order_requires_phone_otp_verification(): void
    {
        Queue::fake();

        $product = Product::where('status', 'active')->first();
        $variant = $product->variants()->where('size', '!=', 'Custom')->first();

        $payload = [
            'customer_name' => 'Unverified Customer',
            'customer_phone' => '03005556677',
            'customer_email' => 'unverified@example.com',
            'shipping_address' => 'House 1, Street 2',
            'city' => 'Faisalabad',
            'payment_method' => 'cod',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                ],
            ],
        ];

        // 1. Without OTP verification -> 422
        $response = $this->postJson('/api/v1/orders', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_phone']);

        // 2. Request OTP and verify
        $sendRes = $this->postJson('/api/v1/checkout/send-otp', ['phone' => '03005556677']);
        $debugCode = $sendRes->json('debug_code');

        $verifyRes = $this->postJson('/api/v1/checkout/verify-otp', [
            'phone' => '03005556677',
            'code' => $debugCode,
        ]);
        $token = $verifyRes->json('verification_token');

        // 3. Place order with verification token -> 201 Created
        $payload['verification_token'] = $token;
        $orderResponse = $this->postJson('/api/v1/orders', $payload);

        $orderResponse->assertStatus(201)
            ->assertJsonPath('data.payment_method', 'cod')
            ->assertJsonPath('data.order_status', 'pending');

        $this->assertNotNull($orderResponse->json('data.phone_verified_at'));

        Queue::assertPushed(SendOrderConfirmationWhatsApp::class);
    }

    public function test_bank_transfer_order_does_not_require_otp(): void
    {
        Queue::fake();

        $product = Product::where('status', 'active')->first();
        $variant = $product->variants()->where('size', '!=', 'Custom')->first();

        $payload = [
            'customer_name' => 'Advance Customer',
            'customer_phone' => '03001112233',
            'customer_email' => 'advance@example.com',
            'shipping_address' => 'House 10, Model Town',
            'city' => 'Lahore',
            'payment_method' => 'bank_transfer',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $orderResponse = $this->postJson('/api/v1/orders', $payload);
        $orderResponse->assertStatus(201)
            ->assertJsonPath('data.payment_method', 'bank_transfer');
    }

    public function test_whatsapp_webhook_handshake_hub_challenge(): void
    {
        $response = $this->get('/api/v1/webhooks/whatsapp?hub_challenge=challenge_12345');
        $response->assertStatus(200);
        $this->assertEquals('"challenge_12345"', $response->getContent());
    }

    public function test_whatsapp_webhook_reply_1_confirms_pending_order(): void
    {
        $order = Order::create([
            'order_number' => 'KF-2026-9001',
            'customer_name' => 'Pending Customer',
            'customer_phone' => '03001239999',
            'customer_email' => 'pending@example.com',
            'shipping_address' => 'House 1, DHA',
            'city' => 'Lahore',
            'subtotal' => 10000,
            'shipping_fee' => 0,
            'discount_amount' => 0,
            'total_amount' => 10000,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'confirmation_channel' => 'none',
            'confirmed_at' => null,
        ]);

        $response = $this->postJson('/api/v1/webhooks/whatsapp', [
            'phone' => '+923001239999',
            'message' => '1',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('order_status', 'confirmed')
            ->assertJsonPath('order_number', $order->order_number);

        $order->refresh();
        $this->assertEquals('confirmed', $order->order_status);
        $this->assertEquals('whatsapp_bot', $order->confirmation_channel);
        $this->assertNotNull($order->confirmed_at);
    }

    public function test_whatsapp_webhook_reply_2_cancels_order_and_restores_variant_stock(): void
    {
        $variant = ProductVariant::first();
        $initialStock = $variant->stock_quantity;

        $order = Order::create([
            'order_number' => 'KF-2026-9002',
            'customer_name' => 'Cancel Customer',
            'customer_phone' => '03007778899',
            'customer_email' => 'cancel@example.com',
            'shipping_address' => 'House 2, DHA',
            'city' => 'Lahore',
            'subtotal' => 10000,
            'shipping_fee' => 0,
            'discount_amount' => 0,
            'total_amount' => 10000,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'confirmation_channel' => 'none',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'product_name' => 'Test Product',
            'size' => $variant->size,
            'color' => $variant->color,
            'quantity' => 2,
            'unit_price' => 5000,
            'total_price' => 10000,
        ]);

        // Variant stock before cancel
        $response = $this->postJson('/api/v1/webhooks/whatsapp', [
            'phone' => '03007778899',
            'message' => '2',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('order_status', 'cancelled');

        $order->refresh();
        $this->assertEquals('cancelled', $order->order_status);
        $this->assertEquals('Cancelled via WhatsApp reply 2', $order->cancelled_reason);

        // Inventory must be restored (+2)
        $this->assertEquals($initialStock + 2, $variant->fresh()->stock_quantity);
    }
}
