<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StitchingRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $revenue = Order::where(function ($query) {
            $query->whereIn('payment_status', ['paid', 'partially_paid'])
                ->orWhereIn('order_status', ['delivered', 'stitching', 'processing', 'dispatched']);
        })->sum('total_amount');

        $pendingOrders = Order::where('order_status', 'pending')->count();

        $activeStitching = StitchingRequest::whereNotIn('status', ['completed', 'cancelled'])->count();

        $lowStockCount = ProductVariant::where('is_active', true)
            ->where('stock_quantity', '<=', 3)
            ->count();

        return [
            Stat::make('Gross Revenue', 'Rs. ' . number_format($revenue))
                ->description('Total confirmed orders')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Pending Orders', (string) $pendingOrders)
                ->description('Awaiting confirmation / dispatch')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingOrders > 0 ? 'warning' : 'gray'),

            Stat::make('Active Atelier Stitching', (string) $activeStitching)
                ->description('In cutting, fitting & sewing')
                ->descriptionIcon('heroicon-m-scissors')
                ->color('primary'),

            Stat::make('Low Stock Alerts', (string) $lowStockCount)
                ->description('Variants with 3 or fewer units')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'danger' : 'success'),
        ];
    }
}
