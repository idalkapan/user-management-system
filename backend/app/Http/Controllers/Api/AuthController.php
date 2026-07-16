<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserResource;
use App\Http\Requests\LoginRequest;


class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Kullanıcı başarıyla oluşturuldu.',
            'data' => [
                'user' => new UserResource($user),
            ],
        ], 201);
    }
    public function login(LoginRequest $request)
    {
        $validated = $request->validated();
        
        $user = User::where('email', $validated['email'])->first();
        
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email veya şifre hatalı.',
                ], 401);
            }
            
            $token = $user->createToken('auth_token')->plainTextToken;
            return response()->json([
                'success' => true,
                'message' => 'Giriş başarılı.',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token,
                    ],
                    ]);

    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'success' => true,
            'message' => 'Çıkış başarılı.',
            ]);
            }
    
}

