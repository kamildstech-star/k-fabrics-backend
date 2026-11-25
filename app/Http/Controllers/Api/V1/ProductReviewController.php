<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductReviewResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $reviews = $product->approvedReviews()->latest()->limit(50)->get();
        $counts = $product->approvedReviews()->selectRaw('rating, count(*) as rating_count')
            ->groupBy('rating')->pluck('rating_count', 'rating');
        $distribution = [];
        $totalReviews = 0;
        $ratingSum = 0;
        foreach ([5, 4, 3, 2, 1] as $rating) {
            $distribution[$rating] = (int) $counts->get($rating, 0);
            $totalReviews += $distribution[$rating];
            $ratingSum += $distribution[$rating] * $rating;
        }
        $averageRating = $totalReviews > 0 ? round($ratingSum / $totalReviews, 1) : null;

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
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
        ]);

        $user = $request->user('web');
        $order = $user ? Order::query()->where('user_id', $user->id)
            ->where('order_status', 'delivered')
            ->whereHas('items', fn ($query) => $query->where('product_id', $product->id))
            ->latest()->first() : null;

        $photoPath = $request->file('photo')?->store('review_photos', 'local');

        $review = ProductReview::create([
            'product_id' => $product->id,
            'user_id' => $user?->id,
            'order_id' => $order?->id,
            'reviewer_name' => $validated['reviewer_name'],
            'rating' => (int) $validated['rating'],
            'comment' => $validated['comment'],
            'photo_url' => $photoPath,
            'is_verified_purchase' => $order !== null,
            'is_approved' => false,
        ]);

        return (new ProductReviewResource($review))
            ->response()
            ->setStatusCode(201);
    }

    public function publicPhoto(ProductReview $review): StreamedResponse
    {
        abort_unless($review->is_approved && $review->product()->where('status', 'active')->exists(), 404);

        return $this->streamPrivatePhoto($review);
    }

    public function staffPhoto(string $review): StreamedResponse
    {
        return $this->streamPrivatePhoto(ProductReview::findOrFail($review));
    }

    private function streamPrivatePhoto(ProductReview $review): StreamedResponse
    {
        abort_unless($review->hasPrivatePhoto(), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($review->photo_url), 404);
        $mime = $disk->mimeType($review->photo_url);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return $disk->response($review->photo_url, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
