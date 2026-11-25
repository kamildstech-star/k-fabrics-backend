<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cors.allowed_origins' => ['http://localhost:3000'],
            'sanctum.stateful' => ['localhost:3000'],
        ]);
    }

    public function test_registration_requires_a_storefront_origin(): void
    {
        $this->withHeader('Origin', 'https://other.example')->postJson('/api/v1/register', [
            'name' => 'Customer',
            'email' => 'customer@example.test',
            'password' => 'not-a-real-password',
            'password_confirmation' => 'not-a-real-password',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'customer@example.test']);
    }

    public function test_storefront_login_does_not_create_a_personal_token(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'password' => 'not-a-real-password',
        ]);

        $response = $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/v1/login', [
            'email' => $customer->email,
            'password' => 'not-a-real-password',
        ]);

        $response->assertOk()->assertJsonMissingPath('token');
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $session = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($session);
        $this->assertTrue($session->isHttpOnly());
        $this->assertSame('lax', $session->getSameSite());
    }

    public function test_legacy_mixed_case_customer_email_still_logs_in(): void
    {
        User::factory()->create([
            'email' => 'LegacyCustomer@example.test',
            'role' => 'customer',
            'password' => 'not-a-real-password',
        ]);

        $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/v1/login', [
            'email' => 'legacycustomer@example.test',
            'password' => 'not-a-real-password',
        ])->assertOk();
    }

    public function test_personal_token_is_not_a_storefront_session(): void
    {
        $token = User::factory()->create()->createToken('internal-test')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/user/me')->assertUnauthorized();
    }

    public function test_admin_credentials_are_not_accepted_by_storefront_login(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => 'not-a-real-password',
        ]);

        $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/v1/login', [
            'email' => $admin->email,
            'password' => 'not-a-real-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}
