<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CustomerMeasurement;
use App\Models\Order;
use App\Models\Product;
use App\Models\StitchingRequest;
use App\Models\StitchingService;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseOneBackendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_is_redirected_from_admin_dashboard_to_login(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/admin/login');
    }

    public function test_admin_user_can_access_admin_dashboard(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get('/admin');
        $response->assertStatus(200);
    }

    public function test_customer_user_is_forbidden_from_admin_panel(): void
    {
        $customer = User::where('role', 'customer')->first();
        $this->assertNotNull($customer);

        $response = $this->actingAs($customer)->get('/admin');
        $response->assertStatus(403);
    }

    public function test_seeded_categories_and_hierarchies(): void
    {
        $dailyPret = Category::where('slug', 'daily-pret')->first();
        $this->assertNotNull($dailyPret);
        $this->assertNull($dailyPret->parent_id);

        $childCategory = Category::where('slug', 'printed-kurtis')->first();
        $this->assertNotNull($childCategory);
        $this->assertEquals($dailyPret->id, $childCategory->parent_id);
        $this->assertTrue($dailyPret->children->contains($childCategory));
    }

    public function test_seeded_products_have_variants_and_images(): void
    {
        $products = Product::with(['variants', 'images', 'category'])->get();
        $this->assertCount(6, $products);

        foreach ($products as $product) {
            $this->assertNotEmpty($product->fabric_type);
            $this->assertContains($product->piece_type, ['1-piece', '2-piece', '3-piece']);
            $this->assertGreaterThan(0, $product->base_price);
            $this->assertGreaterThanOrEqual(1, $product->variants->count());
            $this->assertGreaterThanOrEqual(1, $product->images->count());
        }
    }

    public function test_seeded_stitching_services(): void
    {
        $services = StitchingService::all();
        $this->assertCount(3, $services);

        $simple = $services->firstWhere('name', 'Simple 2-Piece');
        $this->assertNotNull($simple);
        $this->assertEquals(1500.00, $simple->base_price);

        $threePiece = $services->firstWhere('name', '3-Piece with Dupatta Finishing');
        $this->assertNotNull($threePiece);
        $this->assertEquals(2200.00, $threePiece->base_price);

        $fancy = $services->firstWhere('name', 'Fancy / Lining Suit');
        $this->assertNotNull($fancy);
        $this->assertEquals(3500.00, $fancy->base_price);
    }

    public function test_order_and_stitching_request_relationships(): void
    {
        $order = Order::where('order_number', 'KF-2026-0001')->with(['items', 'stitchingRequests', 'user'])->first();
        $this->assertNotNull($order);
        $this->assertCount(1, $order->items);
        $this->assertCount(1, $order->stitchingRequests);

        $stitchingReq = $order->stitchingRequests->first();
        $this->assertEquals('custom_fitting', $stitchingReq->request_type);
        $this->assertNotNull($stitchingReq->measurement);

        // Standalone stitching order
        $order2 = Order::where('order_number', 'KF-2026-0002')->with('stitchingRequests')->first();
        $this->assertNotNull($order2);
        $standaloneReq = $order2->stitchingRequests->first();
        $this->assertEquals('standalone_stitching', $standaloneReq->request_type);
        $this->assertEquals('rider_pickup', $standaloneReq->fabric_delivery_method);
        $this->assertNotNull($standaloneReq->fabric_received_at);
    }

    public function test_coupon_calculations(): void
    {
        $percentCoupon = Coupon::where('code', 'WELCOME10')->first();
        $this->assertNotNull($percentCoupon);
        $this->assertTrue($percentCoupon->isValidForAmount(5000));
        $this->assertFalse($percentCoupon->isValidForAmount(1000)); // under min_order_amount 3000
        $this->assertEquals(500.00, $percentCoupon->calculateDiscount(5000));

        $fixedCoupon = Coupon::where('code', 'LUXURY500')->first();
        $this->assertNotNull($fixedCoupon);
        $this->assertTrue($fixedCoupon->isValidForAmount(5000));
        $this->assertFalse($fixedCoupon->isValidForAmount(4999)); // under min 5000
        $this->assertEquals(500.00, $fixedCoupon->calculateDiscount(6000));
    }

    public function test_filament_resource_pages_render_for_admin(): void
    {
        $admin = User::where('role', 'admin')->first();

        $routes = [
            '/admin/categories',
            '/admin/products',
            '/admin/orders',
            '/admin/coupons',
            '/admin/stitching-services',
            '/admin/stitching-requests',
            '/admin/customer-measurements',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($admin)->get($route);
            $response->assertStatus(200);
        }
    }
}
