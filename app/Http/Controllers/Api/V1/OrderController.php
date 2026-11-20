<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateOrderRequest;
use App\Http\Requests\Api\V1\TrackOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Jobs\SendOrderConfirmationWhatsApp;
use App\Models\Coupon;
use App\Models\CustomerMeasurement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\StitchingRequest;
use App\Models\StitchingService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Create a new order (Unified Guest & Authenticated checkout with atomic transaction).
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user('sanctum');

        $otpService = app(OtpService::class);
        $phoneVerifiedAt = null;

        // Enforce phone OTP verification for COD orders
        if ($validated['payment_method'] === 'cod') {
            $isVerified = $otpService->isPhoneVerified(
                $validated['customer_phone'],
                $request->input('verification_token')
            );

            if (! $isVerified) {
                throw ValidationException::withMessages([
                    'customer_phone' => ['Please verify your phone number with the 4-digit OTP before placing a Cash on Delivery order.'],
                ]);
            }

            $phoneVerifiedAt = now();
        }

        $order = DB::transaction(function () use ($validated, $request, $user, $phoneVerifiedAt) {
            $subtotal = 0.0;
            $itemsToCreate = [];
            $customFittingsToCreate = [];

            // 1. Process Ready-to-Wear Items
            if (! empty($validated['items'])) {
                foreach ($validated['items'] as $itemData) {
                    $variant = ProductVariant::where('id', $itemData['variant_id'])
                        ->where('product_id', $itemData['product_id'])
                        ->lockForUpdate()
                        ->with('product')
                        ->first();

                    if (! $variant) {
                        throw ValidationException::withMessages([
                            'items' => ["Product variant #{$itemData['variant_id']} was not found."],
                        ]);
                    }

                    $quantity = (int) $itemData['quantity'];

                    // Check stock
                    if ($variant->size !== 'Custom' && $variant->stock_quantity < $quantity) {
                        throw ValidationException::withMessages([
                            'items' => ["Variant SKU {$variant->sku} has only {$variant->stock_quantity} left in stock."],
                        ]);
                    }

                    // Decrement stock if standard size
                    if ($variant->size !== 'Custom') {
                        $variant->decrement('stock_quantity', $quantity);
                    }

                    // Calculate unit & total price
                    $unitPrice = $variant->price_override ?? $variant->product->sale_price ?? $variant->product->base_price;
                    $itemTotal = (float) $unitPrice * $quantity;
                    $subtotal += $itemTotal;

                    $itemsToCreate[] = [
                        'variant' => $variant,
                        'product_name' => $variant->product->name,
                        'size' => $variant->size,
                        'color' => $variant->color,
                        'quantity' => $quantity,
                        'unit_price' => (float) $unitPrice,
                        'total_price' => $itemTotal,
                        'custom_fitting' => $itemData['custom_fitting'] ?? null,
                    ];
                }
            }

            // 2. Process Standalone Stitching Request
            $standaloneData = $validated['standalone_stitching'] ?? null;
            $standaloneService = null;
            $standaloneFee = 0.0;
            $uploadedImagePath = null;

            if ($standaloneData) {
                $standaloneService = StitchingService::where('id', $standaloneData['stitching_service_id'])
                    ->where('is_active', true)
                    ->firstOrFail();

                $standaloneFee = (float) $standaloneService->base_price;
                $subtotal += $standaloneFee;

                // Handle file upload if present
                if ($request->hasFile('design_reference_image')) {
                    $path = $request->file('design_reference_image')->store('stitching_references', 'public');
                    $uploadedImagePath = asset('storage/' . $path);
                } elseif (! empty($standaloneData['design_reference_image']) && is_string($standaloneData['design_reference_image'])) {
                    $uploadedImagePath = $standaloneData['design_reference_image'];
                }
            }

            // 3. Process Coupon Discount
            $discountAmount = 0.0;
            if (! empty($validated['coupon_code'])) {
                $coupon = Coupon::where('code', strtoupper(trim($validated['coupon_code'])))->first();
                if ($coupon && $coupon->isValidForAmount($subtotal)) {
                    $discountAmount = $coupon->calculateDiscount($subtotal);
                    $coupon->increment('used_count');
                }
            }

            // 4. Calculate Shipping (Free above Rs. 5,000, otherwise Rs. 250)
            $shippingFee = ($subtotal >= 5000.0) ? 0.0 : 250.0;
            $totalAmount = max(0.0, round($subtotal - $discountAmount + $shippingFee, 2));

            // 5. Generate Sequential Order Number (KF-2026-XXXX)
            $year = date('Y');
            $latestOrder = Order::where('order_number', 'like', "KF-{$year}-%")->latest('id')->first();
            $nextSeq = 1;
            if ($latestOrder && preg_match('/KF-\d{4}-(\d+)/', $latestOrder->order_number, $matches)) {
                $nextSeq = ((int) $matches[1]) + 1;
            }
            $orderNumber = sprintf("KF-%s-%04d", $year, $nextSeq);

            // 6. Create Order Record
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $user?->id,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'customer_email' => $validated['customer_email'] ?? null,
                'shipping_address' => $validated['shipping_address'],
                'city' => $validated['city'],
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'pending',
                'order_status' => 'pending',
                'phone_verified_at' => $phoneVerifiedAt,
                'confirmation_channel' => 'none',
                'notes' => $validated['notes'] ?? null,
            ]);

            // 7. Create Order Items & Custom Fitting Requests
            foreach ($itemsToCreate as $item) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['variant']->product_id,
                    'variant_id' => $item['variant']->id,
                    'product_name' => $item['product_name'],
                    'size' => $item['size'],
                    'color' => $item['color'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                ]);

                if (! empty($item['custom_fitting'])) {
                    $cf = $item['custom_fitting'];

                    $measurement = CustomerMeasurement::create([
                        'user_id' => $user?->id,
                        'profile_name' => $validated['customer_name'] . ' - Order ' . $order->order_number,
                        'unit' => $cf['unit'] ?? 'inches',
                        'shirt_length' => $cf['shirt_length'] ?? null,
                        'shoulder' => $cf['shoulder'] ?? null,
                        'chest' => $cf['chest'] ?? null,
                        'waist' => $cf['waist'] ?? null,
                        'hips' => $cf['hips'] ?? null,
                        'sleeve_length' => $cf['sleeve_length'] ?? null,
                        'armhole' => $cf['armhole'] ?? null,
                        'daman' => $cf['daman'] ?? null,
                        'neck_front' => $cf['neck_front'] ?? null,
                        'neck_back' => $cf['neck_back'] ?? null,
                        'trouser_length' => $cf['trouser_length'] ?? null,
                        'trouser_waist' => $cf['trouser_waist'] ?? null,
                        'pancha' => $cf['pancha'] ?? null,
                        'extra_notes' => $cf['special_instructions'] ?? null,
                    ]);

                    StitchingRequest::create([
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'measurement_id' => $measurement->id,
                        'request_type' => 'custom_fitting',
                        'neck_style' => $cf['neck_style'] ?? null,
                        'sleeve_style' => $cf['sleeve_style'] ?? null,
                        'trouser_style' => $cf['trouser_style'] ?? null,
                        'special_instructions' => $cf['special_instructions'] ?? null,
                        'stitching_fee' => 300.00,
                        'status' => 'stitching',
                    ]);
                }
            }

            // 8. Create Standalone Stitching Request
            if ($standaloneData && $standaloneService) {
                $measurementId = null;
                if (! empty($standaloneData['measurements'])) {
                    $m = $standaloneData['measurements'];
                    $measurement = CustomerMeasurement::create([
                        'user_id' => $user?->id,
                        'profile_name' => $validated['customer_name'] . ' - Standalone ' . $order->order_number,
                        'unit' => $m['unit'] ?? 'inches',
                        'shirt_length' => $m['shirt_length'] ?? null,
                        'shoulder' => $m['shoulder'] ?? null,
                        'chest' => $m['chest'] ?? null,
                        'waist' => $m['waist'] ?? null,
                        'hips' => $m['hips'] ?? null,
                        'sleeve_length' => $m['sleeve_length'] ?? null,
                        'armhole' => $m['armhole'] ?? null,
                        'daman' => $m['daman'] ?? null,
                        'neck_front' => $m['neck_front'] ?? null,
                        'neck_back' => $m['neck_back'] ?? null,
                        'trouser_length' => $m['trouser_length'] ?? null,
                        'trouser_waist' => $m['trouser_waist'] ?? null,
                        'pancha' => $m['pancha'] ?? null,
                        'extra_notes' => $standaloneData['special_instructions'] ?? null,
                    ]);
                    $measurementId = $measurement->id;
                }

                StitchingRequest::create([
                    'order_id' => $order->id,
                    'stitching_service_id' => $standaloneService->id,
                    'measurement_id' => $measurementId,
                    'request_type' => 'standalone_stitching',
                    'fabric_delivery_method' => $standaloneData['fabric_delivery_method'] ?? 'courier_by_customer',
                    'design_reference_image' => $uploadedImagePath,
                    'neck_style' => $standaloneData['neck_style'] ?? null,
                    'sleeve_style' => $standaloneData['sleeve_style'] ?? null,
                    'trouser_style' => $standaloneData['trouser_style'] ?? null,
                    'special_instructions' => $standaloneData['special_instructions'] ?? null,
                    'stitching_fee' => $standaloneFee,
                    'status' => 'awaiting_fabric',
                ]);
            }

            return $order;
        });

        $order->load([
            'items.stitchingRequest',
            'stitchingRequests.stitchingService',
            'stitchingRequests.measurement',
        ]);

        // Dispatch Outbound WhatsApp 1 (Confirm) / 2 (Cancel) Confirmation Prompt
        SendOrderConfirmationWhatsApp::dispatch($order);

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Track an order by order_number and customer_phone.
     */
    public function track(TrackOrderRequest $request): JsonResponse
    {
        $orderNumber = trim($request->validated('order_number'));
        $phoneInput = trim($request->validated('customer_phone'));

        // Normalize phone: strip spaces and hyphens
        $cleanPhone = preg_replace('/[^\d+]/', '', $phoneInput);
        $localPhone = ltrim($cleanPhone, '+92');
        if (! str_starts_with($localPhone, '0')) {
            $localPhoneWithZero = '0' . $localPhone;
        } else {
            $localPhoneWithZero = $localPhone;
            $localPhone = ltrim($localPhone, '0');
        }

        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->where(function ($q) use ($phoneInput, $cleanPhone, $localPhone, $localPhoneWithZero) {
                $q->where('customer_phone', $phoneInput)
                    ->orWhere('customer_phone', $cleanPhone)
                    ->orWhere('customer_phone', 'like', "%{$localPhone}%")
                    ->orWhere('customer_phone', 'like', "%{$localPhoneWithZero}%");
            })
            ->with([
                'items.stitchingRequest',
                'stitchingRequests.stitchingService',
                'stitchingRequests.measurement',
            ])
            ->first();

        if (! $order) {
            return response()->json([
                'message' => 'Order not found. Please check your order number and phone number.',
            ], 404);
        }

        return (new OrderResource($order))->response();
    }

    /**
     * Display a paginated listing of orders for the authenticated user.
     */
    public function userOrders(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()
            ->orders()
            ->with([
                'items.stitchingRequest',
                'stitchingRequests.stitchingService',
                'stitchingRequests.measurement',
            ])
            ->latest()
            ->paginate(10);

        return OrderResource::collection($orders);
    }
}
