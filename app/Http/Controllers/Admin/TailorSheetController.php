<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StitchingRequest;
use Illuminate\Contracts\View\View;

class TailorSheetController extends Controller
{
    /**
     * Print tailor sheet for an entire order.
     */
    public function printOrder(Order $order): View
    {
        $order->load([
            'items.productVariant',
            'stitchingRequests.measurement',
            'stitchingRequests.stitchingService',
            'stitchingRequests.orderItem',
        ]);

        return view('print.tailor-sheet', [
            'order' => $order,
            'stitchingRequests' => $order->stitchingRequests,
        ]);
    }

    /**
     * Print tailor sheet for a single stitching request.
     */
    public function printStitchingRequest(StitchingRequest $stitchingRequest): View
    {
        $stitchingRequest->load([
            'order.items.productVariant',
            'measurement',
            'stitchingService',
            'orderItem',
        ]);

        return view('print.tailor-sheet', [
            'order' => $stitchingRequest->order,
            'stitchingRequests' => collect([$stitchingRequest]),
        ]);
    }
}
