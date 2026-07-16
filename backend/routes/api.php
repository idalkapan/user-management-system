<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\PostController;

/*
|--------------------------------------------------------------------------
| Kullanıcı Bilgisi
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Kimlik Doğrulama
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->post(
    '/logout',
    [AuthController::class, 'logout']
);

/*
|--------------------------------------------------------------------------
| Profil İşlemleri
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get(
    '/profile',
    [ProfileController::class, 'show']
);

Route::middleware('auth:sanctum')->put(
    '/profile',
    [ProfileController::class, 'update']
);

Route::middleware('auth:sanctum')->put(
    '/change-password',
    [ProfileController::class, 'changePassword']
);

Route::middleware('auth:sanctum')->post(
    '/profile/photo',
    [ProfileController::class, 'uploadPhoto']
);

/*
|--------------------------------------------------------------------------
| Admin İşlemleri
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/test', function () {
        return response()->json([
            'message' => 'Admin alanına başarıyla eriştiniz.',
        ]);
    });

    Route::get(
        '/admin/posts/pending',
        [PostController::class, 'pending']
    );

    Route::patch(
        '/admin/posts/{id}/approve',
        [PostController::class, 'approve']
    );

    Route::patch(
        '/admin/posts/{id}/reject',
        [PostController::class, 'reject']
    );
});

/*
|--------------------------------------------------------------------------
| Blog Yazısı İşlemleri
|--------------------------------------------------------------------------
*/

Route::get('/posts', [PostController::class, 'index']);

Route::middleware('auth:sanctum')->post(
    '/posts',
    [PostController::class, 'store']
);

Route::get(
    '/posts/{id}',
    [PostController::class, 'show']
);

Route::middleware('auth:sanctum')->put(
    '/posts/{id}',
    [PostController::class, 'update']
);

Route::middleware('auth:sanctum')->delete(
    '/posts/{id}',
    [PostController::class, 'destroy']
);
Route::middleware('auth:sanctum')->get(
    '/my-posts',
    [PostController::class, 'myPosts']
);