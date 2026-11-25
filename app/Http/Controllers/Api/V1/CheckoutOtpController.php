<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutOtpController extends Controller
{
    /**
     * Dispatch 4-digit OTP to customer phone.
     */
    public function sendOtp(Request $request, OtpService $otpService): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
            'channel' => ['nullable', 'string', 'in:sms'],
        ]);

        $channel = $validated['channel'] ?? 'sms';
        $result = $otpService->sendOtp($validated['phone'], $channel);

        return response()->json($result);
    }

    /**
     * Verify customer submitted 4-digit code.
     */
    public function verifyOtp(Request $request, OtpService $otpService): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
            'code' => ['required', 'string', 'regex:/^[0-9]{4}$/D'],
        ]);

        $result = $otpService->verifyOtp($validated['phone'], $validated['code']);

        return response()->json($result);
    }
}
