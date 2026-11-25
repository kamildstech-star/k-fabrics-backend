<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppOrderConfirmation
{
    public function isConfigured(): bool
    {
        return (bool) config('services.whatsapp.enabled')
            && preg_match('/^v[0-9]+\.[0-9]+$/D', (string) config('services.whatsapp.api_version'))
            && preg_match('/^[0-9]+$/D', (string) config('services.whatsapp.phone_number_id'))
            && filled(config('services.whatsapp.verify_token'))
            && filled(config('services.whatsapp.app_secret'))
            && filled(config('services.whatsapp.access_token'))
            && filled(config('services.whatsapp.confirmation_template'))
            && filled(config('app.key'));
    }

    public function button(Order $order, string $action): string
    {
        $payload = "kf1:{$action}:{$order->getKey()}";
        $signature = hash_hmac('sha256', "whatsapp-order:v1:{$payload}", (string) config('app.key'));

        return "{$payload}:{$signature}";
    }

    public function send(Order $order): void
    {
        if (! $this->isConfigured() || $order->payment_method !== 'cod' || $order->order_status !== 'pending') {
            return;
        }

        $phone = app(OtpService::class)->normalizePhone($order->customer_phone);
        if (! preg_match('/^03[0-9]{9}$/D', $phone)) {
            return;
        }

        $version = config('services.whatsapp.api_version');
        $numberId = config('services.whatsapp.phone_number_id');
        $button = fn (string $action, string $index): array => [
            'type' => 'button',
            'sub_type' => 'quick_reply',
            'index' => $index,
            'parameters' => [['type' => 'payload', 'payload' => $this->button($order, $action)]],
        ];

        try {
            $response = Http::withToken((string) config('services.whatsapp.access_token'))
                ->acceptJson()->connectTimeout(3)->timeout(8)
                ->post("https://graph.facebook.com/{$version}/{$numberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => '92'.substr($phone, 1),
                    'type' => 'template',
                    'template' => [
                        'name' => config('services.whatsapp.confirmation_template'),
                        'language' => ['code' => config('services.whatsapp.language')],
                        'components' => [
                            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $order->order_number]]],
                            $button('confirm', '0'),
                            $button('cancel', '1'),
                        ],
                    ],
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('WhatsApp order confirmation is temporarily unavailable.');
        }

        if (! $response->successful() || ! filled($response->json('messages.0.id'))) {
            throw new RuntimeException('WhatsApp order confirmation was not accepted.');
        }
    }
}
