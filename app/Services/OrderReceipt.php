<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StitchingRequest;

class OrderReceipt
{
    /** Produce an itemized customer copy without contact details or tailoring measurements. */
    public function details(Order $order): array
    {
        $order->loadMissing('items', 'stitchingRequests');
        $customFits = $order->stitchingRequests->where('request_type', 'custom_fitting')->keyBy('order_item_id');

        return [
            'order_number' => $order->order_number,
            'created_at' => $order->created_at?->toISOString(),
            'currency' => 'PKR',
            'lines' => $order->items->map(fn (OrderItem $item) => [
                'description' => $item->product_name,
                'variant' => implode(' / ', array_filter([$item->size, $item->color])),
                'quantity' => $item->quantity,
                'amount' => $item->total_price,
                'custom_fitting_included' => $customFits->has($item->id),
            ])->concat($order->stitchingRequests->where('request_type', 'standalone_stitching')->map(fn (StitchingRequest $booking) => [
                'description' => 'Standalone stitching',
                'variant' => null,
                'quantity' => 1,
                'amount' => $booking->stitching_fee,
                'custom_fitting_included' => false,
            ]))->values()->all(),
            'subtotal' => $order->subtotal,
            'shipping_fee' => $order->shipping_fee,
            'discount_amount' => $order->discount_amount,
            'total_amount' => $order->total_amount,
            'advance_due' => Money::decimal((int) $order->advance_due_minor),
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'order_status' => $order->order_status,
        ];
    }
}
