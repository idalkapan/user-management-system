<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8));

        $resetUrl = function (object $user, string $token): string {
            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

            return $frontendUrl.'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        };

        ResetPassword::createUrlUsing($resetUrl);

        ResetPassword::toMailUsing(function (object $user, string $token) use ($resetUrl) {
            $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Şifre sıfırlama')
                ->line('Hesabınız için bir şifre sıfırlama isteği aldık.')
                ->action('Şifreyi sıfırla', $resetUrl($user, $token))
                ->line('Bu bağlantı '.$expire.' dakika içinde geçerliliğini yitirir.')
                ->line('Bu isteği siz yapmadıysanız herhangi bir işlem yapmanız gerekmez.');
        });

        config([
            'cors.exposed_headers' => ['Retry-After'],
        ]);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(trim($email)) : '';

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('reset-password', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
