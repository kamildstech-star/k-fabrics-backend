<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class OrderReceiptDelivery
{
    public function eligible(Order $order): bool
    {
        $mailer = (string) config('checkout.receipt_email_mailer');
        if (! config('checkout.receipt_email_enabled')
            || ! in_array(config("mail.mailers.{$mailer}.transport"), ['smtp', 'postmark', 'resend', 'ses', 'ses-v2'], true)
            || filled(config('mail.to.address'))
            || filled(config("mail.mailers.{$mailer}.to.address"))
            || ! $this->trackingUrl()
            || ! $order->customer_email
            || ! $order->tracking_nonce
            || $order->receipt_email_sent_at) {
            return false;
        }

        $account = $order->user;
        if (! $account?->email_verified_at || $account->role !== 'customer') {
            return false;
        }

        return strcasecmp($account->email, $order->customer_email) === 0;
    }

    public function deliver(int $orderId, OrderTracking $tracking): void
    {
        DB::transaction(function () use ($orderId, $tracking): void {
            $order = Order::query()->lockForUpdate()->find($orderId);
            if (! $order || ! $this->eligible($order) || ! ($token = $tracking->token($order))) {
                return;
            }

            $reference = $order->order_number;
            $total = Money::decimal(Money::minor($order->total_amount));
            $url = $this->trackingUrl();
            $body = implode("\n", [
                'K. Fabrics received your order.',
                '',
                "Order number: {$reference}",
                "Order total: PKR {$total}",
                '',
                'To see the current status or save your itemized receipt, open:',
                (string) $url,
                'Enter your order number and this private tracking code:',
                $token,
                '',
                'Keep the code private. This message acknowledges your order; it is not proof of payment.',
            ]);
            try {
                $sent = Mail::mailer((string) config('checkout.receipt_email_mailer'))
                    ->raw($body, fn ($message) => $message->to($order->customer_email)->subject('Your K. Fabrics order receipt'));
            } catch (Throwable) {
                // Transport exceptions may include recipient details. Log only a generic failure.
                throw new RuntimeException('Transactional order receipt delivery failed.');
            }
            if ($sent === null) {
                throw new RuntimeException('Transactional order receipt was not accepted.');
            }

            $order->forceFill(['receipt_email_sent_at' => now()])->save();
        });
    }

    private function trackingUrl(): ?string
    {
        $url = (string) config('checkout.receipt_tracking_url');
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https'
            || ($parts['path'] ?? '') !== '/track-order'
            || array_key_exists('user', $parts) || array_key_exists('query', $parts) || array_key_exists('fragment', $parts)) {
            return null;
        }

        $origin = 'https://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        return in_array($origin, config('cors.allowed_origins', []), true) ? $url : null;
    }
}
