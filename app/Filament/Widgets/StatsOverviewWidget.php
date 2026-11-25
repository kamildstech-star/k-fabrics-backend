<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\ProductVariant;
use App\Models\StitchingRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $moneyByKind = OrderPayment::query()
            ->selectRaw('kind, SUM(amount_minor) AS amount')
            ->groupBy('kind')
            ->pluck('amount', 'kind');
        $netCash = ((int) ($moneyByKind['receipt'] ?? 0) - (int) ($moneyByKind['refund'] ?? 0)) / 100;

        $pendingOrders = Order::where('order_status', 'pending')->count();

        $activeStitching = StitchingRequest::query()
            ->whereIn('status', ['awaiting_fabric', 'cutting', 'stitching', 'finishing'])
            ->whereHas('order', fn ($order) => $order->whereNotIn('order_status', ['cancelled', 'returned']))
            ->count();

        $lowStockCount = ProductVariant::lowStock()->count();

        return [
            Stat::make('Net Cash Recorded', 'Rs. ' . number_format($netCash, 2))
                ->description('Recorded receipts minus refunds')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Pending Orders', (string) $pendingOrders)
                ->description('Awaiting confirmation / dispatch')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingOrders > 0 ? 'warning' : 'gray'),

            Stat::make('Active Atelier Stitching', (string) $activeStitching)
                ->description('Awaiting fabric or in production')
                ->descriptionIcon('heroicon-m-scissors')
                ->color('primary'),

            Stat::make('Low Stock Alerts', (string) $lowStockCount)
                ->description('Variants with 3 or fewer units')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'danger' : 'success'),
        ];
    }
}
