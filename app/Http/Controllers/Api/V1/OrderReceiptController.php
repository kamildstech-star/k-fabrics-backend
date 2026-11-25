<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TrackOrderRequest;
use App\Services\OrderReceipt;
use App\Services\OrderTracking;
use Illuminate\Http\JsonResponse;

class OrderReceiptController extends Controller
{
    public function __invoke(TrackOrderRequest $request, OrderTracking $tracking, OrderReceipt $receipt): JsonResponse
    {
        $input = $request->validated();
        $order = $tracking->lookup($input['order_number'], $input['tracking_token']);

        if (! $order) {
            return response()->json(['message' => 'Order not found. Check the order number and tracking code.'], 404)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->json(['data' => $receipt->details($order)])
            ->header('Cache-Control', 'private, no-store');
    }
}
