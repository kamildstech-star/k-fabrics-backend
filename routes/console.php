<?php

use App\Models\OtpVerification;
use App\Services\OrderLifecycle;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => OtpVerification::query()->where('created_at', '<', now()->subDay())->delete())
    ->name('prune-checkout-otp')->daily();


Artisan::command('orders:expire-unconfirmed-cod {--limit=100}', function () {
    $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
    if ($limit === false) {
        $this->error('Set --limit to an integer between 1 and 1000.');

        return 1;
    }
    $released = app(OrderLifecycle::class)->expireDueUnconfirmedCod($limit);
    $this->info("Released stock for $released unconfirmed COD orders.");

    return 0;
})->purpose('Cancel only new eligible COD orders whose confirmation hold expired');

Schedule::command('orders:expire-unconfirmed-cod')->everyFifteenMinutes()->withoutOverlapping(30);

Schedule::command('auth:clear-resets')->daily();
