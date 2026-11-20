<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StitchingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseEightLegalSeoOpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_can_view_printable_tailor_sheet_for_order(): void
    {
        $order = Order::where('order_number', 'KF-2026-0001')->firstOrFail();

        $response = $this->get("/admin/orders/{$order->id}/tailor-sheet");

        $response->assertStatus(200)
            ->assertSee($order->order_number)
            ->assertSee($order->customer_name)
            ->assertSee('Atelier Master Tailor Sheet')
            ->assertSee('Shirt Measurements')
            ->assertSee('Trouser / Shalwar Measurements')
            ->assertSee('Workshop Verification');
    }

    public function test_can_view_printable_tailor_sheet_for_stitching_request(): void
    {
        $stitching = StitchingRequest::firstOrFail();

        $response = $this->get("/admin/stitching-requests/{$stitching->id}/tailor-sheet");

        $response->assertStatus(200)
            ->assertSee($stitching->order->order_number)
            ->assertSee('Workshop Priority')
            ->assertSee('Cutting Master (Ustaad)');
    }

    public function test_low_stock_variants_query(): void
    {
        $variant = ProductVariant::firstOrFail();
        $variant->update(['stock_quantity' => 2, 'is_active' => true]);

        $lowStockVariants = ProductVariant::where('is_active', true)
            ->where('stock_quantity', '<=', 3)
            ->get();

        $this->assertTrue($lowStockVariants->contains('id', $variant->id));
    }
}
