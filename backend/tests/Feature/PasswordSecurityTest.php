<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const SAFE_PASSWORD = 'Purple-Lantern-42';

    public function test_register_rejects_password_shorter_than_eight_characters(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'short@example.com',
            'password' => 'short12',
            'password_confirmation' => 'short12',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'Şifre en az 8 karakter olmalıdır.');
    }

    public function test_register_accepts_password_that_meets_the_policy(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'safe@example.com',
            'password' => self::SAFE_PASSWORD,
            'password_confirmation' => self::SAFE_PASSWORD,
        ])->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'email' => 'safe@example.com',
        ]);
    }

    public function test_register_accepts_common_password_of_eight_characters(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'common@example.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ])->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)->putJson('/api/change-password', [
            'current_password' => 'wrong-password',
            'password' => self::SAFE_PASSWORD,
            'password_confirmation' => self::SAFE_PASSWORD,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.current_password.0', 'Mevcut şifre hatalı.');
    }

    public function test_change_password_rejects_reusing_the_current_password(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)->putJson('/api/change-password', [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'Yeni şifreniz mevcut şifrenizle aynı olamaz.');
    }

    public function test_change_password_rejects_confirmation_mismatch(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)->putJson('/api/change-password', [
            'current_password' => 'password',
            'password' => self::SAFE_PASSWORD,
            'password_confirmation' => 'Purple-Lantern-99',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'Yeni şifreler eşleşmiyor.');
    }

    public function test_change_password_keeps_current_token_and_revokes_the_others(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current', ['*'], now()->addDays(7));
        $other = $user->createToken('other', ['*'], now()->addDays(30));
        $originalExpiresAt = $current->accessToken->expires_at->toDateTimeString();

        $this->withToken($current->plainTextToken)->putJson('/api/change-password', [
            'current_password' => 'password',
            'password' => self::SAFE_PASSWORD,
            'password_confirmation' => self::SAFE_PASSWORD,
        ])->assertOk()
            ->assertJsonPath('message', 'Şifre başarıyla değiştirildi.');

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $current->accessToken->id,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $other->accessToken->id,
        ]);
        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame(
            $originalExpiresAt,
            $current->accessToken->fresh()->expires_at->toDateTimeString()
        );

        $this->withToken($current->plainTextToken)
            ->getJson('/api/profile')
            ->assertOk();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => self::SAFE_PASSWORD,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Email veya şifre hatalı.');
    }
}
