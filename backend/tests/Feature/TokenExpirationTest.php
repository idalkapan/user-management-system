<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_login_without_remember_me_expires_in_seven_days(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'email' => 'user@example.com',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();

        $expiresAt = Carbon::parse($response->json('data.expires_at'));

        $this->assertTrue(
            $expiresAt->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute())
        );
        $this->assertNotNull($user->tokens()->first()->expires_at);
    }

    public function test_user_remember_me_expires_in_thirty_days(): void
    {
        User::factory()->create([
            'role' => 'user',
            'email' => 'user@example.com',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'password',
            'remember_me' => true,
        ]);

        $response->assertOk();

        $expiresAt = Carbon::parse($response->json('data.expires_at'));

        $this->assertTrue(
            $expiresAt->between(now()->addDays(30)->subMinute(), now()->addDays(30)->addMinute())
        );
    }

    public function test_admin_remember_me_is_capped_at_twenty_four_hours(): void
    {
        User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
            'remember_me' => true,
        ]);

        $response->assertOk();

        $expiresAt = Carbon::parse($response->json('data.expires_at'));

        $this->assertTrue(
            $expiresAt->between(now()->addHours(23), now()->addHours(25))
        );
    }

    public function test_unknown_role_does_not_receive_a_long_lived_token(): void
    {
        User::factory()->create([
            'role' => 'editor',
            'email' => 'editor@example.com',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'editor@example.com',
            'password' => 'password',
            'remember_me' => true,
        ]);

        $response->assertOk();

        $expiresAt = Carbon::parse($response->json('data.expires_at'));

        $this->assertTrue(
            $expiresAt->between(now()->addHours(23), now()->addHours(25))
        );
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $token = $user->createToken('auth_token', ['*'], now()->addDay());

        $this->travel(2)->days();

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/profile')
            ->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        $token = $user->createToken('auth_token', ['*'], now()->addDays(7));

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/logout')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/profile')
            ->assertUnauthorized();
    }

    public function test_invalid_credentials_stay_unauthorized(): void
    {
        User::factory()->create([
            'role' => 'user',
            'email' => 'user@example.com',
        ]);

        $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
            'remember_me' => true,
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Email veya şifre hatalı.');
    }

    public function test_purge_legacy_command_deletes_only_tokens_without_expiration(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        $user->createToken('legacy');
        $user->createToken('current', ['*'], now()->addDays(7));

        $this->artisan('tokens:purge-legacy')
            ->assertSuccessful();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNotNull(
            PersonalAccessToken::query()->first()->expires_at
        );
    }
}
