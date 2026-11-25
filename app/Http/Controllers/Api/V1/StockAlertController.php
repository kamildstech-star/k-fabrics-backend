<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\StockAlert;
use App\Services\CatalogDiscovery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAlertController extends Controller
{
    public function store(Request $request, CatalogDiscovery $catalog): JsonResponse
    {
        abort_unless(config('restock.manual_alerts_enabled'), 404);
        $validated = $request->validate([
            'product_variant_id' => ['required', 'integer', 'min:1'],
            'contact' => ['required', 'string', 'email:rfc', 'max:254'],
        ]);

        DB::transaction(function () use ($validated, $catalog): void {
            // Serialize subscriptions for one SKU and recheck availability after the lock.
            $variant = ProductVariant::query()
                ->whereIn('product_id', $catalog->publicProducts()->select('products.id'))
                ->where('is_active', true)->where('size', '<>', 'Custom')
                ->lockForUpdate()->find($validated['product_variant_id']);

            if (! $variant || $variant->stock_quantity > 0) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'A restock request is not available for this size. Refresh to check availability.',
                ]);
            }

            $email = mb_strtolower(trim($validated['contact']));
            StockAlert::query()->firstOrCreate([
                'product_variant_id' => $variant->id,
                'contact' => $email,
                'notified_at' => null,
            ]);
        }, 3);

        // Same response for a new or outstanding request; no contact or identifier echoed.
        return response()->json(['success' => true], 202)->header('Cache-Control', 'private, no-store');
    }
}
