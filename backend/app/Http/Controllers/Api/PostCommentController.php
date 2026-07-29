<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostCommentReplyRequest;
use App\Http\Requests\StorePostCommentRequest;
use App\Http\Requests\UpdatePostCommentRequest;
use App\Http\Resources\PostCommentResource;
use App\Models\Post;
use App\Models\PostComment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostCommentController extends Controller
{
    /**
     * Yazıya ait ana yorumları listeler.
     */
    public function index(Request $request, Post $post): JsonResponse
    {
        if ($post->status !== 'published') {
            return response()->json([
                'message' => 'Yalnızca yayınlanmış yazıların yorumları görüntülenebilir.',
            ], 403);
        }

        try {
            $comments = $this
                ->applyCommentInteractionCounts(PostComment::query(), $request)
                ->with('user:id,name,profile_photo')
                ->where('post_id', $post->id)
                ->roots()
                ->orderBy('created_at')
                ->paginate(20);

            return response()->json([
                'message' => 'Yorumlar başarıyla listelendi.',
                'comments' => PostCommentResource::collection($comments->items()),
                'meta' => [
                    'current_page' => $comments->currentPage(),
                    'last_page' => $comments->lastPage(),
                    'per_page' => $comments->perPage(),
                    'total' => $comments->total(),
                ],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Yorumlar getirilirken bir hata oluştu.',
            ], 500);
        }
    }

    /**
     * Ana yorumun yanıtlarını listeler.
     */
    public function replies(Request $request, PostComment $comment): JsonResponse
    {
        if (!$comment->isRoot()) {
            return response()->json([
                'message' => 'Yanıtlar yalnızca ana yorum üzerinden listelenebilir.',
            ], 422);
        }

        if ($comment->post?->status !== 'published') {
            return response()->json([
                'message' => 'Yalnızca yayınlanmış yazıların yorumları görüntülenebilir.',
            ], 403);
        }

        try {
            $replies = $this
                ->applyCommentInteractionCounts(PostComment::query(), $request)
                ->with([
                    'user:id,name,profile_photo',
                    'repliedToUser:id,name',
                ])
                ->where('parent_id', $comment->id)
                ->orderBy('created_at')
                ->paginate(20);

            return response()->json([
                'message' => 'Yanıtlar başarıyla listelendi.',
                'replies' => PostCommentResource::collection($replies->items()),
                'meta' => [
                    'current_page' => $replies->currentPage(),
                    'last_page' => $replies->lastPage(),
                    'per_page' => $replies->perPage(),
                    'total' => $replies->total(),
                ],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Yanıtlar getirilirken bir hata oluştu.',
            ], 500);
        }
    }

    /**
     * Yazıya yeni ana yorum ekler.
     */
    public function store(StorePostCommentRequest $request, Post $post): JsonResponse
    {
        $user = $request->user();

        if ($user->cannot('create', [PostComment::class, $post])) {
            if ($user->role === 'admin') {
                return response()->json([
                    'message' => 'Bu işlem yalnızca kullanıcılar tarafından yapılabilir.',
                ], 403);
            }

            if ($post->status !== 'published') {
                return response()->json([
                    'message' => 'Yalnızca yayınlanmış yazılara yorum yapılabilir.',
                ], 403);
            }

            return response()->json([
                'message' => 'Bu işlem için yetkiniz yok.',
            ], 403);
        }

        try {
            $comment = PostComment::create([
                'post_id' => $post->id,
                'user_id' => $request->user()->id,
                'content' => $request->validated('content'),
            ]);

            $comment->load('user:id,name,profile_photo');
            $comment->loadCount(['replies', 'likes']);

            if ($user->role === 'user') {
                $comment->setAttribute('is_liked_by_current_user', false);
            }

            return response()->json([
                'message' => 'Yorum başarıyla eklendi.',
                'comment' => new PostCommentResource($comment),
                'comments_count' => $post->comments()->count(),
            ], 201);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Yorum eklenirken bir hata oluştu.',
            ], 500);
        }
    }

    /**
     * Bir yoruma yanıt ekler.
     */
    public function storeReply(
        StorePostCommentReplyRequest $request,
        PostComment $comment,
    ): JsonResponse {
        $user = $request->user();

        if ($user->cannot('reply', $comment)) {
            if ($user->role === 'admin') {
                return response()->json([
                    'message' => 'Bu işlem yalnızca kullanıcılar tarafından yapılabilir.',
                ], 403);
            }

            if ($comment->post?->status !== 'published') {
                return response()->json([
                    'message' => 'Yalnızca yayınlanmış yazılara yanıt verilebilir.',
                ], 403);
            }

            return response()->json([
                'message' => 'Bu işlem için yetkiniz yok.',
            ], 403);
        }

        try {
            $comment->loadMissing('post');

            $rootId = $comment->parent_id ?? $comment->id;
            $post = $comment->post;

            $reply = PostComment::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'parent_id' => $rootId,
                'replied_to_comment_id' => $comment->id,
                'replied_to_user_id' => $comment->user_id,
                'content' => $request->validated('content'),
            ]);

            $reply->load([
                'user:id,name,profile_photo',
                'repliedToUser:id,name',
            ]);
            $reply->loadCount('likes');
            $reply->setAttribute('is_liked_by_current_user', false);

            $rootComment = PostComment::query()
                ->whereKey($rootId)
                ->withCount('replies')
                ->first();

            return response()->json([
                'message' => 'Yanıt başarıyla eklendi.',
                'reply' => new PostCommentResource($reply),
                'comments_count' => $post->comments()->count(),
                'replies_count' => (int) ($rootComment?->replies_count ?? 0),
            ], 201);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Yanıt eklenirken bir hata oluştu.',
            ], 500);
        }
    }

    /**
     * Yorumu günceller.
     */
    public function update(UpdatePostCommentRequest $request, PostComment $comment): JsonResponse
    {
        if ($request->user()->cannot('update', $comment)) {
            return response()->json([
                'message' => 'Bu yorumu düzenleme yetkiniz yok.',
            ], 403);
        }

        try {
            $comment->update([
                'content' => $request->validated('content'),
            ]);

            $comment->load('user:id,name,profile_photo');

            if ($comment->isReply()) {
                $comment->load('repliedToUser:id,name');
            }

            $comment = PostComment::query()
                ->whereKey($comment->id)
                ->with('user:id,name,profile_photo')
                ->when(
                    $comment->isReply(),
                    fn ($query) => $query->with('repliedToUser:id,name'),
                )
                ->withCount(['replies', 'likes'])
                ->when(
                    $request->user()?->role === 'user',
                    fn ($query) => $query->withExists([
                        'likes as is_liked_by_current_user' => fn (Builder $likeQuery) => $likeQuery
                            ->where('user_id', $request->user()->id),
                    ]),
                )
                ->first();

            return response()->json([
                'message' => 'Yorum başarıyla güncellendi.',
                'comment' => new PostCommentResource($comment),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Yorum güncellenirken bir hata oluştu.',
            ], 500);
        }
    }

    /**
     * Yorumu siler.
     */
    public function destroy(Request $request, PostComment $comment): JsonResponse
    {
        if ($request->user()->cannot('delete', $comment)) {
            return response()->json([
                'message' => 'Bu yorumu silme yetkiniz yok.',
            ], 403);
        }

        try {
            $post = $comment->post;
            $comment->delete();

            return response()->json([
                'message' => 'Yorum başarıyla silindi.',
                'comments_count' => $post?->comments()->count() ?? 0,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Yorum silinirken bir hata oluştu.',
            ], 500);
        }
    }

    private function applyCommentInteractionCounts(
        Builder $query,
        Request $request,
    ): Builder {
        $user = $request->user();

        $query->withCount(['replies', 'likes']);

        if ($user?->role === 'user') {
            $query->withExists([
                'likes as is_liked_by_current_user' => fn (Builder $likeQuery) => $likeQuery
                    ->where('user_id', $user->id),
            ]);
        }

        return $query;
    }
}
