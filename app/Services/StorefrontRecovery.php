<?php

namespace App\Services;

use App\Jobs\SendStorefrontRecovery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StorefrontRecovery
{
    public function available(): bool
    {
        $mailer = (string) config('recovery.mailer');
        $queue = (string) config('queue.default');

        return $this->canResetSessions()
            && (bool) config('recovery.email_enabled')
            && filled(config('app.key'))
            && in_array(config("queue.connections.{$queue}.driver"), ['database', 'redis', 'sqs', 'beanstalkd'], true)
            && in_array(config("mail.mailers.{$mailer}.transport"), ['smtp', 'postmark', 'resend', 'ses', 'ses-v2'], true)
            && blank(config('mail.to.address'))
            && blank(config("mail.mailers.{$mailer}.to.address"))
            && $this->storefrontOrigin() !== null;
    }

    public function canResetSessions(): bool
    {
        $connection = config('session.connection');

        return config('session.driver') === 'database'
            && (blank($connection) || $connection === config('database.default'));
    }

    public function queueIfAvailable(string $kind, array $input): void
    {
        if (! $this->available()) {
            return;
        }

        // Throttle without looking up an account/order or storing identifiers in a cache key.
        $identity = $kind.':'.$input['email'].':'.($input['order_number'] ?? '');
        $key = 'storefront-recovery:'.hash_hmac('sha256', $identity, (string) config('app.key'));

        if (Cache::add($key, true, now()->addMinutes(2))) {
            SendStorefrontRecovery::dispatch($kind, $input, now()->timestamp);
        }
    }

    public function sendPasswordCode(string $email): void
    {
        $customer = $this->customer($email);
        if (! $customer) {
            return;
        }

        $broker = Password::broker();
        if ($broker->getRepository()->recentlyCreatedToken($customer)) {
            return;
        }
        $code = $broker->createToken($customer);

        try {
            $this->send($customer->email, 'Reset your K. Fabrics password', [
                'A password reset was requested for your K. Fabrics account.',
                'If you made this request, open '.$this->storefrontOrigin().'/reset-password and enter your email and this one-time code:',
                $code,
                'The code expires after '.(string) config('auth.passwords.users.expire').' minutes. Do not share it. Ignore this email if you did not request a new password.',
            ]);
        } catch (Throwable $exception) {
            if ($broker->tokenExists($customer, $code)) {
                $broker->deleteToken($customer);
            }
            throw $exception;
        }
    }

    public function sendTrackingCode(string $number, string $email, string $phone): void
    {
        $order = Order::query()->where('order_number', $number)->first();
        if (! $order || ! $order->customer_email) {
            return;
        }
        $normalizedOrderPhone = app(OtpService::class)->normalizePhone($order->customer_phone);
        if (! hash_equals(Str::lower(trim($order->customer_email)), $email)
            || ! hash_equals($normalizedOrderPhone, $phone)) {
            return;
        }

        $code = app(OrderTracking::class)->token($order);
        if (! $code) {
            // Older orders without a private credential may only be handled by support.
            return;
        }

        $this->send($order->customer_email, 'Your private K. Fabrics tracking code', [
            'A copy of the private tracking code for your K. Fabrics order was requested.',
            'Open '.$this->storefrontOrigin().'/track-order and enter these details:',
            'Order number: '.$order->order_number,
            'Private tracking code: '.$code,
            'Keep this code private. If you did not request it, you can ignore this message.',
        ]);
    }

    public function resetPassword(string $email, #[\SensitiveParameter] string $code, #[\SensitiveParameter] string $password): ?int
    {
        if (! $this->canResetSessions()) {
            return null;
        }

        return DB::transaction(function () use ($email, $code, $password): ?int {
            // Serialize token use for the same account with the password change.
            $customer = $this->customer($email, lock: true);
            $broker = Password::broker();
            if (! $customer || ! $broker->tokenExists($customer, $code)) {
                return null;
            }

            $customer->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            $broker->deleteToken($customer);
            $customer->tokens()->delete();
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                ->where('user_id', $customer->getKey())->delete();

            return (int) $customer->getKey();
        }, 3);
    }

    private function customer(string $email, bool $lock = false): ?User
    {
        $query = User::query()->where('role', 'customer')->whereRaw('LOWER(email) = ?', [$email])->limit(2);
        if ($lock) {
            $query->lockForUpdate();
        }
        $customers = $query->get();

        return $customers->count() === 1 ? $customers->first() : null;
    }

    private function storefrontOrigin(): ?string
    {
        $origin = rtrim((string) config('recovery.storefront_url'), '/');
        $parts = parse_url($origin);
        if (! filter_var($origin, FILTER_VALIDATE_URL) || ! is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || array_intersect(['user', 'pass', 'path', 'query', 'fragment'], array_keys($parts))
            || ! in_array($origin, config('cors.allowed_origins', []), true)) {
            return null;
        }

        return $origin;
    }

    private function send(#[\SensitiveParameter] string $destination, string $subject, #[\SensitiveParameter] array $lines): void
    {
        try {
            $sent = Mail::mailer((string) config('recovery.mailer'))
                ->raw(implode("\n\n", $lines), fn ($message) => $message->to($destination)->subject($subject));
        } catch (Throwable) {
            // Do not place raw recipients, verification codes or message bodies in worker logs.
            throw new RuntimeException('Storefront recovery email delivery failed.');
        }
        if (! $sent) {
            throw new RuntimeException('Storefront recovery email was not accepted.');
        }
    }
}
