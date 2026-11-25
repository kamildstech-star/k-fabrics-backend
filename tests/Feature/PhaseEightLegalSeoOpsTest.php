<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StitchingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $response = $this->actingAs(User::where('role', 'admin')->firstOrFail())->get("/admin/orders/{$order->id}/tailor-sheet");

        $response->assertStatus(200)
            ->assertSee($order->order_number)
            ->assertDontSee($order->customer_phone)
            ->assertDontSee($order->shipping_address)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Atelier Master Tailor Sheet')
            ->assertSee('Shirt Measurements')
            ->assertSee('Trouser / Shalwar Measurements')
            ->assertSee('Workshop Verification');
    }

    public function test_can_view_printable_tailor_sheet_for_stitching_request(): void
    {
        $stitching = StitchingRequest::firstOrFail();

        $response = $this->actingAs(User::where('role', 'admin')->firstOrFail())->get("/admin/stitching-requests/{$stitching->id}/tailor-sheet");

        $response->assertStatus(200)
            ->assertSee($stitching->order->order_number)
            ->assertSee('Workshop Priority')
            ->assertSee('Cutting Master (Ustaad)');
    }

    public function test_tailor_sheets_are_never_public_or_accessible_to_customers(): void
    {
        $order = Order::firstOrFail();
        $stitching = StitchingRequest::firstOrFail();
        $this->get(route('admin.orders.tailor-sheet', $order))->assertRedirect('/admin/login');
        $this->getJson(route('admin.stitching-requests.reference', $stitching))->assertUnauthorized();

        $this->actingAs(User::where('role', 'customer')->firstOrFail());
        $this->get(route('admin.orders.tailor-sheet', $order))->assertForbidden();
        $this->get(route('admin.stitching-requests.tailor-sheet', $stitching))->assertForbidden();
        $this->get(route('admin.stitching-requests.reference', $stitching))->assertForbidden();
    }

    public function test_staff_can_open_a_private_reference_without_a_public_url(): void
    {
        Storage::fake('local');
        $request = StitchingRequest::firstOrFail();
        $path = UploadedFile::fake()->image('fixture.png')->store('stitching_references', 'local');
        $request->update(['design_reference_image' => $path]);
        $route = route('admin.stitching-requests.reference', $request);

        $this->actingAs(User::where('role', 'admin')->firstOrFail());
        $this->get(route('admin.stitching-requests.tailor-sheet', $request))
            ->assertOk()->assertSee($route)->assertDontSee($path);
        $this->get($route)->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'no-store, private');
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
