<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderLifecycle;
use App\Services\OtpService;
use App\Services\WhatsAppOrderConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request, WhatsAppOrderConfirmation $confirmation): Response
    {
        abort_unless($confirmation->isConfigured(), 404);

        if ($request->isMethod('get')) {
            $token = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');
            abort_unless(
                $request->query('hub_mode') === 'subscribe'
                    && is_string($token)
                    && hash_equals((string) config('services.whatsapp.verify_token'), $token)
                    && is_string($challenge)
                    && strlen($challenge) <= 128,
                403,
            );

            return response($challenge, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $signature = $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) config('services.whatsapp.app_secret'));
        abort_unless(is_string($signature) && hash_equals($expected, $signature), 403);

        if ($request->input('object') !== 'whatsapp_business_account') {
            return response()->noContent();
        }

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                if (data_get($change, 'field') !== 'messages'
                    || (string) data_get($change, 'value.metadata.phone_number_id') !== (string) config('services.whatsapp.phone_number_id')) {
                    continue;
                }
                foreach ((array) data_get($change, 'value.messages', []) as $message) {
                    $this->applyButton($message, $confirmation);
                }
            }
        }

        return response()->noContent();
    }

    private function applyButton(mixed $message, WhatsAppOrderConfirmation $confirmation): void
    {
        $button = match (data_get($message, 'type')) {
            'button' => data_get($message, 'button.payload'),
            'interactive' => data_get($message, 'interactive.button_reply.id'),
            default => null,
        };
        $sender = data_get($message, 'from');
        if (! is_string($button) || ! is_string($sender)
            || ! preg_match('/^kf1:(confirm|cancel):([1-9][0-9]{0,18}):[a-f0-9]{64}$/D', $button, $parts)) {
            return;
        }

        try {
            DB::transaction(function () use ($sender, $button, $parts, $confirmation) {
                $order = Order::query()->whereKey($parts[2])->lockForUpdate()->first();
                if (! $order || $order->payment_method !== 'cod' || $order->order_status !== 'pending'
                    || ! $order->created_at || $order->created_at->lt(now()->subDays(2))
                    || ! hash_equals($confirmation->button($order, $parts[1]), $button)) {
                    return;
                }

                $normalizer = app(OtpService::class);
                $phone = $normalizer->normalizePhone($sender);
                if (! preg_match('/^03[0-9]{9}$/D', $phone)
                    || ! hash_equals($normalizer->normalizePhone($order->customer_phone), $phone)) {
                    return;
                }

                $lifecycle = app(OrderLifecycle::class);
                if ($parts[1] === 'confirm') {
                    $lifecycle->confirm($order, 'whatsapp_bot');
                } else {
                    $lifecycle->cancel($order, 'Customer canceled via WhatsApp confirmation', 'whatsapp_bot');
                }
            });
        } catch (ValidationException) {
            // The order is already handled or cannot transition; retries need no action.
        }
    }
}
