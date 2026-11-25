<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckoutQuoteRequest;
use App\Http\Requests\Api\V1\CreateOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Jobs\SendOrderConfirmationWhatsApp;
use App\Jobs\SendOrderReceipt;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\CheckoutPricing;
use App\Services\Money;
use App\Services\OrderReceipt;
use App\Services\OrderReceiptDelivery;
use App\Services\OrderTracking;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderController extends Controller
{
    public function quote(CheckoutQuoteRequest $request, CheckoutPricing $pricing): JsonResponse
    {
        return response()->json(['data' => $pricing->publicQuote($pricing->calculate($request->validated()))]);
    }

    public function store(CreateOrderRequest $request, CheckoutPricing $pricing, OtpService $otp, OrderTracking $tracking): JsonResponse
    {
        $input = $request->validated();
        $user = $request->hasSession() ? $request->user('web') : null;
        $phone = $otp->normalizePhone($input['customer_phone']);
        if (! preg_match('/^03[0-9]{9}$/D', $phone)) {
            throw ValidationException::withMessages(['customer_phone' => 'Enter a valid Pakistani mobile number.']);
        }
        $input['customer_phone'] = $phone;
        $files = $request->file('design_reference_images', []);
        if ($request->hasFile('design_reference_image')) {
            $files = [0 => $request->file('design_reference_image')];
        }
        ksort($files);
        $fileDigests = array_map(fn ($file) => hash_file('sha256', $file->getRealPath()), $files);
        $key = $input['idempotency_key'] ?? null;
        $keyHash = $key ? hash_hmac('sha256', $key, (string) config('app.key')) : null;
        $payload = Arr::except($input, ['idempotency_key', 'verification_token', 'expected_total_amount', 'design_reference_image', 'design_reference_images']);
        $checkoutHash = hash('sha256', json_encode($this->canonical([$user?->id, $payload, $fileDigests]), JSON_THROW_ON_ERROR));
        if ($order = $this->previousOrder($keyHash, $checkoutHash)) {
            return $this->orderResponse($order, $pricing, $tracking, false);
        }

        $tailoring = $pricing->hasTailoring($input);
        $available = collect($pricing->paymentOptions($tailoring))->firstWhere('id', $input['payment_method']);
        if (! $available) {
            throw ValidationException::withMessages(['payment_method' => $pricing->unavailableMessage($tailoring)]);
        }

        $paths = [];
        $created = false;
        try {
            foreach ($files as $index => $file) {
                $path = $file->store('stitching_references', 'local');
                if (! $path) {
                    throw ValidationException::withMessages(['design_reference_images' => 'The image could not be saved. Please try again.']);
                }
                $paths[$index] = $path;
            }

            $order = DB::transaction(function () use ($input, $available, $pricing, $otp, $tracking, $user, $keyHash, $checkoutHash, $paths, &$created): Order {
                $created = false;
                if ($existing = $this->previousOrder($keyHash, $checkoutHash)) {
                    return $existing;
                }
                $totals = $pricing->calculate($input, lock: true);
                if (isset($input['expected_total_amount']) && Money::minor($input['expected_total_amount']) !== $totals['total']) {
                    throw ValidationException::withMessages(['expected_total_amount' => 'Your checkout total changed. Review the new quote before placing the order.']);
                }

                $codHoldHours = (int) config('checkout.cod_confirmation_hold_hours');
                $eligibleForHold = $codHoldHours > 0 && $input['payment_method'] === 'cod'
                    && $totals['total'] > 0 && $totals['reserved'] !== [] && $totals['bookings'] === []
                    && ! collect($totals['items'])->contains(fn ($line) => ! empty($line['custom_fitting']));

                // Create before consuming a credential; competing requests using the same key
                // cannot consume it twice. All inventory and OTP writes are in this transaction.
                $order = Order::create([
                    'order_number' => 'KF-'.now()->year.'-'.Str::ulid(),
                    'user_id' => $user?->id,
                    ...Arr::only($input, ['customer_name', 'customer_phone', 'customer_email', 'shipping_address', 'city', 'payment_method', 'notes']),
                    'subtotal' => Money::decimal($totals['subtotal']),
                    'shipping_fee' => Money::decimal($totals['shipping']),
                    'discount_amount' => Money::decimal($totals['discount']),
                    'total_amount' => Money::decimal($totals['total']),
                    'advance_due_minor' => $totals['advance'],
                    'idempotency_hash' => $keyHash,
                    'checkout_hash' => $keyHash ? $checkoutHash : null,
                    'coupon_id' => $totals['coupon']?->id,
                    'payment_status' => 'pending',
                    'order_status' => 'pending',
                    'confirmation_channel' => 'none',
                    'cod_confirmation_expires_at' => $eligibleForHold ? now()->addHours($codHoldHours) : null,
                ]);

                if ($available['requires_otp']) {
                    if (! $otp->consumeCheckoutVerification($input['customer_phone'], $input['verification_token'] ?? null)) {
                        throw ValidationException::withMessages(['verification_token' => 'Verify your phone for this checkout before placing your order.']);
                    }
                    $order->update(['phone_verified_at' => now()]);
                }
                foreach ($totals['reserved'] as $variantId => $quantity) {
                    $updated = ProductVariant::whereKey($variantId)->where('stock_quantity', '>=', $quantity)
                        ->where('is_active', true)->decrement('stock_quantity', $quantity);
                    if ($updated !== 1) {
                        throw ValidationException::withMessages(['items' => 'The available stock changed. Review your cart and try again.']);
                    }
                }
                if ($totals['coupon']) {
                    $coupon = $totals['coupon'];
                    $changed = $coupon->newQuery()->whereKey($coupon->id)->where(function ($query) {
                        $query->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses');
                    })->increment('used_count');
                    if ($changed !== 1) {
                        throw ValidationException::withMessages(['coupon_code' => 'This coupon is no longer available.']);
                    }
                }

                foreach ($totals['items'] as $line) {
                    $item = $order->items()->create([
                        'product_id' => $line['product']->id,
                        'variant_id' => $line['variant']->id,
                        'product_name' => $line['product']->name,
                        'size' => $line['variant']->size,
                        'color' => $line['variant']->color,
                        'quantity' => $line['quantity'],
                        'reserved_quantity' => $line['reserved'],
                        'unit_price' => Money::decimal($line['unit']),
                        'total_price' => Money::decimal($line['total']),
                    ]);
                    if ($line['custom_fitting']) {
                        $details = $line['custom_fitting'];
                        $order->stitchingRequests()->create([
                            'order_item_id' => $item->id,
                            'request_type' => 'custom_fitting',
                            'measurement_method' => 'manual',
                            ...Arr::only($details, ['neck_style', 'sleeve_style', 'trouser_style', 'special_instructions']),
                            'measurement_snapshot' => $this->measurement($details, $order),
                            'stitching_fee' => Money::decimal($line['fitting_fee']),
                            'status' => 'awaiting_fabric',
                            // Custom variants use stock held by the atelier; no fabric inbound leg.
                            'fabric_received_at' => now(),
                        ]);
                    }
                }
                foreach ($totals['bookings'] as $index => $booking) {
                    $details = $booking['details'];
                    $order->stitchingRequests()->create([
                        'stitching_service_id' => $booking['service']->id,
                        'request_type' => 'standalone_stitching',
                        'measurement_method' => $details['measurement_method'],
                        'fabric_delivery_method' => $details['fabric_delivery_method'] ?? 'courier_by_customer',
                        'design_reference_image' => $paths[$index] ?? null,
                        ...Arr::only($details, ['neck_style', 'sleeve_style', 'trouser_style', 'special_instructions']),
                        'measurement_snapshot' => ! empty($details['measurements']) ? $this->measurement($details['measurements'], $order) : null,
                        'stitching_fee' => Money::decimal($booking['fee']),
                        'status' => 'awaiting_fabric',
                    ]);
                }
                $tracking->issue($order);
                $created = true;

                return $order;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(array_values($paths));
            // The winner may commit after our initial check but before we obtain inventory locks.
            if ($existing = $this->previousOrder($keyHash, $checkoutHash)) {
                return $this->orderResponse($existing, $pricing, $tracking, false);
            }
            throw $exception;
        }

        if (! $created) {
            Storage::disk('local')->delete(array_values($paths));
        } else {
            if ($order->payment_method === 'cod') {
                rescue(fn () => SendOrderConfirmationWhatsApp::dispatch($order));
            }
            // Receipt providers are optional. An unavailable queue or mail service cannot undo checkout.
            rescue(function () use ($order): void {
                if (! in_array(config('queue.default'), ['sync', 'null'], true)
                    && app(OrderReceiptDelivery::class)->eligible($order)) {
                    SendOrderReceipt::dispatch($order->id);
                }
            }, report: false);
        }

        return $this->orderResponse($order, $pricing, $tracking, $created);
    }

    private function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($entry) => is_array($entry) ? $this->canonical($entry) : $entry, $value);
    }

    private function previousOrder(?string $keyHash, string $checkoutHash): ?Order
    {
        if (! $keyHash) {
            return null;
        }
        $order = Order::query()->where('idempotency_hash', $keyHash)->first();
        if ($order && ! hash_equals($order->checkout_hash ?? '', $checkoutHash)) {
            throw ValidationException::withMessages(['idempotency_key' => 'This checkout key already belongs to a different order.']);
        }

        return $order;
    }

    private function orderResponse(Order $order, CheckoutPricing $pricing, OrderTracking $tracking, bool $created): JsonResponse
    {
        $order->load(['items.stitchingRequest', 'stitchingRequests.stitchingService', 'stitchingRequests.measurement']);
        $data = (new OrderResource($order))->resolve();
        $data['tracking_token'] = $tracking->token($order);
        $data['receipt'] = app(OrderReceipt::class)->details($order);
        $total = Money::minor($order->total_amount);
        $advance = (int) $order->advance_due_minor;
        $data['balance_due'] = Money::publicAmount(max(0, $total - $advance));
        $data['payment_instructions'] = null;
        $data['next_payment_amount'] = null;
        $data['next_payment_type'] = null;
        $progress = $pricing->paymentProgress($order);
        if ($progress !== null && in_array($order->order_status, ['pending', 'confirmed', 'stitching', 'packed'], true)) {
            $advanceOutstanding = $progress['advance_remaining'];
            $nextAmount = $advanceOutstanding ?: $progress['balance_remaining'];
            if ($nextAmount > 0 && ($instructions = $pricing->bankInstructions()) !== null) {
                $data['payment_instructions'] = $instructions;
                $data['next_payment_amount'] = $nextAmount;
                $data['next_payment_type'] = $advanceOutstanding > 0 ? 'advance' : 'balance';
            }
        }

        return response()->json(['data' => $data], $created ? 201 : 200, ['Cache-Control' => 'no-store']);
    }

    private function measurement(array $data, Order $order): array
    {
        return [
            'profile_name' => 'Order '.$order->order_number,
            'unit' => $data['unit'] ?? 'inches',
            ...Arr::only($data, CheckoutQuoteRequest::MEASUREMENTS),
            'extra_notes' => $data['special_instructions'] ?? null,
        ];
    }

    public function userOrders(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection($request->user()
            ->orders()->with(['items.product:id,slug,status', 'items.stitchingRequest', 'stitchingRequests.stitchingService', 'stitchingRequests.measurement'])
            ->latest()->paginate(10));
    }
}
