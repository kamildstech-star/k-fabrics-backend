<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $this->ensureStorefrontSession($request);
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => 'customer',
            'password' => Hash::make($validated['password']),
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => new UserResource($user),
            'message' => 'User registered successfully.',
        ], 201)->header('Cache-Control', 'private, no-store');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $this->ensureStorefrontSession($request);
        $validated = $request->validated();
        $guard = Auth::guard('web');
        $email = $validated['email'];

        // Before email normalization, installations with a case-sensitive
        // collation may have stored mixed-case addresses. Use a fallback only
        // when it uniquely identifies a customer and never for an admin.
        if (! User::where('email', $email)->exists()) {
            $legacy = User::where('role', 'customer')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->limit(2)->pluck('email');
            if ($legacy->count() === 1) {
                $email = $legacy->first();
            }
        }

        if (! $guard->attempt([
            'email' => $email,
            'password' => $validated['password'],
            'role' => 'customer',
        ])) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => new UserResource($guard->user()),
            'message' => 'Login successful.',
        ])->header('Cache-Control', 'private, no-store');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->ensureStorefrontSession($request);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully.',
        ])->header('Cache-Control', 'private, no-store');
    }

    public function me(Request $request): JsonResponse
    {
        return (new UserResource($request->user('web')))->response()
            ->setStatusCode(200)
            ->header('Cache-Control', 'private, no-store');
    }

    private function ensureStorefrontSession(Request $request): void
    {
        // For protected storefront mutations, Sanctum must have started a session
        // and checked CSRF. Require the configured browser origin in addition to
        // Sanctum's stateful host match, which does not distinguish HTTP/HTTPS.
        $source = $request->headers->get('Origin') ?? $request->headers->get('Referer');
        $origin = null;
        if ($source && preg_match('~^https?://[^/?#]+~i', $source, $matches)) {
            $origin = $matches[0];
        }

        abort_unless(
            $request->attributes->get('sanctum') === true
                && $request->hasSession()
                && in_array($origin, config('cors.allowed_origins'), true),
            403,
            'Storefront session unavailable. Refresh the page and try again.'
        );
    }
}
