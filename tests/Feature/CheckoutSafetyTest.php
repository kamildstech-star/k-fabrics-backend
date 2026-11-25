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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckoutSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['checkout.cod_mode' => 'manual', 'checkout.bank_transfer_instructions' => null]);
    }

    public function test_quote_and_order_use_same_totals_for_custom_fitting_and_multiple_bookings(): void
    {
        $custom = $this->variant('Custom');
        $bookingA = $this->booking('1200.11');
        $bookingB = $this->booking('100.01');
        $cart = [
            'items' => [$this->line($custom, 2) + ['custom_fitting' => ['unit' => 'inches', 'chest' => 38.25]]],
            'standalone_stitchings' => [['stitching_service_id' => $bookingA->id], ['stitching_service_id' => $bookingB->id]],
        ];

        $quote = $this->postJson('/api/v1/checkout/quote', $cart)
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price', 1320.25)
            ->assertJsonPath('data.total_amount', 4190.62)
            ->assertJsonPath('data.advance_due', 2095.31)
            ->assertJsonCount(2, 'data.standalone_stitchings');

        $response = $this->place($cart + ['expected_total_amount' => $quote->json('data.total_amount')])
            ->assertCreated()
            ->assertJsonPath('data.total_amount', 4190.62)
            ->assertJsonCount(3, 'data.stitching_requests')
            ->assertJsonPath('data.stitching_requests.0.measurement.chest', 38.25);
        $this->assertNotEmpty($response->json('data.tracking_token'));
        $this->assertSame(3, $custom->fresh()->stock_quantity);
        $order = Order::findOrFail($response->json('data.id'));
        $this->assertSame('600.00', $order->stitchingRequests()->first()->stitching_fee);
        $this->assertNull($order->stitchingRequests()->first()->measurement_id);
        $this->assertDatabaseCount('customer_measurements', 0);
    }

    public function test_stock_coupon_and_checkout_key_are_consistent_across_retries_and_cancellation(): void
    {
        $variant = $this->variant();
        $variant->update(['stock_quantity' => 1]);
        $coupon = Coupon::create(['code' => 'ONE', 'discount_type' => 'fixed', 'discount_value' => 100, 'max_uses' => 1, 'is_active' => true]);
        $cart = ['items' => [$this->line($variant)], 'coupon_code' => 'ONE'];
        $key = (string) Str::uuid();

        $first = $this->place($cart, $key)->assertCreated();
        $this->place($cart, $key)->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.tracking_token', $first->json('data.tracking_token'));
        $this->place($cart + ['notes' => 'Different cart'], $key)->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->place($cart)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(0, $variant->fresh()->stock_quantity);
        $this->assertEquals(1, $coupon->fresh()->used_count);

        $order = Order::findOrFail($first->json('data.id'));
        $workflow = app(OrderLifecycle::class);
        $workflow->cancel($order, 'Customer requested', 'staff');
        $workflow->cancel($order, 'Duplicate request', 'staff');
        $this->assertSame('cancelled', $order->fresh()->order_status);
        $this->assertSame(1, $variant->fresh()->stock_quantity);
        $this->assertEquals(0, $coupon->fresh()->used_count);
    }

    public function test_quote_and_placement_reject_unavailable_variants_duplicates_and_stale_totals(): void
    {
        $variant = $this->variant();
        $line = $this->line($variant, 2);
        $this->postJson('/api/v1/checkout/quote', ['items' => [$line, $line]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.1.quantity');
        $this->place(['items' => [$line], 'expected_total_amount' => '240.00'])
            ->assertUnprocessable()->assertJsonValidationErrors('expected_total_amount');
        $this->postJson('/api/v1/checkout/quote', ['items' => [$line], 'coupon_code' => 'UNAVAILABLE'])
            ->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
        $variant->update(['is_active' => false]);
        $this->place(['items' => [$line]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_reference_images_are_validated_private_and_linked_to_the_correct_booking(): void
    {
        Storage::fake('local');
        $service = $this->booking(1000);
        $input = $this->customer() + [
            'standalone_stitchings' => json_encode([
                ['stitching_service_id' => $service->id],
                ['stitching_service_id' => $service->id],
            ]),
            'design_reference_images' => [1 => UploadedFile::fake()->image('second.png')],
        ];

        $result = $this->post('/api/v1/orders', $input, ['Idempotency-Key' => (string) Str::uuid(), 'Accept' => 'application/json'])
            ->assertCreated()->assertJsonMissingPath('data.stitching_requests.1.design_reference_image');
        $requests = Order::findOrFail($result->json('data.id'))->stitchingRequests()->orderBy('id')->get();
        $this->assertNull($requests[0]->design_reference_image);
        Storage::disk('local')->assertExists($requests[1]->design_reference_image);

        $input['design_reference_images'] = [0 => UploadedFile::fake()->create('invalid.txt', 2, 'text/plain')];
        $this->post('/api/v1/orders', $input, ['Idempotency-Key' => (string) Str::uuid(), 'Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('design_reference_images.0');
    }

    public function test_verified_payment_and_fabric_receipt_gate_tailoring_and_fulfillment(): void
    {
        $service = $this->booking(1250);
        $result = $this->place(['standalone_stitchings' => [['stitching_service_id' => $service->id]]])->assertCreated();
        $order = Order::findOrFail($result->json('data.id'));
        $request = $order->stitchingRequests()->firstOrFail();
        $workflow = app(OrderLifecycle::class);
        $operator = User::factory()->create(['role' => 'admin']);

        $workflow->confirm($order, 'manual_call');
        try {
            $workflow->updateStitching($request, ['status' => 'cutting', 'fabric_received_at' => now()]);
            $this->fail('Cutting must be held until advance is verified.');
        } catch (ValidationException) {
            $this->assertSame('awaiting_fabric', $request->fresh()->status);
        }
        $payment = $workflow->recordPayment($order, 'receipt', '750.00', 'test-receipt-1', now(), $operator);
        $replay = $workflow->recordPayment($order, 'receipt', '750.00', 'test-receipt-1', now(), $operator);
        $this->assertSame($payment->id, $replay->id);
        $this->assertSame('pending', $order->fresh()->payment_status);

        foreach (['cutting', 'stitching', 'finishing', 'ready'] as $status) {
            $workflow->updateStitching($request, ['status' => $status, 'fabric_received_at' => now()]);
        }
        $this->assertSame('stitching', $order->fresh()->order_status);
        $workflow->advanceOrder($order, 'packed');
        $workflow->advanceOrder($order, 'shipped');
        $workflow->advanceOrder($order, 'returned');
        $workflow->recordPayment($order, 'refund', '750.00', 'test-refund-1', now(), $operator);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(0, $workflow->received($order));
    }

    public function test_bank_transfer_is_not_available_until_verified_instructions_are_configured(): void
    {
        $line = $this->line($this->variant());
        $this->place(['items' => [$line], 'payment_method' => 'bank_transfer'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        config(['checkout.bank_transfer_instructions' => 'Test-only verified shop details.']);
        $this->place(['items' => [$line], 'payment_method' => 'bank_transfer'])
            ->assertCreated()->assertJsonPath('data.payment_instructions', 'Test-only verified shop details.');
    }

    public function test_admin_can_open_controlled_order_and_stitching_edit_pages(): void
    {
        $booking = $this->booking(1000);
        $response = $this->place(['standalone_stitchings' => [['stitching_service_id' => $booking->id]]])->assertCreated();
        $order = Order::findOrFail($response->json('data.id'));
        $request = $order->stitchingRequests()->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get("/admin/orders/{$order->id}/edit")
            ->assertOk()->assertSee('Record verified payment / refund')->assertSee('Required tailoring deposit');
        $this->actingAs($admin)->get("/admin/stitching-requests/{$request->id}/edit")
            ->assertOk()->assertSee('Fabric Received Date');
    }

    private function place(array $data, ?string $key = null)
    {
        return $this->postJson('/api/v1/orders', $data + $this->customer(), ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    private function customer(): array
    {
        return [
            'customer_name' => 'Sample customer', 'customer_phone' => '03000000000',
            'shipping_address' => 'Sample address', 'city' => 'Lahore', 'payment_method' => 'cod',
        ];
    }

    private function variant(string $size = 'M'): ProductVariant
    {
        $category = Category::create(['name' => 'Test', 'slug' => 'cat-'.Str::random(6)]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Test suit', 'slug' => 'suit-'.Str::random(6),
            'fabric_type' => 'cotton', 'piece_type' => '1-piece', 'base_price' => '1000.11', 'status' => 'active',
        ]);

        return $product->variants()->create([
            'size' => $size, 'sku' => 'TEST-'.Str::random(6),
            'price_override' => '1020.25', 'stock_quantity' => 3, 'is_active' => true,
        ]);
    }

    private function line(ProductVariant $variant, int $quantity = 1): array
    {
        return ['product_id' => $variant->product_id, 'variant_id' => $variant->id, 'quantity' => $quantity];
    }

    private function booking(string|int $price): StitchingService
    {
        return StitchingService::create(['name' => 'Test stitching', 'base_price' => $price, 'is_active' => true]);
    }
}
