<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutOtpController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\CustomerAddressController;
use App\Http\Controllers\Api\V1\CustomerMeasurementController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductReviewController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\StitchingServiceController;
use App\Http\Controllers\Api\V1\StockAlertController;
use App\Http\Controllers\Api\V1\WhatsAppWebhookController;
use App\Http\Controllers\Api\V1\WishlistController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // 1. Catalog & Stitching Services
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
    Route::get('/products/{slug}/related', [ProductController::class, 'related']);
    Route::get('/products/{slug}/reviews', [ProductReviewController::class, 'index']);
    Route::post('/products/{slug}/reviews', [ProductReviewController::class, 'store']);
    Route::get('/stitching-services', [StitchingServiceController::class, 'index']);
    Route::get('/search/suggest', [SearchController::class, 'suggest']);
    Route::post('/stock-alerts', [StockAlertController::class, 'store']);

    // 2. Coupons, Checkout & Order Tracking
    Route::post('/coupons/validate', [CouponController::class, 'validateCoupon']);
    Route::post('/checkout/send-otp', [CheckoutOtpController::class, 'sendOtp']);
    Route::post('/checkout/verify-otp', [CheckoutOtpController::class, 'verifyOtp']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/track', [OrderController::class, 'track']);
    Route::match(['get', 'post'], '/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);

    // 3. Authentication
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // 4. Authenticated User Endpoints (Sanctum)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user/me', [AuthController::class, 'me']);
        Route::get('/user', [AuthController::class, 'me']);

        // Orders (History & Reorder)
        Route::get('/user/orders', [OrderController::class, 'userOrders']);

        // Saved Customer Measurements
        Route::get('/user/measurements', [CustomerMeasurementController::class, 'index']);
        Route::post('/user/measurements', [CustomerMeasurementController::class, 'store']);
        Route::put('/user/measurements/{id}', [CustomerMeasurementController::class, 'update']);
        Route::delete('/user/measurements/{id}', [CustomerMeasurementController::class, 'destroy']);

        // Wishlist
        Route::get('/user/wishlist', [WishlistController::class, 'index']);
        Route::get('/user/wishlist/ids', [WishlistController::class, 'productIds']);
        Route::post('/user/wishlist/toggle', [WishlistController::class, 'toggle']);

        // Saved Shipping Addresses
        Route::get('/user/addresses', [CustomerAddressController::class, 'index']);
        Route::post('/user/addresses', [CustomerAddressController::class, 'store']);
        Route::put('/user/addresses/{id}', [CustomerAddressController::class, 'update']);
        Route::delete('/user/addresses/{id}', [CustomerAddressController::class, 'destroy']);
    });
});
