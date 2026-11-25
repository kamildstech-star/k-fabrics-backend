<?php

namespace Tests\Feature;

use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_recorded_money_and_sellable_inventory(): void
    {
        $this->seed();

        $order = Order::query()->firstOrFail();
        $order->update(['payment_status' => 'paid', 'order_status' => 'delivered']);
        $widget = new class extends StatsOverviewWidget
        {
            public function stats(): array
            {
                return collect($this->getStats())->mapWithKeys(
                    fn ($stat) => [(string) $stat->getLabel() => $stat->getValue()]
                )->all();
            }
        };
        // An order total alone does not create a receipt in the payment ledger.
        $this->assertSame('Rs. 0.00', $widget->stats()['Net Cash Recorded']);

        $order->payments()->create([
            'kind' => 'receipt', 'amount_minor' => 125050,
            'reference' => 'fixture-receipt', 'received_at' => now(),
        ]);
        $order->payments()->create([
            'kind' => 'refund', 'amount_minor' => 5025,
            'reference' => 'fixture-refund', 'received_at' => now(),
        ]);
        $this->assertSame('Rs. 1,200.25', $widget->stats()['Net Cash Recorded']);

        $variant = ProductVariant::query()->where('size', '!=', 'Custom')->firstOrFail();
        $variant->update(['is_active' => true, 'stock_quantity' => 2]);
        $this->assertTrue(ProductVariant::lowStock()->whereKey($variant->id)->exists());
        $variant->product()->update(['status' => 'archived']);
        $this->assertFalse(ProductVariant::lowStock()->whereKey($variant->id)->exists());
        $this->assertFalse(ProductVariant::lowStock()->where('size', 'Custom')->exists());
    }
}
