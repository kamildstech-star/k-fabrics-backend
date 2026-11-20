<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductListResource;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WishlistController extends Controller
{
    /**
     * Display a listing of products in the authenticated user's wishlist.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = $request->user()
            ->wishlistProducts()
            ->where('status', 'active')
            ->with([
                'category',
                'images' => fn ($q) => $q->orderBy('sort_order', 'asc'),
                'variants' => fn ($q) => $q->where('is_active', true),
            ])
            ->latest('wishlists.created_at')
            ->get();

        return ProductListResource::collection($products);
    }

    /**
     * Toggle a product in/out of the user's wishlist.
     */
    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $user = $request->user();
        $productId = (int) $validated['product_id'];

        $existing = Wishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            $existing->delete();
            $attached = false;
            $message = 'Product removed from wishlist.';
        } else {
            Wishlist::create([
                'user_id' => $user->id,
                'product_id' => $productId,
            ]);
            $attached = true;
            $message = 'Product saved to wishlist.';
        }

        $allIds = Wishlist::where('user_id', $user->id)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return response()->json([
            'attached' => $attached,
            'product_ids' => $allIds,
            'message' => $message,
        ]);
    }

    /**
     * Get just the array of product IDs in wishlist for fast frontend synchronization.
     */
    public function productIds(Request $request): JsonResponse
    {
        $allIds = Wishlist::where('user_id', $request->user()->id)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return response()->json([
            'product_ids' => $allIds,
        ]);
    }
}
