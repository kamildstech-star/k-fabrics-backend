<?php

namespace Tests\Feature;

use App\Filament\Resources\StockAlertResource\Pages\ListStockAlerts;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\StockAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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
        Storage::fake('local');

        $user = User::where('email', 'ayesha@kfabrics.com')->firstOrFail();
        $product = Product::where('slug', 'ivory-embroidered-lawn-suit')->firstOrFail();

        Order::where('user_id', $user->id)->whereHas('items', fn ($q) => $q->where('product_id', $product->id))->update(['order_status' => 'delivered']);
        $file = UploadedFile::fake()->image('customer_dress.jpg');

        $response = $this->actingAs($user)->postJson("/api/v1/products/{$product->slug}/reviews", [
            'reviewer_name' => 'Ayesha Khan',
            'rating' => 5,
            'comment' => 'The stitching and lawn finish are magnificent.',
            'photo' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_verified_purchase', true)
            ->assertJsonPath('data.reviewer_name', 'Ayesha Khan')
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.moderation_status', 'pending')
            ->assertJsonPath('data.photo_url', null);

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->id,
            'comment' => 'The stitching and lawn finish are magnificent.',
            'is_verified_purchase' => 1,
            'is_approved' => 0,
        ]);

        $review = ProductReview::findOrFail($response->json('data.id'));
        Storage::disk('local')->assertExists($review->photo_url);
        $this->get(route('products.reviews.photo', $review))->assertNotFound();
        $review->update(['is_approved' => true]);
        $this->get(route('products.reviews.photo', $review))->assertOk();
    }

    public function test_guest_order_number_does_not_verify_review(): void
    {
        $order = Order::whereHas('items')->firstOrFail();
        $product = $order->items()->firstOrFail()->product;
        $this->postJson("/api/v1/products/{$product->slug}/reviews", [
            'reviewer_name' => 'Demo reviewer',
            'rating' => 4,
            'comment' => 'A review in moderation.',
            'order_number' => $order->order_number,
        ])->assertCreated()
            ->assertJsonPath('data.is_verified_purchase', false)
            ->assertJsonPath('data.moderation_status', 'pending');
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

    public function test_restock_requests_are_off_by_default_and_opt_in_to_a_manual_email_queue(): void
    {
        $variant = ProductVariant::query()->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('status', 'active'))
            ->where('size', '<>', 'Custom')->firstOrFail();
        $payload = ['product_variant_id' => $variant->id, 'contact' => 'Fixture@Example.test'];
        $this->getJson('/api/v1/products/'.$variant->product->slug)
            ->assertOk()->assertJsonPath('data.restock_alerts_enabled', false);
        $this->postJson('/api/v1/stock-alerts', $payload)->assertNotFound();
        $this->assertDatabaseCount('stock_alerts', 0);

        config(['restock.manual_alerts_enabled' => true]);
        $this->getJson('/api/v1/products/'.$variant->product->slug)
            ->assertOk()->assertJsonPath('data.restock_alerts_enabled', true);
        $variant->update(['stock_quantity' => 2]);
        $this->postJson('/api/v1/stock-alerts', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('product_variant_id');
        $variant->update(['stock_quantity' => 0]);
        $this->postJson('/api/v1/stock-alerts', [...$payload, 'contact' => 'not an email'])
            ->assertUnprocessable()->assertJsonValidationErrors('contact');
        $this->postJson('/api/v1/stock-alerts', $payload)->assertAccepted()->assertExactJson(['success' => true]);
        $this->postJson('/api/v1/stock-alerts', [...$payload, 'contact' => 'fixture@example.test'])
            ->assertAccepted()->assertExactJson(['success' => true]);
        $this->assertDatabaseCount('stock_alerts', 1);
        $this->assertDatabaseHas('stock_alerts', [
            'product_variant_id' => $variant->id, 'contact' => 'fixture@example.test', 'notified_at' => null,
        ]);
    }
    public function test_staff_can_record_a_manual_email_only_for_a_restocked_size(): void
    {
        $variant = ProductVariant::query()->where('is_active', true)->where('size', '<>', 'Custom')->firstOrFail();
        $variant->update(['stock_quantity' => 0]);
        $alert = StockAlert::create(['product_variant_id' => $variant->id, 'contact' => 'staff-queue-fixture@example.test']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(ListStockAlerts::class)->assertTableActionHidden('markNotified', $alert);
        $variant->update(['stock_quantity' => 2]);
        $alert = $alert->fresh();
        Livewire::test(ListStockAlerts::class)->filterTable('ready')
            ->assertCanSeeTableRecords([$alert])
            ->assertTableActionVisible('markNotified', $alert)
            ->callTableAction('markNotified', $alert)
            ->assertHasNoTableActionErrors();
        $this->assertNotNull($alert->fresh()->notified_at);
    }
}
