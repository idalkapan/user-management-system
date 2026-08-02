<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\StatisticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PostInteractionController;
use App\Http\Controllers\Api\PostCommentController;
use App\Http\Controllers\Api\PostCommentLikeController;
use App\Http\Controllers\Api\PostCommentReportController;
use App\Http\Controllers\Api\PostLikeController;
use App\Http\Controllers\Api\CategoryController as PublicCategoryController;

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
        '/admin/dashboard',
        [PostController::class, 'dashboard']
    );

    Route::get(
        '/admin/statistics',
        [StatisticsController::class, 'index']
    );

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

    Route::get('/admin/categories', [CategoryController::class, 'index']);
    Route::post('/admin/categories', [CategoryController::class, 'store']);
    Route::put('/admin/categories/{category}', [CategoryController::class, 'update']);
    Route::patch('/admin/categories/{category}/status', [CategoryController::class, 'updateStatus']);
    Route::get('/admin/categories/{category}/posts', [CategoryController::class, 'posts']);
    Route::post('/admin/categories/{category}/restore', [CategoryController::class, 'restore'])
        ->withTrashed()
        ->name('admin.categories.restore');
    Route::delete('/admin/categories/{category}', [CategoryController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Blog Yazısı İşlemleri
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get(
    '/categories',
    [PublicCategoryController::class, 'index']
);

Route::middleware('auth:sanctum')->get(
    '/posts',
    [PostController::class, 'index']
);

Route::middleware('auth:sanctum')->post(
    '/posts',
    [PostController::class, 'store']
);

Route::middleware('auth:sanctum')->get(
    '/posts/{id}',
    [PostController::class, 'show']
);

Route::middleware('auth:sanctum')->post(
    '/posts/{post}/views',
    [PostInteractionController::class, 'recordView']
);

Route::middleware('auth:sanctum')->post(
    '/posts/{post}/like',
    [PostLikeController::class, 'store']
);

Route::middleware('auth:sanctum')->delete(
    '/posts/{post}/like',
    [PostLikeController::class, 'destroy']
);

Route::middleware('auth:sanctum')->get(
    '/posts/{post}/comments',
    [PostCommentController::class, 'index']
);

Route::middleware('auth:sanctum')->post(
    '/posts/{post}/comments',
    [PostCommentController::class, 'store']
);

Route::middleware('auth:sanctum')->put(
    '/comments/{comment}',
    [PostCommentController::class, 'update']
);

Route::middleware('auth:sanctum')->delete(
    '/comments/{comment}',
    [PostCommentController::class, 'destroy']
);

Route::middleware('auth:sanctum')->get(
    '/comments/{comment}/replies',
    [PostCommentController::class, 'replies']
);

Route::middleware('auth:sanctum')->post(
    '/comments/{comment}/replies',
    [PostCommentController::class, 'storeReply']
);

Route::middleware('auth:sanctum')->post(
    '/comments/{comment}/like',
    [PostCommentLikeController::class, 'store']
);

Route::middleware('auth:sanctum')->delete(
    '/comments/{comment}/like',
    [PostCommentLikeController::class, 'destroy']
);

Route::middleware('auth:sanctum')->post(
    '/comments/{comment}/report',
    [PostCommentReportController::class, 'store']
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

Route::middleware('auth:sanctum')->get(
    '/my-statistics',
    [PostController::class, 'myStatistics']
);