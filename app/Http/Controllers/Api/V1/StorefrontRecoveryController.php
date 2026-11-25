<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use App\Services\StorefrontRecovery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

class StorefrontRecoveryController extends Controller
{
    public function options(StorefrontRecovery $recovery): JsonResponse
    {
        return response()->json(['data' => ['email_recovery_available' => $recovery->available()]])
            ->header('Cache-Control', 'private, no-store');
    }

    public function forgotPassword(Request $request, StorefrontRecovery $recovery): JsonResponse
    {
        $this->ensureStorefront($request);
        $input = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        rescue(fn () => $recovery->queueIfAvailable('password', ['email' => Str::lower(trim($input['email']))]), report: false);

        return $this->accepted();
    }

    public function recoverTracking(Request $request, StorefrontRecovery $recovery, OtpService $phone): JsonResponse
    {
        $this->ensureStorefront($request);
        $input = $request->validate([
            'order_number' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:25'],
        ]);
        $normalized = $phone->normalizePhone($input['phone']);
        if (! preg_match('/^03[0-9]{9}$/D', $normalized)) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Pakistani mobile number.']);
        }

        rescue(fn () => $recovery->queueIfAvailable('tracking', [
            'order_number' => Str::upper(trim($input['order_number'])),
            'email' => Str::lower(trim($input['email'])),
            'phone' => $normalized,
        ]), report: false);

        return $this->accepted();
    }

    public function resetPassword(Request $request, StorefrontRecovery $recovery): JsonResponse
    {
        $this->ensureStorefront($request);
        abort_unless($recovery->canResetSessions(), 503, 'Password reset is temporarily unavailable. Contact store support.');
        $input = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed', function (string $attribute, mixed $password, \Closure $fail) {
                if (is_string($password) && strlen($password) > 72) {
                    $fail('The password must not exceed 72 bytes.');
                }
            }],
        ]);
        $id = (new Timebox)->call(fn () => $recovery->resetPassword(
            Str::lower(trim($input['email'])), $input['code'], $input['password'],
        ), 300_000);
        if (! $id) {
            throw ValidationException::withMessages(['code' => 'That reset code is invalid or expired. Request a new one and try again.']);
        }

        if ((int) $request->user('web')?->getKey() === $id) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Password reset. Sign in with your new password.'])
            ->header('Cache-Control', 'private, no-store');
    }

    private function accepted(): JsonResponse
    {
        return response()->json(['message' => 'If the details match and email recovery is available, a message will be sent.'], 202)
            ->header('Cache-Control', 'private, no-store');
    }

    private function ensureStorefront(Request $request): void
    {
        $source = $request->headers->get('Origin') ?? $request->headers->get('Referer');
        $origin = null;
        if ($source && preg_match('~^https?://[^/?#]+~i', $source, $match)) {
            $origin = $match[0];
        }
        abort_unless(
            $request->attributes->get('sanctum') === true && $request->hasSession()
                && in_array($origin, config('cors.allowed_origins', []), true),
            403,
            'Storefront session unavailable. Refresh the page and try again.'
        );
    }
}
