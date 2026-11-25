<?php

namespace App\Services;

use App\Models\Order;
use LogicException;

class OrderTracking
{
    /**
     * Generate a secret once; retain only a nonce so safe checkout retries
     * can return the same key without persisting its raw value.
     */
    public function issue(Order $order): string
    {
        if (! $order->tracking_nonce) {
            $order->forceFill(['tracking_nonce' => bin2hex(random_bytes(32))])->save();
        }

        return $this->token($order);
    }

    public function token(Order $order): ?string
    {
        if (! $order->tracking_nonce) {
            return null;
        }

        $key = (string) config('app.key');
        if ($key === '') {
            throw new LogicException('An application key is required for tracking codes.');
        }

        return hash_hmac('sha256', "guest-order-tracking:v1:{$order->getKey()}:{$order->tracking_nonce}", $key);
    }

    public function lookup(string $number, string $token): ?Order
    {
        $order = Order::query()->where('order_number', strtoupper(trim($number)))->first();

        return $order && $this->matches($order, trim($token)) ? $order : null;
    }

    public function matches(Order $order, string $provided): bool
    {
        $expected = $this->token($order);

        return $expected !== null && hash_equals($expected, $provided);
    }
}
