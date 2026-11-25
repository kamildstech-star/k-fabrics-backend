<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StitchingService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CheckoutPricing
{
    private const SHIRT_MEASUREMENTS = [
        'shirt_length' => 'shirt length',
        'shoulder' => 'shoulder width',
        'chest' => 'chest/bust',
        'waist' => 'shirt waist',
        'hips' => 'hips',
        'sleeve_length' => 'sleeve length',
    ];

    private const TROUSER_MEASUREMENTS = [
        'trouser_length' => 'trouser length',
        'trouser_waist' => 'trouser waist',
    ];

    public function paymentOptions(): array
    {
        $options = [];
        $mode = config('checkout.cod_mode');
        if ($mode === 'manual' || ($mode === 'otp' && app(OtpService::class)->isDeliveryConfigured())) {
            $options[] = ['id' => 'cod', 'label' => 'Cash on Delivery', 'requires_otp' => $mode === 'otp'];
        }
        if ($this->bankInstructions() !== null) {
            $options[] = ['id' => 'bank_transfer', 'label' => 'Bank Transfer', 'requires_otp' => false];
        }

        return $options;
    }

    public function bankInstructions(): ?string
    {
        $instructions = trim((string) config('checkout.bank_transfer_instructions'));

        return $instructions === '' ? null : $instructions;
    }

    public function calculate(array $cart, bool $lock = false): array
    {
        $provided = collect($cart['items'] ?? [])->values();
        $variants = $this->variants($provided, $lock);
        $products = $provided->isEmpty() ? collect() : Product::query()
            ->whereKey($provided->pluck('product_id')->unique()->values()->all())
            ->where('status', 'active')->orderBy('id')
            ->when($lock, fn ($q) => $q->lockForUpdate())->get()->keyBy('id');
        $items = [];
        $reserved = [];
        $bookings = [];
        $subtotal = 0;

        foreach ($provided as $index => $line) {
            $variant = $variants->get((int) $line['variant_id']);
            $product = $products->get((int) $line['product_id']);
            if (! $variant || ! $product || (int) $variant->product_id !== (int) $product->id) {
                throw ValidationException::withMessages(["items.$index" => 'This product option is no longer available.']);
            }
            $quantity = (int) $line['quantity'];
            $custom = ! empty($line['custom_fitting']);
            if (($variant->size === 'Custom') !== $custom) {
                throw ValidationException::withMessages(["items.$index.custom_fitting" => 'Select the custom size and provide fitting instructions together.']);
            }
            if ($custom) {
                $this->requireMeasurements($line['custom_fitting'], $product->piece_type !== '1-piece', "items.$index.custom_fitting");
            }
            if (! $custom) {
                $reserved[$variant->id] = ($reserved[$variant->id] ?? 0) + $quantity;
                if ($variant->stock_quantity < $reserved[$variant->id]) {
                    throw ValidationException::withMessages(["items.$index.quantity" => 'Not enough stock is available for this option.']);
                }
            }
            $fittingFee = $custom ? max(0, (int) config('checkout.custom_fitting_fee_minor')) : 0;
            $unit = Money::minor($variant->price_override ?? $product->sale_price ?? $product->base_price) + $fittingFee;
            if ($unit < 0) {
                throw ValidationException::withMessages(["items.$index" => 'This item is not currently available for checkout.']);
            }
            $items[] = [
                'variant' => $variant, 'product' => $product, 'quantity' => $quantity,
                'unit' => $unit, 'total' => $unit * $quantity, 'fitting_fee' => $fittingFee * $quantity,
                'custom_fitting' => $line['custom_fitting'] ?? null, 'reserved' => $custom ? 0 : $quantity,
            ];
            $subtotal += $unit * $quantity;
        }

        $standalone = collect($cart['standalone_stitchings'] ?? [])->values();
        $services = $standalone->isEmpty() ? collect() : StitchingService::query()
            ->whereKey($standalone->pluck('stitching_service_id')->unique()->values()->all())
            ->where('is_active', true)->orderBy('id')
            ->when($lock, fn ($q) => $q->lockForUpdate())->get()->keyBy('id');

        foreach ($standalone as $index => $details) {
            $service = $services->get((int) $details['stitching_service_id']);
            if (! $service || Money::minor($service->base_price) < 0) {
                throw ValidationException::withMessages(["standalone_stitchings.$index" => 'This stitching service is no longer available.']);
            }
            if ($details['measurement_method'] === 'manual') {
                // All offered standalone packages cover a shirt and trousers.
                $this->requireMeasurements($details['measurements'] ?? [], true, "standalone_stitchings.$index.measurements");
            } elseif (! empty($details['measurements'])) {
                throw ValidationException::withMessages([
                    "standalone_stitchings.$index.measurements" => 'Choose either manual measurements or a physical sample, not both.',
                ]);
            }
            $fee = Money::minor($service->base_price);
            $subtotal += $fee;
            $bookings[] = ['service' => $service, 'details' => $details, 'fee' => $fee];
        }

        $coupon = null;
        $discount = 0;
        if (! empty($cart['coupon_code'])) {
            $coupon = Coupon::query()->where('code', strtoupper(trim($cart['coupon_code'])))
                ->when($lock, fn ($q) => $q->lockForUpdate())->first();
            if (! $coupon || ! $coupon->is_active || $coupon->expires_at?->isPast()
                || ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses)
                || $subtotal < Money::minor($coupon->min_order_amount)) {
                throw ValidationException::withMessages(['coupon_code' => 'This coupon is no longer valid for this cart.']);
            }
            $value = Money::minor($coupon->discount_value);
            $discount = $coupon->discount_type === 'percentage'
                ? intdiv($subtotal * max(0, min(10000, $value)) + 5000, 10000)
                : max(0, min($subtotal, $value));
        }
        $shipping = $subtotal >= (int) config('checkout.free_shipping_at_minor') ? 0 : (int) config('checkout.shipping_fee_minor');
        $total = $subtotal - $discount + $shipping;
        if ($total > 9_999_999_999 || $subtotal > 9_999_999_999) {
            throw ValidationException::withMessages(['items' => 'This cart exceeds the maximum order total.']);
        }
        $tailoring = $bookings !== [] || collect($items)->contains(fn ($item) => ! empty($item['custom_fitting']));
        $advance = $tailoring ? intdiv($total + 1, 2) : 0;

        return compact('items', 'bookings', 'coupon', 'subtotal', 'discount', 'shipping', 'total', 'advance', 'reserved');
    }

    private function requireMeasurements(array $measurements, bool $withTrousers, string $path): void
    {
        $required = $withTrousers ? self::SHIRT_MEASUREMENTS + self::TROUSER_MEASUREMENTS : self::SHIRT_MEASUREMENTS;
        $missing = array_filter($required, fn ($label, $key) => ! isset($measurements[$key]), ARRAY_FILTER_USE_BOTH);
        if (! isset($measurements['unit'])) {
            $missing[] = 'measurement unit';
        }
        if ($missing !== []) {
            throw ValidationException::withMessages([$path => 'Enter the required measurements: '.implode(', ', $missing).'.']);
        }
    }

    private function variants(Collection $items, bool $lock): Collection
    {
        return $items->isEmpty() ? collect() : ProductVariant::query()
            ->whereKey($items->pluck('variant_id')->unique()->values()->all())
            ->where('is_active', true)->orderBy('id')
            ->when($lock, fn ($q) => $q->lockForUpdate())->get()->keyBy('id');
    }

    public function publicQuote(array $quote): array
    {
        return [
            'subtotal' => Money::publicAmount($quote['subtotal']),
            'discount_amount' => Money::publicAmount($quote['discount']),
            'shipping_fee' => Money::publicAmount($quote['shipping']),
            'total_amount' => Money::publicAmount($quote['total']),
            'advance_due' => Money::publicAmount($quote['advance']),
            'requires_deposit' => $quote['advance'] > 0,
            'payment_options' => $this->paymentOptions(),
            'items' => array_map(fn ($item) => [
                'product_id' => $item['product']->id, 'variant_id' => $item['variant']->id,
                'quantity' => $item['quantity'], 'unit_price' => Money::publicAmount($item['unit']),
                'total_price' => Money::publicAmount($item['total']),
            ], $quote['items']),
            'standalone_stitchings' => array_map(fn ($item) => [
                'stitching_service_id' => $item['service']->id,
                'stitching_fee' => Money::publicAmount($item['fee']),
            ], $quote['bookings']),
        ];
    }
}
