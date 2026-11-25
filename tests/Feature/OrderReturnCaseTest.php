<?php

namespace Tests\Feature;

use App\Filament\Resources\ReturnCaseResource;
use App\Filament\Resources\ReturnCaseResource\Pages\ListReturnCases;
use App\Models\Order;
use App\Models\ReturnCase;
use App\Models\User;
use App\Services\OrderTracking;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class OrderReturnCaseTest extends TestCase
{
    use RefreshDatabase;

    private const REQUEST = '/api/v1/orders/returns/request';

    private const STATUS = '/api/v1/orders/returns/status';

    public function test_guest_can_request_once_with_tracking_proof_and_never_receives_internal_details(): void
    {
        $order = $this->makeOrder();
        $proof = ['order_number' => $order->order_number, 'tracking_token' => app(OrderTracking::class)->issue($order)];
        $this->postJson(self::STATUS, $proof)->assertOk()->assertExactJson(['data' => null]);

        $response = $this->postJson(self::REQUEST, $proof + $this->requestBody())
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.type', 'exchange')
            ->assertJsonStructure(['data' => ['reference', 'type', 'status', 'submitted_at', 'updated_at']]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $case = ReturnCase::query()->sole();
        $this->postJson(self::REQUEST, $proof + $this->requestBody())
            ->assertOk()->assertJsonPath('data.reference', $case->reference);
        $this->assertDatabaseCount('return_cases', 1);

        $staff = User::factory()->create(['role' => 'admin']);
        $case->advance('under_review', $staff, 'Internal review note');
        $this->postJson(self::STATUS, $proof)->assertOk()
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonMissingPath('data.staff_note')
            ->assertJsonMissingPath('data.status_history')
            ->assertJsonMissingPath('data.description')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.order_id')
            ->assertJsonMissingPath('data.tracking_token');
        $this->assertSame('delivered', $order->fresh()->order_status);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_private_proof_or_ownership_is_required_and_undelivered_orders_cannot_submit(): void
    {
        $owner = User::factory()->create();
        $another = User::factory()->create();
        $order = $this->makeOrder($owner, 'shipped');
        $number = ['order_number' => $order->order_number];
        $this->actingAs($another)->postJson(self::STATUS, $number)->assertNotFound();
        $this->postJson(self::REQUEST, $number + ['tracking_token' => str_repeat('0', 64)] + $this->requestBody())->assertNotFound();
        $this->actingAs($owner)->postJson(self::REQUEST, $number + $this->requestBody())->assertConflict();
        $this->assertDatabaseCount('return_cases', 0);

        $order->update(['order_status' => 'delivered']);
        $this->postJson(self::REQUEST, $number + $this->requestBody())->assertCreated();
        $this->postJson(self::STATUS, $number)->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->assertDatabaseCount('return_cases', 1);
    }

    public function test_staff_can_record_review_without_changing_order_or_issuing_payment(): void
    {
        $order = $this->makeOrder();
        $case = ReturnCase::create($this->requestBody() + ['order_id' => $order->id, 'reference' => 'KR-'.Str::ulid()]);
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($customer)->get(ReturnCaseResource::getUrl('index'))->assertForbidden();
        $this->actingAs($admin)->get(ReturnCaseResource::getUrl('index'))->assertOk();
        Livewire::test(ListReturnCases::class)
            ->assertCanSeeTableRecords([$case])
            ->callTableAction('advance', $case, data: ['status' => 'under_review', 'staff_note' => 'Review item condition'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('under_review', $case->fresh()->status);
        $this->assertSame($admin->id, $case->fresh()->handled_by);
        $this->assertSame('submitted', $case->fresh()->status_history[0]['from']);

        $case->advance('approved', $admin, null);
        $case->advance('closed', $admin, 'Closed after manual follow-up');
        $this->assertCount(3, $case->fresh()->status_history);
        $this->assertSame('delivered', $order->fresh()->order_status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(0, $order->payments()->count());

        $this->expectException(ValidationException::class);
        $case->advance('under_review', $admin, null);
    }

    private function requestBody(): array
    {
        return ['type' => 'exchange', 'reason' => 'fit', 'description' => 'The test garment needs another size.'];
    }

    private function makeOrder(?User $user = null, string $status = 'delivered'): Order
    {
        return Order::create([
            'order_number' => 'KF-'.Str::ulid(), 'user_id' => $user?->id,
            'customer_name' => 'Test Buyer', 'customer_phone' => '03001111111', 'customer_email' => 'test@example.test',
            'shipping_address' => 'Local test only', 'city' => 'Lahore',
            'subtotal' => 4500, 'shipping_fee' => 250, 'discount_amount' => 0, 'total_amount' => 4750,
            'payment_method' => 'cod', 'payment_status' => 'paid', 'order_status' => $status,
        ]);
    }
}
