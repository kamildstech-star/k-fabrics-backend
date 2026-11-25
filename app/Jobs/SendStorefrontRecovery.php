<?php

namespace App\Jobs;

use App\Services\StorefrontRecovery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendStorefrontRecovery implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [60, 180];

    /** @param array<string, string> $input short-lived delivery lookup; encrypted in queue payloads */
    public function __construct(
        public string $kind,
        public array $input,
        public int $requestedAt,
    ) {}

    public function handle(StorefrontRecovery $recovery): void
    {
        if ($this->requestedAt < now()->subMinutes(15)->timestamp || ! $recovery->available()) {
            return;
        }

        if ($this->kind === 'password') {
            $recovery->sendPasswordCode($this->input['email']);
        } elseif ($this->kind === 'tracking') {
            $recovery->sendTrackingCode($this->input['order_number'], $this->input['email'], $this->input['phone']);
        }
    }
}
