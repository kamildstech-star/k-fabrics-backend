<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductReviewResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    /**
     * Display approved reviews and summary rating stats for a product.
     */
    public function index(string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        $reviews = $product->approvedReviews()
            ->latest()
            ->get();

        $totalReviews = $reviews->count();
        $averageRating = $totalReviews > 0 ? round($reviews->avg('rating'), 1) : 5.0;

        $distribution = [
            5 => $reviews->where('rating', 5)->count(),
            4 => $reviews->where('rating', 4)->count(),
            3 => $reviews->where('rating', 3)->count(),
            2 => $reviews->where('rating', 2)->count(),
            1 => $reviews->where('rating', 1)->count(),
        ];

        return response()->json([
            'data' => ProductReviewResource::collection($reviews),
            'meta' => [
                'average_rating' => $averageRating,
                'total_reviews' => $totalReviews,
                'distribution' => $distribution,
            ],
        ]);
    }

    /**
     * Submit a new review for a product.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        $validated = $request->validate([
            'reviewer_name' => ['required', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:5120'], // 5MB
            'order_number' => ['nullable', 'string', 'max:50'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user('sanctum');

        // Check if verified purchase
        $isVerified = false;
        $orderId = null;

        if ($user) {
            $hasPurchased = Order::where('user_id', $user->id)
                ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
                ->latest()
                ->first();

            if ($hasPurchased) {
                $isVerified = true;
                $orderId = $hasPurchased->id;
            }
        }

        if (! $isVerified && ! empty($validated['order_number'])) {
            $order = Order::where('order_number', trim($validated['order_number']))
                ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
                ->first();

            if ($order) {
                $isVerified = true;
                $orderId = $order->id;
            }
        }

        // Handle photo upload
        $photoUrl = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('reviews', 'public');
            $photoUrl = asset('storage/' . $path);
        }

        $review = ProductReview::create([
            'product_id' => $product->id,
            'user_id' => $user?->id,
            'order_id' => $orderId,
            'reviewer_name' => $validated['reviewer_name'],
            'rating' => (int) $validated['rating'],
            'comment' => $validated['comment'],
            'photo_url' => $photoUrl,
            'is_verified_purchase' => $isVerified,
            'is_approved' => true, // Approved by default, admin can moderate in Filament
        ]);

        return (new ProductReviewResource($review))
            ->response()
            ->setStatusCode(201);
    }
}
