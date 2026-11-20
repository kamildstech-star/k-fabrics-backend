<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\StockAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAlertController extends Controller
{
    /**
     * Subscribe for back-in-stock notification on an out-of-stock variant.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'contact' => ['required', 'string', 'max:255'],
        ]);

        $variant = ProductVariant::with('product')->findOrFail($validated['product_variant_id']);

        $alert = StockAlert::create([
            'product_variant_id' => $variant->id,
            'contact' => trim($validated['contact']),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Thank you! We will notify you as soon as {$variant->product->name} (Size {$variant->size}) is back in stock.",
            'alert_id' => $alert->id,
        ], 201);
    }
}
