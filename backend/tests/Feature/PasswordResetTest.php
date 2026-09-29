<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'Purple-Lantern-42';

    private const GENERIC_FORGOT_MESSAGE = 'Eğer bu e-posta adresi kayıtlıysa, şifre sıfırlama bağlantısı gönderildi.';

    private const INVALID_RESET_MESSAGE = 'Bu şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş.';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Notification::fake();
    }

    public function test_forgot_password_returns_the_same_message_for_known_and_unknown_emails(): void
    {
        $user = User::factory()->create([
            'email' => 'known@example.com',
        ]);

        $known = $this->postJson('/api/forgot-password', [
            'email' => 'known@example.com',
        ]);

        $unknown = $this->postJson('/api/forgot-password', [
            'email' => 'missing@example.com',
        ]);

        $known->assertOk()
            ->assertJsonPath('message', self::GENERIC_FORGOT_MESSAGE);

        $unknown->assertOk()
            ->assertJsonPath('message', self::GENERIC_FORGOT_MESSAGE);

        $this->assertSame(
            $known->json('message'),
            $unknown->json('message')
        );

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_reset_notification_links_to_the_frontend_route(): void
    {
        $user = User::factory()->create([
            'email' => 'link@example.com',
        ]);

        $this->postJson('/api/forgot-password', [
            'email' => 'link@example.com',
        ])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;
            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

            return str_starts_with($url, $frontendUrl.'/reset-password?')
                && str_contains($url, 'token=')
                && str_contains($url, 'email='.urlencode($user->email));
        });
    }

    public function test_valid_token_resets_the_password_and_revokes_every_token(): void
    {
        $user = User::factory()->create();
        $first = $user->createToken('first', ['*'], now()->addDays(7));
        $second = $user->createToken('second', ['*'], now()->addDays(30));
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $first->accessToken->id,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $second->accessToken->id,
        ]);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);

        $this->withToken($first->plainTextToken)
            ->getJson('/api/profile')
            ->assertUnauthorized();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnauthorized();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
        ])->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_reset_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => 'not-a-real-token',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertUnprocessable()
            ->assertJsonPath('message', self::INVALID_RESET_MESSAGE);
    }

    public function test_reset_rejects_a_used_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];

        $this->postJson('/api/reset-password', $payload)->assertOk();

        $this->postJson('/api/reset-password', [
            ...$payload,
            'password' => 'Another-Lantern-42',
            'password_confirmation' => 'Another-Lantern-42',
        ])->assertUnprocessable()
            ->assertJsonPath('message', self::INVALID_RESET_MESSAGE);
    }

    public function test_reset_rejects_an_expired_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->travel(61)->minutes();

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertUnprocessable()
            ->assertJsonPath('message', self::INVALID_RESET_MESSAGE);
    }

    public function test_reset_rejects_confirmation_mismatch_and_a_short_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $accessToken = $user->createToken('current')->plainTextToken;

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => 'Purple-Lantern-99',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'Şifreler eşleşmiyor.');

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'short12',
            'password_confirmation' => 'short12',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'Şifre en az 8 karakter olmalıdır.');

        $this->assertSame(1, $user->tokens()->count());
        $this->withToken($accessToken)->getJson('/api/profile')->assertOk();
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_change_password_still_keeps_the_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current', ['*'], now()->addDays(7));
        $other = $user->createToken('other', ['*'], now()->addDays(7));

        $this->withToken($current->plainTextToken)->putJson('/api/change-password', [
            'current_password' => 'password',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $current->accessToken->id,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $other->accessToken->id,
        ]);
        $this->withToken($current->plainTextToken)
            ->getJson('/api/profile')
            ->assertOk();
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'limited@example.com',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/forgot-password', [
                'email' => 'limited@example.com',
            ])->assertOk();
        }

        $this->postJson('/api/forgot-password', [
            'email' => 'limited@example.com',
        ])->assertTooManyRequests();
    }

    public function test_reset_password_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/reset-password', [
                'email' => $user->email,
                'token' => 'not-a-real-token',
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])->assertUnprocessable();
        }

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => 'not-a-real-token',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertTooManyRequests();
    }
}
