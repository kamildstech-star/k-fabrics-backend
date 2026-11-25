<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StitchingRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TailorSheetController extends Controller
{
    /**
     * Print tailor sheet for an entire order.
     */
    public function printOrder(string $order): View
    {
        $order = Order::findOrFail($order);
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
    public function printStitchingRequest(string $stitchingRequest): View
    {
        $stitchingRequest = StitchingRequest::findOrFail($stitchingRequest);
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

    public function reference(string $stitchingRequest): StreamedResponse
    {
        $path = StitchingRequest::findOrFail($stitchingRequest)->design_reference_image;
        abort_unless(is_string($path) && preg_match('#^stitching_references/[A-Za-z0-9_.-]+$#D', $path), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return $disk->response($path, null, ['Content-Type' => $mime]);
    }
}
