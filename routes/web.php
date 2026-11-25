<?php

use App\Http\Controllers\Admin\TailorSheetController;
use App\Http\Controllers\Api\V1\ProductReviewController;
use App\Http\Middleware\EnsureTailorAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Admin Printable Tailor & Packing Sheets
Route::prefix('admin')->middleware(EnsureTailorAccess::class)->group(function () {
    Route::get('/orders/{order}/tailor-sheet', [TailorSheetController::class, 'printOrder'])
        ->name('admin.orders.tailor-sheet');
    Route::get('/stitching-requests/{stitchingRequest}/tailor-sheet', [TailorSheetController::class, 'printStitchingRequest'])
        ->name('admin.stitching-requests.tailor-sheet');
    Route::get('/stitching-requests/{stitchingRequest}/reference', [TailorSheetController::class, 'reference'])
        ->name('admin.stitching-requests.reference');
    Route::get('/reviews/{review}/photo', [ProductReviewController::class, 'staffPhoto'])->name('admin.reviews.photo');
});
