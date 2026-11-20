<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle incoming WhatsApp replies ("1" for confirm, "2" for cancel).
     */
    public function handle(Request $request, OtpService $otpService): JsonResponse
    {
        // Meta WhatsApp Webhook verification handshake (hub.challenge)
        if ($request->has('hub_challenge')) {
            return response()->json($request->input('hub_challenge'));
        }

        // Support direct JSON payload, Twilio webhook, and Meta Cloud API formats
        $rawPhone = $request->input('phone')
            ?? $request->input('From')
            ?? data_get($request->all(), 'entry.0.changes.0.value.messages.0.from');

        $body = trim((string) (
            $request->input('message')
            ?? $request->input('text')
            ?? $request->input('Body')
            ?? data_get($request->all(), 'entry.0.changes.0.value.messages.0.text.body')
        ));

        if (! $rawPhone || $body === '') {
            return response()->json(['success' => false, 'message' => 'Missing phone or message body.'], 400);
        }

        $normalizedPhone = $otpService->normalizePhone($rawPhone);
        $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);

        // Find customer's latest pending order
        $order = Order::where(function ($q) use ($normalizedPhone, $rawPhone, $cleanPhone) {
            $q->where('customer_phone', $normalizedPhone)
                ->orWhere('customer_phone', $rawPhone)
                ->orWhere('customer_phone', $cleanPhone)
                ->orWhere('customer_phone', 'like', "%{$normalizedPhone}%");
        })
            ->where('order_status', 'pending')
            ->with('items')
            ->latest()
            ->first();

        if (! $order) {
            Log::info("[WhatsApp Bot] No pending order found for {$rawPhone}");

            return response()->json([
                'success' => false,
                'message' => 'No pending order found for this number.',
                'reply' => 'Aapka koi pending order system me mojood nahi hai.',
            ], 404);
        }

        $lowerBody = strtolower($body);

        // Option 1: Confirm order
        if ($body === '1' || in_array($lowerBody, ['confirm', 'yes', 'ha', 'haan', 'ok'])) {
            $order->update([
                'order_status' => 'confirmed',
                'confirmation_channel' => 'whatsapp_bot',
                'confirmed_at' => now(),
            ]);

            $reply = "Shukriya! Aapka order #{$order->order_number} confirm ho chuka hai aur workshop me tayyari ke liye bhej dia gaya hai.";

            Log::info("[WhatsApp Bot] Order #{$order->order_number} confirmed via WhatsApp bot from {$rawPhone}");

            return response()->json([
                'success' => true,
                'order_number' => $order->order_number,
                'order_status' => 'confirmed',
                'reply' => $reply,
            ]);
        }

        // Option 2: Cancel order and restore variant inventory
        if ($body === '2' || in_array($lowerBody, ['cancel', 'no', 'nahi', 'cancel order'])) {
            DB::transaction(function () use ($order) {
                $order->update([
                    'order_status' => 'cancelled',
                    'confirmation_channel' => 'whatsapp_bot',
                    'cancelled_reason' => 'Cancelled via WhatsApp reply 2',
                ]);

                // Restore product variant stock
                foreach ($order->items as $item) {
                    if ($item->variant_id) {
                        ProductVariant::where('id', $item->variant_id)->increment('stock_quantity', $item->quantity);
                    }
                }
            });

            $reply = "Aapka order #{$order->order_number} cancel kar dia gaya hai. Agar aap dobara order karna chahein to hamari website visit karein.";

            Log::info("[WhatsApp Bot] Order #{$order->order_number} cancelled and stock restored via WhatsApp bot from {$rawPhone}");

            return response()->json([
                'success' => true,
                'order_number' => $order->order_number,
                'order_status' => 'cancelled',
                'reply' => $reply,
            ]);
        }

        // Unrecognized reply
        return response()->json([
            'success' => false,
            'message' => 'Unrecognized option.',
            'reply' => "Order #{$order->order_number} confirm karne ke liye sirf 1 bhejein, ya cancel karne ke liye 2 bhejein.",
        ]);
    }
}
