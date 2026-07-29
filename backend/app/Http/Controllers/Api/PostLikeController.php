<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostLike;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostLikeController extends Controller
{
    /**
     * Yazıya beğeni ekler.
     */
    public function store(Request $request, Post $post): JsonResponse
    {
        $denyResponse = $this->authorizeLikeAction($request, $post);

        if ($denyResponse) {
            return $denyResponse;
        }

        $user = $request->user();

        try {
            PostLike::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);

            return $this->likeResponse(
                'Beğeni eklendi.',
                $post,
                true,
                201,
            );
        } catch (UniqueConstraintViolationException) {
            return $this->likeResponse(
                'Bu yazıyı zaten beğendiniz.',
                $post,
                true,
            );
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Beğeni işlemi sırasında bir hata oluştu.',
            ], 500);
        }
    }

    /**
     * Yazıdan beğeniyi kaldırır.
     */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        $denyResponse = $this->authorizeLikeAction($request, $post);

        if ($denyResponse) {
            return $denyResponse;
        }

        $user = $request->user();

        try {
            $deleted = PostLike::query()
                ->where('post_id', $post->id)
                ->where('user_id', $user->id)
                ->delete();

            return $this->likeResponse(
                $deleted > 0
                    ? 'Beğeni kaldırıldı.'
                    : 'Bu yazıyı zaten beğenmemişsiniz.',
                $post,
                false,
            );
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Beğeni işlemi sırasında bir hata oluştu.',
            ], 500);
        }
    }

    private function authorizeLikeAction(Request $request, Post $post): ?JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'user') {
            return response()->json([
                'message' => 'Bu işlem yalnızca kullanıcılar tarafından yapılabilir.',
            ], 403);
        }

        if ($post->status !== 'published') {
            return response()->json([
                'message' => 'Yalnızca yayınlanmış yazılar beğenilebilir.',
            ], 403);
        }

        if ($post->user_id === $user->id) {
            return response()->json([
                'message' => 'Kendi yazınızı beğenemezsiniz.',
            ], 403);
        }

        return null;
    }

    private function likeResponse(
        string $message,
        Post $post,
        bool $isLikedByCurrentUser,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'likes_count' => $post->likes()->count(),
            'is_liked_by_current_user' => $isLikedByCurrentUser,
        ], $status);
    }
}
