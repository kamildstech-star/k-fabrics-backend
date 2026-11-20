<?php

namespace Tests\Feature;

use App\Models\CustomerAddress;
use App\Models\CustomerMeasurement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseSixAccountWishlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authenticated_user_can_view_orders(): void
    {
        $user = User::where('email', 'ayesha@kfabrics.com')->first();
        $response = $this->actingAs($user)->getJson('/api/v1/user/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'order_number',
                        'total_amount',
                        'order_status',
                        'items',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_customer_measurements_update_and_delete(): void
    {
        $user = User::where('email', 'ayesha@kfabrics.com')->first();
        $measurement = CustomerMeasurement::where('user_id', $user->id)->first();

        // Update
        $updateResponse = $this->actingAs($user)->putJson("/api/v1/user/measurements/{$measurement->id}", [
            'profile_name' => 'Ayesha Updated Festive Fit',
            'unit' => 'inches',
            'shirt_length' => 40.0,
            'shoulder' => 15.0,
            'chest' => 39.0,
            'waist' => 35.0,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.profile_name', 'Ayesha Updated Festive Fit')
            ->assertJsonPath('data.shirt_length', 40);

        $this->assertDatabaseHas('customer_measurements', [
            'id' => $measurement->id,
            'profile_name' => 'Ayesha Updated Festive Fit',
        ]);

        // Delete
        $deleteResponse = $this->actingAs($user)->deleteJson("/api/v1/user/measurements/{$measurement->id}");
        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('customer_measurements', [
            'id' => $measurement->id,
        ]);
    }

    public function test_wishlist_toggle_and_list(): void
    {
        $user = User::where('email', 'ayesha@kfabrics.com')->first();
        $product = Product::where('status', 'active')->first();

        // 1. Toggle ON
        $toggleOn = $this->actingAs($user)->postJson('/api/v1/user/wishlist/toggle', [
            'product_id' => $product->id,
        ]);

        $toggleOn->assertStatus(200)
            ->assertJsonPath('attached', true)
            ->assertJsonFragment(['product_ids' => [$product->id]]);

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        // 2. Fetch Wishlist Items
        $listResponse = $this->actingAs($user)->getJson('/api/v1/user/wishlist');
        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id);

        // 3. Fetch Wishlist IDs
        $idsResponse = $this->actingAs($user)->getJson('/api/v1/user/wishlist/ids');
        $idsResponse->assertStatus(200)
            ->assertJsonPath('product_ids', [$product->id]);

        // 4. Toggle OFF
        $toggleOff = $this->actingAs($user)->postJson('/api/v1/user/wishlist/toggle', [
            'product_id' => $product->id,
        ]);

        $toggleOff->assertStatus(200)
            ->assertJsonPath('attached', false)
            ->assertJsonPath('product_ids', []);

        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_saved_addresses_crud(): void
    {
        $user = User::where('email', 'ayesha@kfabrics.com')->first();

        // 1. Create first address (should auto become default)
        $createResponse = $this->actingAs($user)->postJson('/api/v1/user/addresses', [
            'label' => 'Home',
            'recipient_name' => 'Ayesha Khan',
            'phone' => '03001234567',
            'address' => 'House 12, Main Boulevard, DHA Phase 5',
            'city' => 'Lahore',
            'is_default' => false,
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.label', 'Home');

        $addressId = $createResponse->json('data.id');

        // 2. Create second address with is_default = true (should become default and demote first)
        $secondResponse = $this->actingAs($user)->postJson('/api/v1/user/addresses', [
            'label' => 'Studio',
            'recipient_name' => 'Ayesha Studio',
            'phone' => '03219876543',
            'address' => 'Floor 2, Mall 1, Gulberg III',
            'city' => 'Lahore',
            'is_default' => true,
        ]);

        $secondResponse->assertStatus(201)
            ->assertJsonPath('data.is_default', true);

        $secondId = $secondResponse->json('data.id');

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $addressId,
            'is_default' => 0,
        ]);
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $secondId,
            'is_default' => 1,
        ]);

        // 3. List addresses
        $listResponse = $this->actingAs($user)->getJson('/api/v1/user/addresses');
        $listResponse->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // 4. Update address
        $updateResponse = $this->actingAs($user)->putJson("/api/v1/user/addresses/{$addressId}", [
            'label' => 'Primary Home',
            'city' => 'Lahore',
        ]);
        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.label', 'Primary Home');

        // 5. Delete address
        $deleteResponse = $this->actingAs($user)->deleteJson("/api/v1/user/addresses/{$secondId}");
        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('customer_addresses', [
            'id' => $secondId,
        ]);

        // Remaining address should have been promoted to default
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $addressId,
            'is_default' => 1,
        ]);
    }
}
