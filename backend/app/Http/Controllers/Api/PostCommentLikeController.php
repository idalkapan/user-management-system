<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PostComment;
use App\Models\PostCommentLike;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostCommentLikeController extends Controller
{
    /**
     * Yoruma beğeni ekler.
     */
    public function store(Request $request, PostComment $comment): JsonResponse
    {
        $denyResponse = $this->authorizeLikeAction($request, $comment);

        if ($denyResponse) {
            return $denyResponse;
        }

        $user = $request->user();

        try {
            PostCommentLike::create([
                'post_comment_id' => $comment->id,
                'user_id' => $user->id,
            ]);

            return $this->likeResponse(
                'Beğeni eklendi.',
                $comment,
                true,
                201,
            );
        } catch (UniqueConstraintViolationException) {
            return $this->likeResponse(
                'Bu yorumu zaten beğendiniz.',
                $comment,
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
     * Yorumdan beğeniyi kaldırır.
     */
    public function destroy(Request $request, PostComment $comment): JsonResponse
    {
        $denyResponse = $this->authorizeLikeAction($request, $comment);

        if ($denyResponse) {
            return $denyResponse;
        }

        $user = $request->user();

        try {
            PostCommentLike::query()
                ->where('post_comment_id', $comment->id)
                ->where('user_id', $user->id)
                ->delete();

            return $this->likeResponse(
                'Beğeni kaldırıldı.',
                $comment,
                false,
            );
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Beğeni işlemi sırasında bir hata oluştu.',
            ], 500);
        }
    }

    private function authorizeLikeAction(
        Request $request,
        PostComment $comment,
    ): ?JsonResponse {
        $user = $request->user();

        if ($user->cannot('like', $comment)) {
            if ($user->role === 'admin') {
                return response()->json([
                    'message' => 'Bu işlem yalnızca kullanıcılar tarafından yapılabilir.',
                ], 403);
            }

            if ($comment->post?->status !== 'published') {
                return response()->json([
                    'message' => 'Yalnızca yayınlanmış yazılardaki yorumlar beğenilebilir.',
                ], 403);
            }

            return response()->json([
                'message' => 'Bu işlem için yetkiniz yok.',
            ], 403);
        }

        return null;
    }

    private function likeResponse(
        string $message,
        PostComment $comment,
        bool $isLikedByCurrentUser,
        int $status = 200,
    ): JsonResponse {
        $comment->loadCount('likes');

        return response()->json([
            'message' => $message,
            'likes_count' => (int) $comment->likes_count,
            'is_liked_by_current_user' => $isLikedByCurrentUser,
        ], $status);
    }
}
