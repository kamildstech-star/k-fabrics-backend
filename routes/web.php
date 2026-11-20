<?php

use App\Http\Controllers\Admin\TailorSheetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Admin Printable Tailor & Packing Sheets
Route::prefix('admin')->group(function () {
    Route::get('/orders/{order}/tailor-sheet', [TailorSheetController::class, 'printOrder'])
        ->name('admin.orders.tailor-sheet');
    Route::get('/stitching-requests/{stitchingRequest}/tailor-sheet', [TailorSheetController::class, 'printStitchingRequest'])
        ->name('admin.stitching-requests.tailor-sheet');
});
