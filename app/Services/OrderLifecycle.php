<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\ProductVariant;
use App\Models\StitchingRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderLifecycle
{
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
            $locked->update(['order_status' => 'confirmed', 'confirmed_at' => now(), 'confirmation_channel' => $channel]);

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
            $locked->update(['order_status' => 'cancelled', 'cancelled_reason' => $reason]);
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
        }, 3);
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
            $stages = ['awaiting_fabric' => 'cutting', 'cutting' => 'stitching', 'stitching' => 'finishing', 'finishing' => 'ready'];
            if (in_array($order->order_status, ['cancelled', 'returned'], true)) {
                $this->invalid('status', 'This order is no longer active.');
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
                'status', 'fabric_received_at', 'fabric_delivery_method', 'neck_style', 'sleeve_style', 'trouser_style', 'special_instructions',
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
            $locked->update(['payment_status' => match (true) {
                $kind === 'refund' && $net === 0 => 'refunded',
                $net >= $total => 'paid',
                default => 'pending',
            }]);

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
