<?php

namespace Tests\Feature;

use App\Jobs\SendStorefrontRecovery;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderTracking;
use App\Services\StorefrontRecovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class StorefrontRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = 'https://shop.example.test';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cors.allowed_origins' => [self::ORIGIN],
            'sanctum.stateful' => ['shop.example.test'],
            'recovery.email_enabled' => true,
            'recovery.mailer' => 'smtp',
            'recovery.storefront_url' => self::ORIGIN,
            'session.driver' => 'database',
            'queue.default' => 'database',
        ]);
    }

    public function test_requests_accept_known_and_unknown_inputs_identically_and_jobs_are_encrypted(): void
    {
        Mail::shouldReceive('mailer')->never();
        $customer = User::factory()->create(['email' => 'fixture.customer@example.test', 'role' => 'customer']);
        $this->getJson('/api/v1/recovery/options')->assertOk()->assertJsonPath('data.email_recovery_available', true);
        $first = $this->fromStore('/password/forgot', ['email' => $customer->email])->assertAccepted();
        $second = $this->fromStore('/password/forgot', ['email' => 'missing.customer@example.test'])->assertAccepted();
        $this->assertSame($first->json(), $second->json());
        $this->assertSame('no-store, private', $first->headers->get('Cache-Control'));

        $jobs = DB::table('jobs')->pluck('payload');
        $this->assertCount(2, $jobs);
        $jobs->each(fn ($payload) => $this->assertStringNotContainsString('@example.test', $payload));
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_disabled_or_unsupported_configuration_never_queues_requests(): void
    {
        Queue::fake();
        config(['recovery.email_enabled' => false]);
        $this->getJson('/api/v1/recovery/options')->assertOk()->assertJsonPath('data.email_recovery_available', false);
        $this->fromStore('/password/forgot', ['email' => 'customer@example.test'])->assertAccepted();
        config(['recovery.email_enabled' => true, 'session.driver' => 'array']);
        $this->getJson('/api/v1/recovery/options')->assertOk()->assertJsonPath('data.email_recovery_available', false);
        $this->fromStore('/password/forgot', ['email' => 'customer@example.test'])->assertAccepted();
        $this->fromStore('/password/reset', [
            'email' => 'customer@example.test',
            'code' => str_repeat('a', 64),
            'password' => 'unused-fixture-value',
            'password_confirmation' => 'unused-fixture-value',
        ])->assertServiceUnavailable();
        Queue::assertNotPushed(SendStorefrontRecovery::class);
    }

    public function test_reset_mail_has_no_secret_in_url_and_code_changes_password_once_revoking_sessions(): void
    {
        $account = User::factory()->create(['email' => 'Fixture.Customer@example.test', 'role' => 'customer']);
        $account->createToken('fixture-api');
        DB::table('sessions')->insert([
            'id' => str_repeat('A', 40), 'user_id' => $account->id, 'payload' => 'YToyOnt9', 'last_activity' => now()->timestamp,
        ]);

        $code = null;
        $mailer = Mockery::mock();
        Mail::shouldReceive('mailer')->once()->with('smtp')->andReturn($mailer);
        $mailer->shouldReceive('raw')->once()->andReturnUsing(function (string $body, callable $address) use (&$code, $account) {
            $message = new Message(new Email);
            $address($message);
            $this->assertSame($account->email, $message->getSymfonyMessage()->getTo()[0]->getAddress());
            $this->assertMatchesRegularExpression('/shop\.example\.test\/reset-password and enter/', $body);
            preg_match('/^([a-f0-9]{64})$/m', $body, $matches);
            $code = $matches[1] ?? null;

            return new \stdClass;
        });
        (new SendStorefrontRecovery('password', ['email' => strtolower($account->email)], now()->timestamp))
            ->handle(app(StorefrontRecovery::class));
        $this->assertNotNull($code);
        $this->assertNotSame($code, DB::table('password_reset_tokens')->first()->token);

        $payload = [
            'email' => strtolower($account->email), 'code' => $code,
            'password' => 'fixture-new-password', 'password_confirmation' => 'fixture-new-password',
        ];
        $this->postJson('/api/v1/password/reset', $payload)->assertForbidden();
        $this->fromStore('/password/reset', $payload)->assertOk()->assertJsonMissingPath('code');
        $this->assertTrue(Hash::check('fixture-new-password', $account->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => str_repeat('A', 40)]);
        $this->assertCount(0, $account->tokens()->get());
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->fromStore('/password/reset', $payload)->assertUnprocessable()->assertJsonValidationErrors('code');
        $unknown = $this->fromStore('/password/reset', array_replace($payload, ['email' => 'no-match@example.test']));
        $unknown->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_admin_is_not_eligible_for_storefront_reset(): void
    {
        $admin = User::factory()->create(['email' => 'fixture.admin@example.test', 'role' => 'admin']);
        $token = Password::broker()->createToken($admin);
        Mail::shouldReceive('mailer')->never();
        (new SendStorefrontRecovery('password', ['email' => $admin->email], now()->timestamp))
            ->handle(app(StorefrontRecovery::class));
        $this->fromStore('/password/reset', [
            'email' => $admin->email, 'code' => $token,
            'password' => 'no-change-expected', 'password_confirmation' => 'no-change-expected',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertFalse(Hash::check('no-change-expected', $admin->fresh()->password));
    }

    public function test_tracking_recovery_verifies_all_checkout_fields_without_disclosing_code_to_requester(): void
    {
        $order = Order::create([
            'order_number' => 'KF-2026-FIXTURE', 'customer_name' => 'Private fixture',
            'customer_email' => 'order.fixture@example.test', 'customer_phone' => '+923005556677',
            'shipping_address' => 'Private fixture address', 'city' => 'Lahore', 'subtotal' => '1900', 'total_amount' => '2150',
        ]);
        $code = app(OrderTracking::class)->issue($order);
        $fixture = ['order_number' => strtolower($order->order_number), 'email' => strtoupper($order->customer_email), 'phone' => '+92 300-5556677'];

        Queue::fake();
        $one = $this->fromStore('/orders/recover-tracking', $fixture)->assertAccepted();
        $two = $this->fromStore('/orders/recover-tracking', array_replace($fixture, ['order_number' => 'KF-2026-UNKNOWN']))->assertAccepted();
        $this->assertSame($one->json(), $two->json());
        $one->assertDontSee($code)->assertDontSee($order->customer_email);
        Queue::assertPushed(SendStorefrontRecovery::class, 2);

        $mailer = Mockery::mock();
        Mail::shouldReceive('mailer')->once()->with('smtp')->andReturn($mailer);
        $mailer->shouldReceive('raw')->once()->andReturnUsing(function (string $body, callable $address) use ($order, $code) {
            $message = new Message(new Email);
            $address($message);
            $this->assertSame($order->customer_email, $message->getSymfonyMessage()->getTo()[0]->getAddress());
            $this->assertStringContainsString($code, $body);
            $this->assertStringContainsString('/track-order and enter these details', $body);
            $this->assertStringNotContainsString('Private fixture address', $body);

            return new \stdClass;
        });
        $recovery = app(StorefrontRecovery::class);
        $recovery->sendTrackingCode($order->order_number, $order->customer_email, '03001112233');
        $recovery->sendTrackingCode($order->order_number, 'different@example.test', '03005556677');
        $recovery->sendTrackingCode($order->order_number, $order->customer_email, '03005556677');
    }

    private function fromStore(string $path, array $data)
    {
        return $this->postJson('/api/v1'.$path, $data, ['Origin' => self::ORIGIN]);
    }
}
