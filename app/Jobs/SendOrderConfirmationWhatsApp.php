<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmationWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function handle(): void
    {
        $message = "Shukriya! Aapka order #{$this->order->order_number} confirm karne ke liye 1 reply karein ya Cancel ke liye 2.";

        Log::info("[WhatsApp Bot Outbound] To: {$this->order->customer_phone} | Order: {$this->order->order_number} | Message: {$message}");

        // In production with WhatsApp Cloud API / Twilio:
        // Http::withToken(config('services.whatsapp.token'))->post(...);
    }
}
