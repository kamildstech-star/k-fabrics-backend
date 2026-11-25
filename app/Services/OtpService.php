<?php

namespace App\Services;

use App\Models\OtpVerification;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($cleaned, '92') && strlen($cleaned) === 12) {
            return '0'.substr($cleaned, 2);
        }

        if (strlen($cleaned) === 10 && str_starts_with($cleaned, '3')) {
            return '0'.$cleaned;
        }

        return $cleaned;
    }

    public function isDeliveryConfigured(): bool
    {
        return app()->environment('testing') || $this->hasSmsCredentials();
    }

    public function sendOtp(string $phone, string $channel = 'sms'): array
    {
        $this->ensureOtpAvailable();
        $phone = $this->validatedPhone($phone);
        if ($channel !== 'sms') {
            throw ValidationException::withMessages(['channel' => ['Only SMS verification is available.']]);
        }

        try {
            return Cache::lock('otp:'.$this->phoneKey($phone), 15)->block(5, function () use ($phone) {
                $recent = OtpVerification::query()->where('phone', $this->phoneKey($phone))->where('purpose', 'checkout_cod');
                if ((clone $recent)->where('created_at', '>=', now()->subMinute())->exists()
                    || (clone $recent)->where('created_at', '>=', now()->subMinutes(15))->count() >= 3) {
                    throw ValidationException::withMessages(['phone' => ['Please wait before requesting another code.']]);
                }

                $code = (string) random_int(1000, 9999);
                $otp = OtpVerification::create([
                    'phone' => $this->phoneKey($phone),
                    'purpose' => 'checkout_cod',
                    'code_hash' => Hash::make($code),
                    'max_attempts' => 5,
                    'expires_at' => now()->addMinutes(5),
                ]);

                if ($this->hasSmsCredentials() && ! $this->deliver($phone, $code)) {
                    $otp->update(['expires_at' => now()]);
                    abort(503, 'SMS verification is temporarily unavailable. Please try again later.');
                }

                $recent->where('id', '!=', $otp->id)->whereNull('verified_at')->update(['expires_at' => now()]);

                $result = [
                    'success' => true,
                    'message' => 'Verification code requested via SMS.',
                    'phone' => $phone,
                    'channel' => 'sms',
                    'expires_in_seconds' => 300,
                    'cooldown_seconds' => 60,
                ];
                if (app()->environment('testing') && ! $this->hasSmsCredentials()) {
                    $result['debug_code'] = $code;
                }

                return $result;
            });
        } catch (LockTimeoutException) {
            abort(429, 'Please wait before requesting another code.');
        }
    }

    public function verifyOtp(string $phone, string $code): array
    {
        $this->ensureOtpAvailable();
        $phone = $this->validatedPhone($phone);

        try {
            return Cache::lock('otp:'.$this->phoneKey($phone), 15)->block(5, function () use ($phone, $code) {
                $otp = OtpVerification::where('phone', $this->phoneKey($phone))
                    ->where('purpose', 'checkout_cod')
                    ->latest('id')->first();

                if (! $otp || $otp->isExpired() || $otp->isVerified() || $otp->hasExceededAttempts()) {
                    throw ValidationException::withMessages(['code' => ['This code is no longer valid. Request a new code.']]);
                }
                if (! Hash::check(trim($code), $otp->code_hash)) {
                    $otp->increment('attempts');
                    throw ValidationException::withMessages(['code' => ['Invalid verification code.']]);
                }

                $token = bin2hex(random_bytes(32));
                $otp->forceFill([
                    'verified_at' => now(),
                    'verification_token_hash' => hash('sha256', $token),
                ])->save();

                return [
                    'success' => true,
                    'message' => 'Phone number successfully verified.',
                    'phone' => $phone,
                    'verification_token' => $token,
                    'verified_at' => $otp->verified_at->toISOString(),
                ];
            });
        } catch (LockTimeoutException) {
            abort(429, 'Please wait before verifying another code.');
        }
    }

    /** Consume exactly one matching OTP. Call inside the order transaction. */
    public function consumeCheckoutVerification(string $phone, ?string $token): bool
    {
        if (! $token || ! preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return false;
        }

        return OtpVerification::where('phone', $this->phoneKey($this->normalizePhone($phone)))
            ->where('purpose', 'checkout_cod')
            ->where('verification_token_hash', hash('sha256', $token))
            ->whereNotNull('verified_at')
            ->where('verified_at', '>=', now()->subMinutes(30))
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]) === 1;
    }

    private function phoneKey(string $phone): string
    {
        return hash_hmac('sha256', 'checkout-otp-phone:v1:'.$phone, (string) config('app.key'));
    }

    private function ensureOtpAvailable(): void
    {
        abort_unless(config('checkout.cod_mode') === 'otp' && $this->isDeliveryConfigured() && filled(config('app.key')), 503, 'SMS verification is not available.');
    }

    private function validatedPhone(string $phone): string
    {
        $normalized = $this->normalizePhone($phone);
        if (! preg_match('/^03[0-9]{9}$/D', $normalized)) {
            throw ValidationException::withMessages(['phone' => ['Please enter a valid Pakistani mobile number.']]);
        }

        return $normalized;
    }

    private function hasSmsCredentials(): bool
    {
        return filled(config('services.twilio.account_sid'))
            && filled(config('services.twilio.auth_token'))
            && (filled(config('services.twilio.messaging_service_sid')) || filled(config('services.twilio.from')));
    }

    private function deliver(string $phone, string $code): bool
    {
        $sid = (string) config('services.twilio.account_sid');
        $serviceSid = config('services.twilio.messaging_service_sid');
        $sender = filled($serviceSid)
            ? ['MessagingServiceSid' => $serviceSid]
            : ['From' => config('services.twilio.from')];

        try {
            $result = Http::withBasicAuth($sid, (string) config('services.twilio.auth_token'))
                ->asForm()->connectTimeout(3)->timeout(7)
                ->post('https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($sid).'/Messages.json', $sender + [
                    'To' => '+92'.substr($phone, 1),
                    'Body' => "Your K. Fabrics verification code is {$code}. Valid for 5 minutes.",
                ]);

            return $result->successful() && filled($result->json('sid'));
        } catch (ConnectionException) {
            return false;
        }
    }
}
