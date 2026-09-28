<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_failed_login_stays_unauthorized_until_the_login_limit(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', [
                'email' => 'user@example.com',
                'password' => 'wrong-password',
            ])->assertUnauthorized()
                ->assertJsonPath('message', 'Email veya şifre hatalı.');
        }

        $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_login_limit_is_scoped_to_email_and_ip(): void
    {
        User::factory()->create([
            'email' => 'first@example.com',
        ]);

        User::factory()->create([
            'email' => 'second@example.com',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', [
                'email' => 'FIRST@example.com',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'email' => 'second@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_successful_login_still_returns_a_token(): void
    {
        User::factory()->create([
            'email' => 'login-ok@example.com',
        ]);

        $this->postJson('/api/login', [
            'email' => 'login-ok@example.com',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['token'],
            ]);
    }

    public function test_register_limit_returns_too_many_requests(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/register', [
                'name' => 'Test User',
                'email' => "user{$attempt}@example.com",
                'password' => 'password1',
                'password_confirmation' => 'password1',
            ])->assertCreated();
        }

        $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'blocked@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])->assertTooManyRequests();
    }

    public function test_missing_token_still_returns_unauthorized(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_non_admin_still_cannot_open_admin_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }
}
