<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnCase;
use App\Services\OrderTracking;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderReturnController extends Controller
{
    public function store(Request $request, OrderTracking $tracking): JsonResponse
    {
        $input = $request->validate($this->proof() + [
            'type' => ['required', Rule::in(array_keys(ReturnCase::TYPES))],
            'reason' => ['required', Rule::in(array_keys(ReturnCase::REASONS))],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $order = $this->verifiedOrder($request, $tracking, $input);
        if (! $order) {
            return $this->notFound();
        }

        try {
            [$case, $created] = DB::transaction(function () use ($order, $input): array {
                $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
                $case = ReturnCase::query()->where('order_id', $locked->getKey())->first();
                if ($case) {
                    return [$case, false];
                }
                if ($locked->order_status !== 'delivered') {
                    return [null, false];
                }

                return [ReturnCase::create([
                    'order_id' => $locked->getKey(),
                    'reference' => 'KR-'.Str::ulid(),
                    'type' => $input['type'],
                    'reason' => $input['reason'],
                    'description' => $input['description'],
                ]), true];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // Repeated or simultaneous submissions resolve to the same case.
            $case = ReturnCase::query()->where('order_id', $order->getKey())->first();
            if (! $case) {
                throw $exception;
            }
            $created = false;
        }

        if (! $case) {
            return $this->reply(['message' => 'The order must be marked delivered before you can submit a return or exchange request online.'], 409);
        }

        return $this->reply(['data' => $this->summary($case)], $created ? 201 : 200);
    }

    public function status(Request $request, OrderTracking $tracking): JsonResponse
    {
        $order = $this->verifiedOrder($request, $tracking, $request->validate($this->proof()));
        if (! $order) {
            return $this->notFound();
        }
        $case = ReturnCase::query()->where('order_id', $order->getKey())->first();

        return $this->reply(['data' => $case ? $this->summary($case) : null]);
    }

    private function proof(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:50'],
            'tracking_token' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function verifiedOrder(Request $request, OrderTracking $tracking, array $input): ?Order
    {
        $number = strtoupper(trim($input['order_number']));
        if ($user = $request->user('web')) {
            $owned = Order::query()->where('user_id', $user->getAuthIdentifier())->where('order_number', $number)->first();
            if ($owned) {
                return $owned;
            }
        }
        if ($token = $input['tracking_token'] ?? null) {
            return $tracking->lookup($number, $token);
        }

        return null;
    }

    private function summary(ReturnCase $case): array
    {
        return [
            'reference' => $case->reference,
            'type' => $case->type,
            'status' => $case->status,
            'submitted_at' => $case->created_at?->toISOString(),
            'updated_at' => $case->updated_at?->toISOString(),
        ];
    }

    private function notFound(): JsonResponse
    {
        return $this->reply(['message' => 'Order not found. Check the order number and use its private tracking code or the account that placed it.'], 404);
    }

    private function reply(array $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status)->header('Cache-Control', 'private, no-store');
    }
}
