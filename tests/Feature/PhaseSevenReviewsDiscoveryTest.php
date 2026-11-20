<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\StockAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseSevenReviewsDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_can_fetch_approved_reviews_and_ratings_summary(): void
    {
        $product = Product::where('slug', 'ivory-embroidered-lawn-suit')->firstOrFail();

        $response = $this->getJson("/api/v1/products/{$product->slug}/reviews");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'reviewer_name',
                        'rating',
                        'comment',
                        'photo_url',
                        'is_verified_purchase',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'average_rating',
                    'total_reviews',
                    'distribution',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('meta.total_reviews'));
    }

    public function test_can_submit_review_with_verified_purchase_detection(): void
    {
        Storage::fake('public');

        $user = User::where('email', 'ayesha@kfabrics.com')->firstOrFail();
        $product = Product::where('slug', 'ivory-embroidered-lawn-suit')->firstOrFail();

        $file = UploadedFile::fake()->image('customer_dress.jpg');

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/products/{$product->slug}/reviews", [
            'reviewer_name' => 'Ayesha Khan',
            'rating' => 5,
            'comment' => 'The stitching and lawn finish are magnificent.',
            'photo' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_verified_purchase', true)
            ->assertJsonPath('data.reviewer_name', 'Ayesha Khan')
            ->assertJsonPath('data.rating', 5);

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->id,
            'reviewer_name' => 'Ayesha Khan',
            'is_verified_purchase' => 1,
        ]);
    }

    public function test_can_fetch_related_products(): void
    {
        $product = Product::where('slug', 'ivory-embroidered-lawn-suit')->firstOrFail();

        $response = $this->getJson("/api/v1/products/{$product->slug}/related");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'base_price',
                        'primary_image',
                    ],
                ],
            ]);

        // Returns up to 4 items and excludes the current product
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertLessThanOrEqual(4, count($data));
        $this->assertNotContains($product->id, array_column($data, 'id'));
    }

    public function test_predictive_search_suggest(): void
    {
        $response = $this->getJson('/api/v1/search/suggest?q=lawn');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'fabric_type',
                        'base_price',
                        'primary_image',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertLessThanOrEqual(5, count($data));
    }

    public function test_can_subscribe_to_back_in_stock_alerts(): void
    {
        $variant = ProductVariant::firstOrFail();

        $response = $this->postJson('/api/v1/stock-alerts', [
            'product_variant_id' => $variant->id,
            'contact' => '+923001234567',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('stock_alerts', [
            'product_variant_id' => $variant->id,
            'contact' => '+923001234567',
        ]);
    }
}
