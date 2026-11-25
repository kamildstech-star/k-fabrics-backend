<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CatalogRequest;
use App\Http\Resources\Api\V1\ProductDetailResource;
use App\Http\Resources\Api\V1\ProductListResource;
use App\Services\CatalogDiscovery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Display a paginated listing of active products with filters and sorting.
     */
    public function index(CatalogRequest $request, CatalogDiscovery $catalog): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = $catalog->products($filters)->with([
            'category',
            'images',
            'variants' => fn ($variants) => $variants->where('is_active', true),
        ]);
        $catalog->order($query, $filters['sort'] ?? null, $filters['search'] ?? null);
        $perPage = min(max((int) ($filters['per_page'] ?? 12), 1), 100);

        return ProductListResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function facets(CatalogRequest $request, CatalogDiscovery $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog->facets($request->safe()->only(['category', 'search']))]);
    }

    /**
     * Display the specified active product.
     */
    public function show(string $slug, CatalogDiscovery $catalog): ProductDetailResource
    {
        $product = $catalog->publicProducts()
            ->where('slug', $slug)
            ->with([
                'category',
                'images' => fn ($q) => $q->orderBy('sort_order', 'asc'),
                'variants' => fn ($q) => $q->where('is_active', true),
            ])
            ->firstOrFail();

        return new ProductDetailResource($product);
    }

    /**
     * Display related products in the same category.
     */
    public function related(string $slug, CatalogDiscovery $catalog): AnonymousResourceCollection
    {
        $current = $catalog->publicProducts()->with('category')->where('slug', $slug)
            ->firstOrFail();

        $related = $catalog->publicProducts()
            ->where('id', '!=', $current->id)
            ->where(function ($q) use ($current) {
                $q->where('category_id', $current->category_id);
                if ($current->category?->parent_id) {
                    $q->orWhere('category_id', $current->category->parent_id);
                }
            })
            ->with([
                'category',
                'images' => fn ($q) => $q->orderBy('sort_order', 'asc'),
                'variants' => fn ($q) => $q->where('is_active', true),
            ])
            ->take(4)
            ->get();

        if ($related->count() < 4) {
            $needed = 4 - $related->count();
            $existingIds = $related->pluck('id')->push($current->id)->all();
            $supplement = $catalog->publicProducts()
                ->whereNotIn('id', $existingIds)
                ->with([
                    'category',
                    'images' => fn ($q) => $q->orderBy('sort_order', 'asc'),
                    'variants' => fn ($q) => $q->where('is_active', true),
                ])
                ->latest()
                ->take($needed)
                ->get();
            $related = $related->merge($supplement);
        }

        return ProductListResource::collection($related);
    }
}
