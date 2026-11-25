<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CatalogDiscovery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Fast predictive search suggestions.
     */
    public function suggest(Request $request, CatalogDiscovery $catalog): JsonResponse
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = $input['q'] ?? '';
        $query = $catalog->discoveryScope(['search' => $search])->with(['images', 'category']);
        $products = $catalog->order($query, 'relevance', $search)->limit(5)->get();

        $data = $products->map(function ($product) {
            $primaryImg = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'fabric_type' => $product->fabric_type,
                'piece_type' => $product->piece_type,
                'category' => ['slug' => $product->category->slug, 'name' => $product->category->name],
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
