<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\ProductVariant;
use App\Models\StitchingRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderLifecycle
{
    public const COURIER_PATTERN = "/^(?=.*\p{L})[\p{L}\p{N}][\p{L}\p{N} .&,'()\/+\-]{1,99}$/uD";

    public const TRACKING_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._\/-]{2,99}$/D';

    public const COD_EXPIRY_REASON = 'COD confirmation not received before the stock hold expired.';

    public function received(Order $order): int
    {
        return (int) $order->payments()->where('kind', 'receipt')->sum('amount_minor')
            - (int) $order->payments()->where('kind', 'refund')->sum('amount_minor');
    }

    public function confirm(Order $order, string $channel): Order
    {
        return DB::transaction(function () use ($order, $channel): Order {
            $locked = $this->lock($order);
            if ($locked->order_status === 'confirmed') {
                return $locked;
            }
            if ($locked->order_status !== 'pending') {
                $this->invalid('order_status', 'Only a pending order can be confirmed.');
            }
            if ($locked->payment_method === 'bank_transfer'
                && $this->received($locked) < ($locked->advance_due_minor ?: Money::minor($locked->total_amount))) {
                $this->invalid('payment_status', 'Record the verified payment before confirming a bank-transfer order.');
            }
            $locked->update([
                'order_status' => 'confirmed', 'confirmed_at' => now(), 'confirmation_channel' => $channel,
                'cod_confirmation_expires_at' => null,
            ]);

            return $locked;
        }, 3);
    }

    public function cancel(Order $order, string $reason, string $channel): Order
    {
        return DB::transaction(function () use ($order, $reason, $channel): Order {
            $locked = $this->lock($order);
            if ($locked->order_status === 'cancelled') {
                return $locked;
            }
            if (! in_array($locked->order_status, ['pending', 'confirmed', 'packed'], true)
                || $locked->stitchingRequests()->where('status', '<>', 'awaiting_fabric')->exists()) {
                $this->invalid('order_status', 'This order cannot be automatically cancelled after tailoring or dispatch has started.');
            }
            if ($channel === 'whatsapp_bot' && $locked->order_status !== 'pending') {
                $this->invalid('order_status', 'This order is no longer awaiting WhatsApp confirmation.');
            }
            return $this->cancelAndRelease($locked, $reason);
        }, 3);
    }

    public function expireDueUnconfirmedCod(int $limit = 100): int
    {
        if ((int) config('checkout.cod_confirmation_hold_hours') <= 0) {
            return 0;
        }
        $cutoff = now();
        $candidates = Order::query()->pendingCodConfirmation()
            ->where('payment_status', 'pending')
            ->where('cod_confirmation_expires_at', '<=', $cutoff)
            ->whereDoesntHave('payments')
            ->whereDoesntHave('stitchingRequests')
            ->orderBy('cod_confirmation_expires_at')->orderBy('id')
            ->limit(max(1, min(1000, $limit)))->get(['id']);

        $expired = 0;
        foreach ($candidates as $candidate) {
            $released = DB::transaction(function () use ($candidate, $cutoff): bool {
                // Re-evaluate after obtaining the same row lock used by confirmation
                // and payment recording. The list above is only an optimization.
                $locked = Order::query()->lockForUpdate()->find($candidate->id);
                if (! $locked || $locked->payment_method !== 'cod' || $locked->payment_status !== 'pending'
                    || $locked->order_status !== 'pending' || $locked->confirmed_at !== null
                    || $locked->confirmation_channel !== 'none'
                    || ! $locked->cod_confirmation_expires_at || $locked->cod_confirmation_expires_at->gt($cutoff)
                    || $locked->payments()->exists() || $locked->stitchingRequests()->exists()) {
                    return false;
                }
                $this->cancelAndRelease($locked, self::COD_EXPIRY_REASON);

                return true;
            }, 3);
            $expired += (int) $released;
        }

        return $expired;
    }

    private function cancelAndRelease(Order $locked, string $reason): Order
    {
        $locked->update([
            'order_status' => 'cancelled', 'cancelled_reason' => $reason, 'cod_confirmation_expires_at' => null,
        ]);
        $variants = [];
        foreach ($locked->items as $item) {
            $reserved = $item->reserved_quantity ?? ($item->size === 'Custom' ? 0 : (int) $item->quantity);
            if ($item->variant_id && $reserved > 0) {
                $variants[$item->variant_id] = ($variants[$item->variant_id] ?? 0) + $reserved;
            }
        }
        ksort($variants);
        foreach ($variants as $variantId => $quantity) {
            ProductVariant::whereKey($variantId)->increment('stock_quantity', $quantity);
        }
        if ($locked->coupon_id) {
            Coupon::whereKey($locked->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
        }

        return $locked;
    }

    public function updateDetails(Order $order, array $details): Order
    {
        return DB::transaction(function () use ($order, $details): Order {
            $locked = $this->lock($order);
            $allowed = Arr::only($details, [
                'customer_name', 'customer_email', 'city', 'shipping_address', 'notes', 'courier_name', 'tracking_number',
            ]);
            $hasShipped = in_array($locked->order_status, ['shipped', 'delivered', 'returned'], true);
            foreach (['courier_name', 'tracking_number'] as $field) {
                if (! array_key_exists($field, $allowed)) {
                    continue;
                }
                if (is_string($allowed[$field])) {
                    $value = trim($allowed[$field]);
                    $allowed[$field] = $value === '' ? null : $value;
                }
                // Historical orders can retain missing shipment data, but operators
                // cannot clear valid shipment data once an order has been dispatched.
                $previous = $locked->getAttribute($field);
                if ($hasShipped && $allowed[$field] === $previous) {
                    continue;
                }
                $required = $hasShipped && filled($previous);
                $this->validateShipmentField($field, $allowed[$field], $required);
            }
            $locked->update($allowed);

            return $locked;
        }, 3);
    }

    private function validateShipmentField(string $field, mixed $value, bool $required): void
    {
        $pattern = $field === 'courier_name' ? self::COURIER_PATTERN : self::TRACKING_PATTERN;
        $label = $field === 'courier_name' ? 'courier name' : 'tracking number';
        Validator::make([$field => $value], [
            $field => [$required ? 'required' : 'nullable', 'string', 'max:100', 'regex:'.$pattern],
        ], [
            "$field.required" => "Save the $label before dispatch.",
            "$field.regex" => "Enter a valid $label before saving or dispatching.",
        ])->validate();
    }

    public function advanceOrder(Order $order, string $status): Order
    {
        return DB::transaction(function () use ($order, $status): Order {
            $locked = $this->lock($order);
            if ($locked->order_status === $status) {
                return $locked;
            }
            $allowed = [
                'confirmed' => ['stitching', 'packed'],
                'stitching' => ['packed'],
                'packed' => ['shipped'],
                'shipped' => ['delivered', 'returned'],
                'delivered' => ['returned'],
            ];
            if (! in_array($status, $allowed[$locked->order_status] ?? [], true)) {
                $this->invalid('order_status', 'This order cannot move directly to that status.');
            }
            if ($status === 'stitching' && (! $locked->stitchingRequests()->exists()
                || $this->received($locked) < $locked->advance_due_minor)) {
                $this->invalid('order_status', 'Tailoring requires a stitching booking and the verified deposit.');
            }
            if ($status === 'packed' && $locked->stitchingRequests()->where('status', '<>', 'ready')->exists()) {
                $this->invalid('order_status', 'Finish all tailoring bookings before packing.');
            }
            if ($status === 'shipped' && $locked->payment_method === 'bank_transfer'
                && $this->received($locked) < Money::minor($locked->total_amount)) {
                $this->invalid('order_status', 'Record the verified remaining balance before dispatch.');
            }
            if ($status === 'shipped') {
                $this->validateShipmentField('courier_name', $locked->courier_name, true);
                $this->validateShipmentField('tracking_number', $locked->tracking_number, true);
            }
            $locked->update(['order_status' => $status]);

            return $locked;
        }, 3);
    }

    public function updateStitching(StitchingRequest $request, array $details): StitchingRequest
    {
        return DB::transaction(function () use ($request, $details): StitchingRequest {
            $order = Order::query()->lockForUpdate()->findOrFail($request->order_id);
            $locked = StitchingRequest::query()->lockForUpdate()->findOrFail($request->id);
            $status = $details['status'] ?? $locked->status;
            $fabric = array_key_exists('fabric_received_at', $details) ? $details['fabric_received_at'] : $locked->fabric_received_at;
            $sample = array_key_exists('sample_received_at', $details) ? $details['sample_received_at'] : $locked->sample_received_at;
            $stages = ['awaiting_fabric' => 'cutting', 'cutting' => 'stitching', 'stitching' => 'finishing', 'finishing' => 'ready'];
            if (in_array($order->order_status, ['cancelled', 'returned'], true)) {
                $this->invalid('status', 'This order is no longer active.');
            }
            if ($locked->measurement_method === 'sample' && $status !== 'awaiting_fabric' && ! $sample) {
                $this->invalid('sample_received_at', 'Record receipt of the sample garments before cutting can begin.');
            }
            if ($locked->status !== $status) {
                if (($stages[$locked->status] ?? null) !== $status) {
                    $this->invalid('status', 'Move the job to its next workshop stage.');
                }
                if (! in_array($order->order_status, ['confirmed', 'stitching'], true)
                    || ! $fabric || $this->received($order) < $order->advance_due_minor) {
                    $this->invalid('status', 'Confirm the order, receive the fabric and verify the deposit before starting workshop work.');
                }
                if ($order->order_status === 'confirmed') {
                    $order->update(['order_status' => 'stitching']);
                }
            }

            $locked->update(array_intersect_key($details, array_flip([
                'status', 'fabric_received_at', 'sample_received_at', 'fabric_delivery_method', 'neck_style', 'sleeve_style', 'trouser_style', 'special_instructions',
            ])));

            return $locked;
        }, 3);
    }

    public function recordPayment(Order $order, string $kind, string $amount, string $reference, CarbonInterface $receivedAt, ?User $operator): OrderPayment
    {
        return DB::transaction(function () use ($order, $kind, $amount, $reference, $receivedAt, $operator): OrderPayment {
            $locked = $this->lock($order);
            if (! in_array($kind, ['receipt', 'refund'], true) || ! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $amount)) {
                $this->invalid('amount', 'Enter a valid PKR amount.');
            }
            $amountMinor = Money::minor($amount);
            $reference = trim($reference);
            if ($reference === '' || mb_strlen($reference) > 120) {
                $this->invalid('reference', 'Provide the payment processor, bank, or manual cash reference.');
            }
            if ($payment = OrderPayment::query()->where('reference', $reference)->first()) {
                if ((int) $payment->order_id === (int) $locked->id && $payment->amount_minor === $amountMinor && $payment->kind === $kind) {
                    return $payment;
                }
                $this->invalid('reference', 'That reference is already used by another payment.');
            }
            $current = $this->received($locked);
            $total = Money::minor($locked->total_amount);
            if ($amountMinor <= 0 || $receivedAt->isFuture()) {
                $this->invalid('amount', 'Record a positive amount and an actual transaction date.');
            }
            if ($kind === 'receipt' && (in_array($locked->order_status, ['cancelled', 'returned'], true) || $current + $amountMinor > $total)) {
                $this->invalid('amount', 'The amount exceeds the balance or the order is no longer payable.');
            }
            if ($kind === 'refund' && (! in_array($locked->order_status, ['cancelled', 'returned'], true) || $amountMinor > $current)) {
                $this->invalid('amount', 'Refunds require a cancelled or returned order and cannot exceed verified receipts.');
            }
            $payment = $locked->payments()->create([
                'recorded_by' => $operator?->id, 'kind' => $kind, 'amount_minor' => $amountMinor,
                'reference' => $reference, 'received_at' => $receivedAt,
            ]);
            $net = $current + ($kind === 'receipt' ? $amountMinor : -$amountMinor);
            $locked->update([
                'payment_status' => match (true) {
                    $kind === 'refund' && $net === 0 => 'refunded',
                    $net >= $total => 'paid',
                    default => 'pending',
                },
                // Any verified receipt moves the order to staff review rather
                // than automatic expiry, even if it is only a partial amount.
                ...($kind === 'receipt' ? ['cod_confirmation_expires_at' => null] : []),
            ]);

            return $payment;
        }, 3);
    }

    private function lock(Order $order): Order
    {
        return Order::query()->lockForUpdate()->findOrFail($order->id);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
