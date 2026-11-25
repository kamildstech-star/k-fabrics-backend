<?php

use App\Models\OtpVerification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => OtpVerification::query()->where('created_at', '<', now()->subDay())->delete())
    ->name('prune-checkout-otp')->daily();
