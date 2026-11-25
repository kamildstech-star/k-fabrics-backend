<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StitchingService;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseTwoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_categories_endpoint_returns_nested_hierarchy(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'image_url',
                        'children' => [
                            '*' => [
                                'id',
                                'name',
                                'slug',
                                'image_url',
                            ],
                        ],
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        // Verify root category has children
        $dailyPret = collect($data)->firstWhere('slug', 'daily-pret');
        $this->assertNotNull($dailyPret);
        $this->assertNotEmpty($dailyPret['children']);
        $this->assertEquals('Printed Kurtis', $dailyPret['children'][0]['name']);
    }

    public function test_products_list_endpoint_and_filtering(): void
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'fabric_type',
                        'piece_type',
                        'base_price',
                        'sale_price',
                        'is_featured',
                        'status',
                        'category',
                        'primary_image',
                        'hover_image',
                        'images',
                        'variants',
                    ],
                ],
                'links',
                'meta',
            ]);

        // Filter by category slug
        $catResponse = $this->getJson('/api/v1/products?category=daily-pret');
        $catResponse->assertStatus(200);
        foreach ($catResponse->json('data') as $item) {
            $this->assertContains($item['category']['slug'], ['daily-pret', 'printed-kurtis']);
        }

        // Filter by size
        $sizeResponse = $this->getJson('/api/v1/products?size=M');
        $sizeResponse->assertStatus(200);
        $this->assertNotEmpty($sizeResponse->json('data'));

        // Filter by fabric type
        $fabricResponse = $this->getJson('/api/v1/products?fabric_type=Lawn');
        $fabricResponse->assertStatus(200);
        foreach ($fabricResponse->json('data') as $item) {
            $this->assertEquals('Lawn', $item['fabric_type']);
        }

        // Filter by featured
        $featuredResponse = $this->getJson('/api/v1/products?is_featured=true');
        $featuredResponse->assertStatus(200);
        foreach ($featuredResponse->json('data') as $item) {
            $this->assertTrue($item['is_featured']);
        }

        // Filter by price range
        $priceResponse = $this->getJson('/api/v1/products?min_price=10000');
        $priceResponse->assertStatus(200);
        foreach ($priceResponse->json('data') as $item) {
            $effectivePrice = $item['sale_price'] ?? $item['base_price'];
            $this->assertGreaterThanOrEqual(10000, $effectivePrice);
        }

        // Search
        $searchResponse = $this->getJson('/api/v1/products?search=Ivory');
        $searchResponse->assertStatus(200);
        $this->assertNotEmpty($searchResponse->json('data'));
        $this->assertStringContainsStringIgnoringCase('Ivory', $searchResponse->json('data.0.name'));

        // Sorting price_asc
        $sortAscResponse = $this->getJson('/api/v1/products?sort=price_asc');
        $sortAscResponse->assertStatus(200);
        $prices = collect($sortAscResponse->json('data'))->map(fn ($p) => $p['sale_price'] ?? $p['base_price'])->values();
        $sorted = $prices->sort()->values();
        $this->assertEquals($sorted->toArray(), $prices->toArray());
    }

    public function test_single_product_detail_endpoint(): void
    {
        $product = Product::where('status', 'active')->first();
        $this->assertNotNull($product);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', $product->name)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'fabric_type',
                    'piece_type',
                    'base_price',
                    'sale_price',
                    'is_featured',
                    'status',
                    'category' => ['id', 'name', 'slug'],
                    'primary_image',
                    'images',
                    'variants',
                ],
            ]);

        // Non-existent product returns 404
        $notFoundResponse = $this->getJson('/api/v1/products/non-existent-product-slug');
        $notFoundResponse->assertStatus(404);
    }

    public function test_stitching_services_endpoint(): void
    {
        $response = $this->getJson('/api/v1/stitching-services');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'base_price',
                        'estimated_days',
                        'is_active',
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_coupon_validation_endpoint(): void
    {
        // Valid percentage coupon
        $response = $this->postJson('/api/v1/coupons/validate', [
            'code' => 'WELCOME10',
            'subtotal' => 5000.00,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'valid' => true,
                'discount_amount' => 500.00,
                'coupon' => [
                    'code' => 'WELCOME10',
                    'discount_type' => 'percentage',
                    'discount_value' => 10.00,
                ],
            ]);

        // Under min_order_amount (min 3000)
        $underMinResponse = $this->postJson('/api/v1/coupons/validate', [
            'code' => 'WELCOME10',
            'subtotal' => 2000.00,
        ]);
        $underMinResponse->assertStatus(422)
            ->assertJson(['valid' => false]);

        // Non-existent coupon
        $invalidResponse = $this->postJson('/api/v1/coupons/validate', [
            'code' => 'INVALID_CODE',
            'subtotal' => 5000.00,
        ]);
        $invalidResponse->assertStatus(422)
            ->assertJson(['valid' => false]);
    }

    public function test_order_creation_standard_ready_to_wear(): void
    {
        $product = Product::where('status', 'active')->first();
        $variant = $product->variants()->where('size', 'M')->first();
        $initialStock = $variant->stock_quantity;

        $payload = [
            'customer_name' => 'Catalog test customer',
            'customer_phone' => '030'.str_repeat('0', 8),
            'customer_email' => 'catalog@example.test',
            'shipping_address' => 'Synthetic test delivery address A',
            'city' => 'Lahore',
            'payment_method' => 'cod',
            'notes' => 'Ring doorbell twice please',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/orders', $payload, ['Idempotency-Key' => (string) \Illuminate\Support\Str::uuid()]);

        $response->assertStatus(201)
            ->assertJsonPath('data.customer_name', 'Catalog test customer')
            ->assertJsonPath('data.city', 'Lahore')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'order_number',
                    'customer_name',
                    'customer_phone',
                    'subtotal',
                    'shipping_fee',
                    'total_amount',
                    'payment_method',
                    'payment_status',
                    'order_status',
                    'items',
                ],
            ]);

        // Stock must be decremented
        $this->assertEquals($initialStock - 2, $variant->fresh()->stock_quantity);

        // A collision-resistant, opaque, human-referenceable order number.
        $orderNumber = $response->json('data.order_number');
        $this->assertMatchesRegularExpression('/^KF-\d{4}-[0-9A-HJKMNP-TV-Z]{26}$/', $orderNumber);
    }

    public function test_order_creation_with_custom_fitting(): void
    {
        $product = Product::where('status', 'active')->first();
        $variant = $product->variants()->where('size', 'Custom')->first();

        $payload = [
            'customer_name' => 'Custom fit test customer',
            'customer_phone' => '030'.str_repeat('0', 8),
            'customer_email' => 'custom-fit@example.test',
            'shipping_address' => 'Synthetic test delivery address B',
            'city' => 'Islamabad',
            'payment_method' => 'cod',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'custom_fitting' => [
                        'unit' => 'inches',
                        'shirt_length' => 39.0,
                        'shoulder' => 14.0,
                        'sleeve_length' => 21.0,
                        'trouser_length' => 38.0,
                        'trouser_waist' => 31.0,
                        'chest' => 38.0,
                        'waist' => 34.0,
                        'hips' => 40.0,
                        'neck_style' => 'Boat neck with slit',
                        'special_instructions' => 'Fit should be slightly loose.',
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/orders', $payload, ['Idempotency-Key' => (string) \Illuminate\Support\Str::uuid()]);

        $response->assertStatus(201);
        $orderNumber = $response->json('data.order_number');

        $order = Order::where('order_number', $orderNumber)->with('stitchingRequests.measurement')->first();
        $this->assertNotNull($order);
        $this->assertCount(1, $order->stitchingRequests);

        $request = $order->stitchingRequests->first();
        $this->assertEquals('custom_fitting', $request->request_type);
        $this->assertEquals(39.0, (float) $request->orderMeasurement()->shirt_length);
        $this->assertEquals('Boat neck with slit', $request->neck_style);
    }

    public function test_order_creation_standalone_stitching_with_image_upload(): void
    {
        Storage::fake('local');

        $service = StitchingService::first();
        $imageFile = UploadedFile::fake()->image('reference_design.jpg', 600, 800);

        $payload = [
            'customer_name' => 'Stitching test customer',
            'customer_phone' => '030'.str_repeat('0', 8),
            'customer_email' => 'stitching@example.test',
            'shipping_address' => 'Synthetic test delivery address C',
            'city' => 'Karachi',
            'payment_method' => 'cod',
            'design_reference_image' => $imageFile,
            'standalone_stitching' => json_encode([
                'stitching_service_id' => $service->id,
                'fabric_delivery_method' => 'courier_by_customer',
                'measurement_method' => 'manual',
                'neck_style' => 'Round neck with lace',
                'sleeve_style' => 'Quarter sleeves',
                'special_instructions' => 'I will courier the fabric after confirming the address.',
                'measurements' => [
                    'unit' => 'inches',
                    'shirt_length' => 40.0,
                    'chest' => 37.0,
                    'shoulder' => 14.5,
                    'waist' => 31,
                    'hips' => 38,
                    'sleeve_length' => 21,
                    'trouser_length' => 38,
                    'trouser_waist' => 31,
                ],
            ]),
        ];

        $response = $this->post('/api/v1/orders', $payload, ['Idempotency-Key' => (string) \Illuminate\Support\Str::uuid()]);

        $response->assertStatus(201);
        $orderNumber = $response->json('data.order_number');

        $order = Order::where('order_number', $orderNumber)->with('stitchingRequests.measurement')->first();
        $this->assertNotNull($order);
        $this->assertCount(1, $order->stitchingRequests);

        $request = $order->stitchingRequests->first();
        $this->assertEquals('standalone_stitching', $request->request_type);
        $this->assertEquals('courier_by_customer', $request->fabric_delivery_method);
        $this->assertNotEmpty($request->design_reference_image);
        Storage::disk('local')->assertExists($request->design_reference_image);
        $response->assertJsonMissingPath('data.stitching_requests.0.design_reference_image');
        $this->assertEquals(40.0, (float) $request->orderMeasurement()->shirt_length);
    }

    public function test_order_tracking_endpoint(): void
    {
        $order = Order::where('order_number', 'KF-2026-0001')->firstOrFail();
        $token = app(\App\Services\OrderTracking::class)->issue($order);

        $this->postJson('/api/v1/orders/track', [
            'order_number' => $order->order_number,
            'tracking_token' => $token,
        ])->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.order_status', $order->order_status)
            ->assertJsonMissingPath('data.customer_phone')
            ->assertJsonMissingPath('data.customer_name')
            ->assertJsonMissingPath('data.shipping_address')
            ->assertJsonMissingPath('data.stitching_requests.0.measurement')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->postJson('/api/v1/orders/track', [
            'order_number' => $order->order_number,
            'tracking_token' => str_repeat('a', 64),
        ])->assertNotFound();
        $this->getJson('/api/v1/orders/track')->assertStatus(405);
    }

    public function test_authentication_flow(): void
    {
        config([
            'cors.allowed_origins' => ['http://localhost:3000'],
            'sanctum.stateful' => ['localhost:3000'],
        ]);
        $this->withHeader('Origin', 'http://localhost:3000');

        $password = \Illuminate\Support\Str::random(28);
        $register = $this->postJson('/api/v1/register', [
            'name' => 'Registration test customer',
            'email' => 'Registration@Example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $register->assertCreated()
            ->assertJsonPath('user.email', 'registration@example.test')
            ->assertJsonMissingPath('token')
            ->assertCookie(config('session.cookie'));

        $this->getJson('/api/v1/user/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'registration@example.test');

        $this->postJson('/api/v1/logout')->assertOk();
        $this->getJson('/api/v1/user/me')->assertUnauthorized();

        $this->postJson('/api/v1/login', [
            'email' => 'registration@example.test',
            'password' => $password,
        ])->assertOk()->assertJsonStructure(['user'])->assertJsonMissingPath('token');
    }

    public function test_saved_customer_measurements_endpoints(): void
    {
        $user = User::where('role', 'customer')->first();

        // Unauthenticated access fails
        $unauthResponse = $this->getJson('/api/v1/user/measurements');
        $unauthResponse->assertStatus(401);

        // Authenticated GET
        $getResponse = $this->actingAs($user, 'web')->getJson('/api/v1/user/measurements');

        $getResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'profile_name',
                        'unit',
                        'shirt_length',
                        'shoulder',
                        'chest',
                    ],
                ],
            ]);

        // Authenticated POST new measurement profile
        $postResponse = $this->actingAs($user, 'web')->postJson('/api/v1/user/measurements', [
                'profile_name' => 'Summer Kurti Fit',
                'unit' => 'inches',
                'shirt_length' => 41.5,
                'shoulder' => 15.0,
                'chest' => 39.0,
                'waist' => 35.0,
                'trouser_length' => 38.0,
                'extra_notes' => 'Straight relaxed cut.',
            ]);

        $postResponse->assertStatus(201)
            ->assertJsonPath('data.profile_name', 'Summer Kurti Fit')
            ->assertJsonPath('data.shirt_length', 41.5);
    }
}
