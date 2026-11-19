<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ValidateCouponRequest;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;

class CouponController extends Controller
{
    /**
     * Validate a coupon code against a subtotal.
     */
    public function validateCoupon(ValidateCouponRequest $request): JsonResponse
    {
        $code = trim(strtoupper($request->validated('code')));
        $subtotal = (float) $request->validated('subtotal');

        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Coupon code not found.',
            ], 422);
        }

        if (! $coupon->is_active) {
            return response()->json([
                'valid' => false,
                'message' => 'This coupon is no longer active.',
            ], 422);
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return response()->json([
                'valid' => false,
                'message' => 'This coupon has expired.',
            ], 422);
        }

        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            return response()->json([
                'valid' => false,
                'message' => 'This coupon has reached its maximum usage limit.',
            ], 422);
        }

        if ($subtotal < (float) $coupon->min_order_amount) {
            return response()->json([
                'valid' => false,
                'message' => sprintf(
                    'Minimum order amount of Rs. %s required for this coupon.',
                    number_format($coupon->min_order_amount, 2)
                ),
            ], 422);
        }

        $discount = $coupon->calculateDiscount($subtotal);

        return response()->json([
            'valid' => true,
            'discount_amount' => $discount,
            'coupon' => [
                'code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'discount_value' => (float) $coupon->discount_value,
                'min_order_amount' => (float) $coupon->min_order_amount,
            ],
        ]);
    }
}
