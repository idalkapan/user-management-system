<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'success' => true,
            'message' => 'Eğer bu e-posta adresi kayıtlıysa, şifre sıfırlama bağlantısı gönderildi.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => 'Bu şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Şifreniz sıfırlandı. Yeni şifrenizle giriş yapabilirsiniz.',
        ]);
    }
}
