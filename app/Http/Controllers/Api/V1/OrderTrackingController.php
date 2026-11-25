<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TrackOrderRequest;
use App\Models\StitchingRequest;
use App\Services\CheckoutPricing;
use App\Services\OrderTracking;
use Illuminate\Http\JsonResponse;

class OrderTrackingController extends Controller
{
    public function __invoke(TrackOrderRequest $request, OrderTracking $tracking, CheckoutPricing $pricing): JsonResponse
    {
        $input = $request->validated();
        $order = $tracking->lookup($input['order_number'], $input['tracking_token']);

        if (! $order) {
            return response()->json(['message' => 'Order not found. Check the order number and tracking code.'], 404)
                ->header('Cache-Control', 'private, no-store');
        }

        $stitching = $order->stitchingRequests()->get(['request_type', 'status']);

        return response()->json(['data' => [
            'order_number' => $order->order_number,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'bank_balance' => $pricing->paymentProgress($order),
            'courier_name' => $order->courier_name,
            'tracking_number' => $order->tracking_number,
            'created_at' => $order->created_at?->toISOString(),
            'stitching_requests' => $stitching->map(fn (StitchingRequest $request) => [
                'request_type' => $request->request_type,
                'status' => $request->status,
            ])->values(),
        ]])->header('Cache-Control', 'private, no-store');
    }
}
