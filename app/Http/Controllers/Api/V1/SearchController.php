<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Fast predictive search suggestions.
     */
    public function suggest(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([
                'data' => [],
            ]);
        }

        $products = Product::where('status', 'active')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('fabric_type', 'like', "%{$q}%")
                    ->orWhere('piece_type', 'like', "%{$q}%")
                    ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$q}%"));
            })
            ->with([
                'images' => fn ($img) => $img->orderBy('sort_order', 'asc'),
            ])
            ->take(5)
            ->get();

        $data = $products->map(function ($product) {
            $primaryImg = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'fabric_type' => $product->fabric_type,
                'piece_type' => $product->piece_type,
                'base_price' => (float) $product->base_price,
                'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
                'primary_image' => $primaryImg?->image_url,
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }
}
