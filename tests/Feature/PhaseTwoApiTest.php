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
            'customer_name' => 'Sara Ahmed',
            'customer_phone' => '+923009876543',
            'customer_email' => 'sara@example.com',
            'shipping_address' => 'Flat 402, Al-Hafeez Heights, Gulberg',
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

        \App\Models\OtpVerification::create([
            'phone' => '03009876543',
            'purpose' => 'checkout_cod',
            'code_hash' => \Illuminate\Support\Facades\Hash::make('1234'),
            'attempts' => 0,
            'max_attempts' => 5,
            'verified_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/orders', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.customer_name', 'Sara Ahmed')
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

        // Order format KF-2026-XXXX
        $orderNumber = $response->json('data.order_number');
        $this->assertMatchesRegularExpression('/^KF-\d{4}-\d{4}$/', $orderNumber);
    }

    public function test_order_creation_with_custom_fitting(): void
    {
        $product = Product::where('status', 'active')->first();
        $variant = $product->variants()->where('size', 'Custom')->first();

        $payload = [
            'customer_name' => 'Fatima Ali',
            'customer_phone' => '+923331122334',
            'customer_email' => 'fatima@example.com',
            'shipping_address' => 'House 12, Street 3, F-7/2',
            'city' => 'Islamabad',
            'payment_method' => 'bank_transfer',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'custom_fitting' => [
                        'unit' => 'inches',
                        'shirt_length' => 39.0,
                        'chest' => 38.0,
                        'waist' => 34.0,
                        'hips' => 40.0,
                        'neck_style' => 'Boat neck with slit',
                        'special_instructions' => 'Fit should be slightly loose.',
                    ],
                ],
            ],
        ];

        \App\Models\OtpVerification::create([
            'phone' => '03009876543',
            'purpose' => 'checkout_cod',
            'code_hash' => \Illuminate\Support\Facades\Hash::make('1234'),
            'attempts' => 0,
            'max_attempts' => 5,
            'verified_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/orders', $payload);

        $response->assertStatus(201);
        $orderNumber = $response->json('data.order_number');

        $order = Order::where('order_number', $orderNumber)->with('stitchingRequests.measurement')->first();
        $this->assertNotNull($order);
        $this->assertCount(1, $order->stitchingRequests);

        $request = $order->stitchingRequests->first();
        $this->assertEquals('custom_fitting', $request->request_type);
        $this->assertEquals(39.0, (float) $request->measurement->shirt_length);
        $this->assertEquals('Boat neck with slit', $request->neck_style);
    }

    public function test_order_creation_standalone_stitching_with_image_upload(): void
    {
        Storage::fake('public');

        $service = StitchingService::first();
        $imageFile = UploadedFile::fake()->image('reference_design.jpg', 600, 800);

        $payload = [
            'customer_name' => 'Zainab Bibi',
            'customer_phone' => '+923451234567',
            'customer_email' => 'zainab@example.com',
            'shipping_address' => 'Plot 88, Phase 6, DHA',
            'city' => 'Karachi',
            'payment_method' => 'jazzcash',
            'design_reference_image' => $imageFile,
            'standalone_stitching' => json_encode([
                'stitching_service_id' => $service->id,
                'fabric_delivery_method' => 'rider_pickup',
                'neck_style' => 'Round neck with lace',
                'sleeve_style' => 'Quarter sleeves',
                'special_instructions' => 'Fabric will be handed over to rider.',
                'measurements' => [
                    'unit' => 'inches',
                    'shirt_length' => 40.0,
                    'chest' => 37.0,
                ],
            ]),
        ];

        $response = $this->post('/api/v1/orders', $payload);

        $response->assertStatus(201);
        $orderNumber = $response->json('data.order_number');

        $order = Order::where('order_number', $orderNumber)->with('stitchingRequests.measurement')->first();
        $this->assertNotNull($order);
        $this->assertCount(1, $order->stitchingRequests);

        $request = $order->stitchingRequests->first();
        $this->assertEquals('standalone_stitching', $request->request_type);
        $this->assertEquals('rider_pickup', $request->fabric_delivery_method);
        $this->assertNotEmpty($request->design_reference_image);
        $this->assertEquals(40.0, (float) $request->measurement->shirt_length);
    }

    public function test_order_tracking_endpoint(): void
    {
        $order = Order::where('order_number', 'KF-2026-0001')->first();

        // Valid tracking query
        $response = $this->getJson("/api/v1/orders/track?order_number={$order->order_number}&customer_phone={$order->customer_phone}");

        $response->assertStatus(200)
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.customer_phone', $order->customer_phone)
            ->assertJsonStructure([
                'data' => [
                    'order_number',
                    'order_status',
                    'payment_status',
                    'total_amount',
                    'items',
                    'stitching_requests',
                ],
            ]);

        // Wrong phone returns 404
        $wrongPhoneResponse = $this->getJson("/api/v1/orders/track?order_number={$order->order_number}&customer_phone=03999999999");
        $wrongPhoneResponse->assertStatus(404)
            ->assertJson(['message' => 'Order not found. Please check your order number and phone number.']);
    }

    public function test_authentication_flow(): void
    {
        // 1. Register
        $regResponse = $this->postJson('/api/v1/register', [
            'name' => 'Mariam Tariq',
            'email' => 'mariam@example.com',
            'phone' => '+923111222333',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $regResponse->assertStatus(201)
            ->assertJsonStructure(['user', 'token', 'message']);

        $token = $regResponse->json('token');

        // 2. Profile (Me)
        $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('data.email', 'mariam@example.com');

        // 3. Logout
        $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/logout');

        $logoutResponse->assertStatus(200);

        // 4. Login
        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => 'mariam@example.com',
            'password' => 'secret123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_saved_customer_measurements_endpoints(): void
    {
        $user = User::where('role', 'customer')->first();
        $token = $user->createToken('test_token')->plainTextToken;

        // Unauthenticated access fails
        $unauthResponse = $this->getJson('/api/v1/user/measurements');
        $unauthResponse->assertStatus(401);

        // Authenticated GET
        $getResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user/measurements');

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
        $postResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/user/measurements', [
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
