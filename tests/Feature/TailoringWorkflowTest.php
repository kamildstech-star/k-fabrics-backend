<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\StitchingService;
use App\Models\User;
use App\Services\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TailoringWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_tailoring_rejects_incomplete_measurements_and_unavailable_handover(): void
    {
        $service = StitchingService::create(['name' => 'Tailoring package', 'base_price' => 1200]);
        $manual = ['stitching_service_id' => $service->id, 'measurement_method' => 'manual', 'measurements' => ['unit' => 'inches', 'chest' => 38]];
        $this->postJson('/api/v1/checkout/quote', ['standalone_stitchings' => [$manual]])
            ->assertUnprocessable()->assertJsonValidationErrors('standalone_stitchings.0.measurements');

        $sample = ['stitching_service_id' => $service->id, 'measurement_method' => 'sample'];
        $this->postJson('/api/v1/checkout/quote', ['standalone_stitchings' => [$sample + ['fabric_delivery_method' => 'rider_pickup']]])
            ->assertUnprocessable()->assertJsonValidationErrors('standalone_stitchings.0.fabric_delivery_method');
        $this->postJson('/api/v1/checkout/quote', ['standalone_stitchings' => [$sample + ['measurements' => $this->measurements()]]])
            ->assertUnprocessable()->assertJsonValidationErrors('standalone_stitchings.0.measurements');
        $this->postJson('/api/v1/checkout/quote', ['standalone_stitchings' => [array_replace($manual, ['measurements' => $this->measurements()])]])
            ->assertOk();
        $this->postJson('/api/v1/checkout/quote', ['standalone_stitchings' => [$sample]])
            ->assertOk();
    }

    public function test_custom_fit_requires_trouser_measurements_for_bundled_garments_but_not_single_piece(): void
    {
        $category = Category::create(['name' => 'Tailoring', 'slug' => 'tailoring']);
        foreach (['1-piece', '2-piece'] as $type) {
            $product = Product::create([
                'name' => 'Cotton '.$type, 'slug' => 'cotton-'.$type, 'category_id' => $category->id,
                'fabric_type' => 'Cotton', 'piece_type' => $type, 'base_price' => 1000, 'status' => 'active',
            ]);
            $variant = $product->variants()->create(['size' => 'Custom', 'sku' => 'custom-'.$type]);
            $cart = ['items' => [[
                'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1,
                'custom_fitting' => array_diff_key($this->measurements(), ['trouser_length' => true, 'trouser_waist' => true]),
            ]]];
            $result = $this->postJson('/api/v1/checkout/quote', $cart);
            if ($type === '1-piece') {
                $result->assertOk();
            } else {
                $result->assertUnprocessable()->assertJsonValidationErrors('items.0.custom_fitting');
            }
        }
    }

    public function test_workshop_cannot_start_sample_order_until_fabric_sample_and_deposit_are_received(): void
    {
        config(['checkout.bank_transfer_approved' => true, 'checkout.bank_transfer_instructions' => 'Test only.']);
        $service = StitchingService::create(['name' => 'Tailoring package', 'base_price' => 1200]);
        $response = $this->postJson('/api/v1/orders', [
            'customer_name' => 'Test customer', 'customer_phone' => '03000000000',
            'shipping_address' => 'Test address', 'city' => 'Lahore', 'payment_method' => 'bank_transfer',
            'standalone_stitchings' => [['stitching_service_id' => $service->id, 'measurement_method' => 'sample']],
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()
            ->assertJsonPath('data.stitching_requests.0.measurement_method', 'sample');

        $order = Order::findOrFail($response->json('data.id'));
        $request = $order->stitchingRequests()->firstOrFail();
        $this->assertNull($request->measurement_snapshot);
        $workflow = app(OrderLifecycle::class);
        $workflow->recordPayment($order, 'receipt', '725.00', 'test-tailoring-deposit', now(), User::factory()->create(['role' => 'admin']));
        $workflow->confirm($order, 'advance_paid');

        try {
            $workflow->updateStitching($request, ['status' => 'cutting', 'fabric_received_at' => now()]);
            $this->fail('Cutting must not begin before sample receipt.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('sample_received_at', $error->errors());
        }

        $started = $workflow->updateStitching($request, [
            'status' => 'cutting', 'fabric_received_at' => now(), 'sample_received_at' => now(),
        ]);
        $this->assertSame('cutting', $started->status);
        $this->assertNotNull($started->sample_received_at);
    }

    private function measurements(): array
    {
        return [
            'unit' => 'inches', 'shirt_length' => 40, 'shoulder' => 14.5, 'chest' => 38, 'waist' => 33,
            'hips' => 40, 'sleeve_length' => 21, 'trouser_length' => 38, 'trouser_waist' => 32,
        ];
    }
}
