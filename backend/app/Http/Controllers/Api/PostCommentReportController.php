<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostCommentReportRequest;
use App\Models\PostComment;
use App\Models\PostCommentReport;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;

class PostCommentReportController extends Controller
{
    /**
     * Yorum şikâyeti oluşturur.
     */
    public function store(
        StorePostCommentReportRequest $request,
        PostComment $comment,
    ): JsonResponse {
        $user = $request->user();

        if (
            PostCommentReport::query()
                ->where('post_comment_id', $comment->id)
                ->where('reported_by', $user->id)
                ->exists()
        ) {
            return response()->json([
                'message' => 'Bu yorumu zaten şikâyet ettiniz.',
            ], 422);
        }

        try {
            $report = PostCommentReport::create([
                'post_comment_id' => $comment->id,
                'reported_by' => $user->id,
                'reason' => $request->validated('reason'),
                'description' => $request->validated('description'),
                'status' => PostCommentReport::STATUS_PENDING,
                'comment_content_snapshot' => $comment->content,
                'comment_author_id_snapshot' => $comment->user_id,
                'post_id_snapshot' => $comment->post_id,
            ]);

            return response()->json([
                'message' => 'Şikâyetiniz başarıyla alındı.',
                'report' => [
                    'id' => $report->id,
                    'post_comment_id' => $report->post_comment_id,
                    'reason' => $report->reason,
                    'status' => $report->status,
                    'created_at' => $report->created_at,
                ],
            ], 201);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Bu yorumu zaten şikâyet ettiniz.',
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Şikâyet oluşturulurken bir hata oluştu.',
            ], 500);
        }
    }
}
