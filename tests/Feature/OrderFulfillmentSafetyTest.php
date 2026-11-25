<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StitchingService;
use App\Models\User;
use App\Services\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderFulfillmentSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        config([
            'checkout.cod_mode' => 'manual',
            'checkout.cod_confirmation_hold_hours' => 72,
            'checkout.bank_transfer_approved' => true,
            'checkout.bank_transfer_instructions' => 'Test-only fixture instructions.',
        ]);
    }

    public function test_expiry_releases_stock_and_coupon_once_and_protects_all_ineligible_orders(): void
    {
        $variant = $this->variant();
        $coupon = Coupon::create([
            'code' => 'HOLDFIXTURE', 'discount_type' => 'fixed', 'discount_value' => 100,
            'max_uses' => 5, 'is_active' => true,
        ]);
        $old = $this->historicalOrder();
        $eligible = $this->place($variant, ['coupon_code' => $coupon->code]);
        $confirmed = $this->place($variant);
        $partlyPaid = $this->place($variant);
        $bank = $this->place($variant, ['payment_method' => 'bank_transfer']);
        $service = StitchingService::create(['name' => 'Test tailoring', 'base_price' => 1200, 'is_active' => true]);
        $tailored = $this->place(null, [
            'payment_method' => 'bank_transfer',
            'standalone_stitchings' => [['stitching_service_id' => $service->id, 'measurement_method' => 'sample']],
        ]);

        $this->assertTrue($eligible->cod_confirmation_expires_at->equalTo(now()->addHours(72)->startOfSecond()));
        $this->assertNull($old->cod_confirmation_expires_at);
        $this->assertNull($bank->cod_confirmation_expires_at);
        $this->assertNull($tailored->cod_confirmation_expires_at);
        $this->assertSame(16, $variant->fresh()->stock_quantity);
        $this->assertEquals(1, $coupon->fresh()->used_count);

        $lifecycle = app(OrderLifecycle::class);
        $lifecycle->confirm($confirmed, 'manual_call');
        $lifecycle->recordPayment($partlyPaid, 'receipt', '50.00', 'test-partial', now(), User::factory()->create(['role' => 'admin']));
        $this->assertNull($confirmed->fresh()->cod_confirmation_expires_at);
        $this->assertNull($partlyPaid->fresh()->cod_confirmation_expires_at);

        // Also protect a paid order if a deadline somehow remains set.
        $partlyPaid->update(['cod_confirmation_expires_at' => $eligible->cod_confirmation_expires_at]);
        $this->travel(71)->hours();
        $this->artisan('orders:expire-unconfirmed-cod')
            ->expectsOutput('Released stock for 0 unconfirmed COD orders.')->assertSuccessful();

        $this->travel(2)->hours();
        $this->artisan('orders:expire-unconfirmed-cod')
            ->expectsOutput('Released stock for 1 unconfirmed COD orders.')->assertSuccessful();
        $this->artisan('orders:expire-unconfirmed-cod')
            ->expectsOutput('Released stock for 0 unconfirmed COD orders.')->assertSuccessful();

        $this->assertSame('cancelled', $eligible->fresh()->order_status);
        $this->assertSame(OrderLifecycle::COD_EXPIRY_REASON, $eligible->fresh()->cancelled_reason);
        $this->assertSame('confirmed', $confirmed->fresh()->order_status);
        foreach ([$old, $partlyPaid, $bank, $tailored] as $untouched) {
            $this->assertSame('pending', $untouched->fresh()->order_status);
        }
        $this->assertSame(17, $variant->fresh()->stock_quantity);
        $this->assertEquals(0, $coupon->fresh()->used_count);
    }

    public function test_expiry_can_be_paused_and_no_legacy_deadline_is_assigned(): void
    {
        $variant = $this->variant();
        $first = $this->place($variant);
        config(['checkout.cod_confirmation_hold_hours' => 0]);
        $second = $this->place($variant);
        $this->assertNull($second->cod_confirmation_expires_at);
        $this->travel(4)->days();
        $this->artisan('orders:expire-unconfirmed-cod')
            ->expectsOutput('Released stock for 0 unconfirmed COD orders.')->assertSuccessful();
        $this->assertSame('pending', $first->fresh()->order_status);

        config(['checkout.cod_confirmation_hold_hours' => 72]);
        $this->artisan('orders:expire-unconfirmed-cod')
            ->expectsOutput('Released stock for 1 unconfirmed COD orders.')->assertSuccessful();
        $this->assertSame('cancelled', $first->fresh()->order_status);
        $this->assertSame('pending', $second->fresh()->order_status);
    }

    public function test_dispatch_requires_saved_courier_and_consignment_id(): void
    {
        $order = $this->place($this->variant());
        $flow = app(OrderLifecycle::class);
        $flow->confirm($order, 'manual_call');
        $flow->advanceOrder($order, 'packed');

        $this->assertValidationField('courier_name', fn () => $flow->advanceOrder($order, 'shipped'));
        $this->assertValidationField('courier_name', fn () => $flow->updateDetails($order, ['courier_name' => '12345']));
        $flow->updateDetails($order, ['courier_name' => ' TCS Pakistan (Pvt.) Ltd. ']);
        $this->assertValidationField('tracking_number', fn () => $flow->advanceOrder($order, 'shipped'));
        $this->assertValidationField('tracking_number', fn () => $flow->updateDetails($order, ['tracking_number' => 'https://example.test/tracking']));
        $this->assertSame('packed', $order->fresh()->order_status);

        $flow->updateDetails($order, ['tracking_number' => ' PK-12345/7 ', 'order_status' => 'delivered']);
        $saved = $flow->advanceOrder($order, 'shipped');
        $this->assertSame('shipped', $saved->order_status);
        $this->assertSame('TCS Pakistan (Pvt.) Ltd.', $saved->courier_name);
        $this->assertSame('PK-12345/7', $saved->tracking_number);

        $this->assertValidationField('tracking_number', fn () => $flow->updateDetails($saved, ['tracking_number' => null]));
        $this->assertSame('PK-12345/7', $saved->fresh()->tracking_number);
    }

    public function test_staff_can_open_pending_cod_work_tab_and_see_a_deadline(): void
    {
        $this->place($this->variant());
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/orders?activeTab=cod_confirmation')
            ->assertOk()->assertSee('COD Needs Confirmation')->assertSee('COD Hold Deadline (PKT)');
    }

    private function assertValidationField(string $field, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected validation error for $field.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    private function variant(): ProductVariant
    {
        $category = Category::create(['name' => 'Ready to wear', 'slug' => 'ready-'.Str::random(8)]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Test kurta', 'slug' => 'test-kurta-'.Str::random(8),
            'fabric_type' => 'Cotton', 'piece_type' => '1-piece', 'base_price' => 1000, 'status' => 'active',
        ]);

        return $product->variants()->create([
            'size' => 'M', 'sku' => 'TEST-'.Str::random(8), 'stock_quantity' => 20, 'is_active' => true,
        ]);
    }

    private function place(?ProductVariant $variant, array $overrides = []): Order
    {
        $payload = $overrides + [
            'customer_name' => 'Test customer', 'customer_phone' => '03000000000',
            'shipping_address' => 'Test address', 'city' => 'Lahore', 'payment_method' => 'cod',
        ];
        if ($variant) {
            $payload['items'] = [['product_id' => $variant->product_id, 'variant_id' => $variant->id, 'quantity' => 1]];
        }
        $response = $this->postJson('/api/v1/orders', $payload, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

        $order = Order::findOrFail($response->json('data.id'));
        $response->assertJsonPath('data.cod_confirmation_expires_at', $order->cod_confirmation_expires_at?->toISOString());

        return $order;
    }

    private function historicalOrder(): Order
    {
        return Order::forceCreate([
            'order_number' => 'KF-HISTORICAL-0001', 'customer_name' => 'Historical customer', 'customer_phone' => '03000000000',
            'shipping_address' => 'Historical address', 'city' => 'Lahore', 'subtotal' => 1000, 'total_amount' => 1250,
            'payment_method' => 'cod', 'payment_status' => 'pending', 'order_status' => 'pending',
            'created_at' => now()->subYear(),
        ]);
    }
}
