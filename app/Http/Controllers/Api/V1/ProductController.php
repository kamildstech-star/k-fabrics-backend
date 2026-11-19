<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductDetailResource;
use App\Http\Resources\Api\V1\ProductListResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Display a paginated listing of active products with filters and sorting.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->where('status', 'active')
            ->with([
                'category',
                'images' => fn ($q) => $q->orderBy('sort_order', 'asc'),
                'variants' => fn ($q) => $q->where('is_active', true),
            ]);

        // Filter by Category Slug (matches direct category or parent category)
        if ($request->filled('category')) {
            $categorySlug = $request->query('category');
            $query->whereHas('category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug)
                    ->orWhereHas('parent', function ($pq) use ($categorySlug) {
                        $pq->where('slug', $categorySlug);
                    });
            });
        }

        // Filter by Size (matches active variant size)
        if ($request->filled('size')) {
            $size = $request->query('size');
            $query->whereHas('variants', function ($q) use ($size) {
                $q->where('is_active', true)->where('size', $size);
            });
        }

        // Filter by Fabric Type
        if ($request->filled('fabric_type')) {
            $query->where('fabric_type', $request->query('fabric_type'));
        }

        // Filter by Piece Type ('1-piece', '2-piece', '3-piece')
        if ($request->filled('piece_type')) {
            $query->where('piece_type', $request->query('piece_type'));
        }

        // Filter by Price Range (using effective price)
        if ($request->filled('min_price')) {
            $query->whereRaw('COALESCE(sale_price, base_price) >= ?', [(float) $request->query('min_price')]);
        }
        if ($request->filled('max_price')) {
            $query->whereRaw('COALESCE(sale_price, base_price) <= ?', [(float) $request->query('max_price')]);
        }

        // Filter by Featured
        if ($request->has('is_featured')) {
            $isFeatured = filter_var($request->query('is_featured'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isFeatured !== null) {
                $query->where('is_featured', $isFeatured);
            }
        }

        // Search in Name or Description
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sort = $request->query('sort', 'newest');
        match ($sort) {
            'price_asc' => $query->orderByRaw('COALESCE(sale_price, base_price) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(sale_price, base_price) DESC'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $perPage = min(max((int) $request->query('per_page', 12), 1), 100);

        return ProductListResource::collection($query->paginate($perPage));
    }

    /**
     * Display the specified active product.
     */
    public function show(string $slug): ProductDetailResource
    {
        $product = Product::query()
            ->where('status', 'active')
            ->where('slug', $slug)
            ->with([
                'category',
                'images' => fn ($q) => $q->orderBy('sort_order', 'asc'),
                'variants' => fn ($q) => $q->where('is_active', true),
            ])
            ->firstOrFail();

        return new ProductDetailResource($product);
    }
}
