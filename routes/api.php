<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\CustomerMeasurementController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\StitchingServiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // 1. Catalog & Stitching Services
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
    Route::get('/stitching-services', [StitchingServiceController::class, 'index']);

    // 2. Coupons, Checkout & Order Tracking
    Route::post('/coupons/validate', [CouponController::class, 'validateCoupon']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/track', [OrderController::class, 'track']);

    // 3. Authentication
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // 4. Authenticated User Endpoints (Sanctum)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user/me', [AuthController::class, 'me']);
        Route::get('/user', [AuthController::class, 'me']);

        // Saved Customer Measurements
        Route::get('/user/measurements', [CustomerMeasurementController::class, 'index']);
        Route::post('/user/measurements', [CustomerMeasurementController::class, 'store']);
    });
});
