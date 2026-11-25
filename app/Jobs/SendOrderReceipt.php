<?php

namespace App\Jobs;

use App\Services\OrderReceiptDelivery;
use App\Services\OrderTracking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderReceipt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $orderId)
    {
    }

    public function handle(OrderReceiptDelivery $delivery, OrderTracking $tracking): void
    {
        $delivery->deliver($this->orderId, $tracking);
    }
}
