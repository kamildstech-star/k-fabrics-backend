<?php

namespace App\Services;

use App\Models\OtpVerification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OtpService
{
    /**
     * Normalize Pakistani phone number to standard 03XXXXXXXXX format.
     */
    public function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($cleaned, '92') && strlen($cleaned) === 12) {
            return '0' . substr($cleaned, 2);
        }

        if (strlen($cleaned) === 10 && str_starts_with($cleaned, '3')) {
            return '0' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Generate and dispatch a 4-digit OTP.
     */
    public function sendOtp(string $phone, string $channel = 'whatsapp', string $purpose = 'checkout_cod'): array
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if (strlen($normalizedPhone) !== 11 || ! str_starts_with($normalizedPhone, '03')) {
            throw ValidationException::withMessages([
                'phone' => ['Please enter a valid 11-digit Pakistani mobile number (e.g. 03001234567).'],
            ]);
        }

        // Rate limit: max 3 requests per 15 minutes
        $recentCount = OtpVerification::where('phone', $normalizedPhone)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        if ($recentCount >= 3) {
            throw ValidationException::withMessages([
                'phone' => ['Too many OTP requests. Please wait 15 minutes before trying again.'],
            ]);
        }

        // Generate 4-digit numeric code
        $code = (string) random_int(1000, 9999);

        // Store verification record (TTL: 5 minutes)
        $otp = OtpVerification::create([
            'phone' => $normalizedPhone,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'max_attempts' => 5,
            'verified_at' => null,
            'expires_at' => now()->addMinutes(5),
        ]);

        // Dispatch via driver / logger
        $this->dispatchNotification($normalizedPhone, $code, $channel);

        return [
            'success' => true,
            'message' => "4-digit verification code sent via {$channel}.",
            'phone' => $normalizedPhone,
            'channel' => $channel,
            'expires_in_seconds' => 300,
            'cooldown_seconds' => 60,
            // Include debug code in testing / local for automated tests and dev convenience
            'debug_code' => app()->environment('local', 'testing') ? $code : null,
        ];
    }

    /**
     * Verify submitted 4-digit code.
     */
    public function verifyOtp(string $phone, string $code, string $purpose = 'checkout_cod'): array
    {
        $normalizedPhone = $this->normalizePhone($phone);
        $code = trim($code);

        $otp = OtpVerification::where('phone', $normalizedPhone)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (! $otp || $otp->isExpired()) {
            throw ValidationException::withMessages([
                'code' => ['Verification code has expired or was not requested. Please request a new OTP.'],
            ]);
        }

        if ($otp->hasExceededAttempts()) {
            throw ValidationException::withMessages([
                'code' => ['Maximum verification attempts exceeded. Please request a new code.'],
            ]);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            $remaining = $otp->max_attempts - $otp->attempts;

            throw ValidationException::withMessages([
                'code' => ["Invalid verification code. {$remaining} attempts remaining."],
            ]);
        }

        // Mark as verified
        $otp->update(['verified_at' => now()]);

        // Generate token and cache verification for 30 minutes
        $verificationToken = hash_hmac('sha256', "{$normalizedPhone}:{$otp->id}:{$otp->verified_at->timestamp}", config('app.key'));
        Cache::put("cod_verified:{$normalizedPhone}", $verificationToken, now()->addMinutes(30));

        return [
            'success' => true,
            'message' => 'Phone number successfully verified.',
            'phone' => $normalizedPhone,
            'verification_token' => $verificationToken,
            'verified_at' => $otp->verified_at->toISOString(),
        ];
    }

    /**
     * Check if phone has been recently verified.
     */
    public function isPhoneVerified(string $phone, ?string $token = null): bool
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if ($token) {
            $cached = Cache::get("cod_verified:{$normalizedPhone}");
            if ($cached && hash_equals($cached, $token)) {
                return true;
            }
        }

        return OtpVerification::where('phone', $normalizedPhone)
            ->whereNotNull('verified_at')
            ->where('verified_at', '>=', now()->subMinutes(30))
            ->exists();
    }

    /**
     * Abstraction to send SMS / WhatsApp.
     */
    protected function dispatchNotification(string $phone, string $code, string $channel): void
    {
        $message = "Your K. Fabrics verification code is {$code}. Valid for 5 minutes. Do not share this code.";

        Log::info("[OTP Notification] Channel: {$channel} | To: {$phone} | Message: {$message}");

        // In production, invoke Twilio / WhatsApp Cloud API / Zong SMS Gateway here.
    }
}
