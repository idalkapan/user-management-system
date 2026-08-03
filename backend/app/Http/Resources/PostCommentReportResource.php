<?php

namespace App\Http\Resources;

use App\Models\Post;
use App\Models\PostCommentReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostCommentReportResource extends JsonResource
{
    /**
     * @var array<int|string, int>|null
     */
    public static ?array $pendingCountsByCommentId = null;

    /**
     * @var array<string, int>|null
     */
    public static ?array $pendingCountsBySnapshotKey = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $comment = $this->comment;
        $commentAuthor = $comment?->user;
        $post = $comment?->post;

        if (!$commentAuthor && $this->comment_author_id_snapshot) {
            $commentAuthor = User::query()
                ->select('id', 'name')
                ->find($this->comment_author_id_snapshot);
        }

        if (!$post && $this->post_id_snapshot) {
            $post = Post::query()
                ->select('id', 'title', 'slug', 'status')
                ->find($this->post_id_snapshot);
        }

        $commentContent = $comment?->content ?? $this->comment_content_snapshot;
        $commentMissing = $this->post_comment_id === null || $comment === null;

        return [
            'id' => $this->id,
            'post_comment_id' => $this->post_comment_id,
            'reason_code' => $this->reason,
            'reason_label' => PostCommentReport::reasonLabel($this->reason),
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => PostCommentReport::statusLabel($this->status),
            'admin_note' => $this->admin_note,
            'created_at' => $this->created_at,
            'reviewed_at' => $this->reviewed_at,
            'comment_content_snapshot' => $this->comment_content_snapshot,
            'comment_author_id_snapshot' => $this->comment_author_id_snapshot,
            'post_id_snapshot' => $this->post_id_snapshot,
            'comment_missing' => $commentMissing,
            'comment_preview' => $commentContent
                ? mb_substr($commentContent, 0, 160)
                : null,
            'pending_reports_count' => $this->resolvePendingReportsCount(),
            'reporter' => $this->when(
                $this->relationLoaded('reporter') && $this->reporter,
                fn () => [
                    'id' => $this->reporter->id,
                    'name' => $this->reporter->name,
                ],
            ),
            'reviewer' => $this->when(
                $this->relationLoaded('reviewer') && $this->reviewer,
                fn () => [
                    'id' => $this->reviewer->id,
                    'name' => $this->reviewer->name,
                ],
            ),
            'comment' => $this->when(
                $comment !== null,
                fn () => [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'author' => $commentAuthor
                        ? [
                            'id' => $commentAuthor->id,
                            'name' => $commentAuthor->name,
                        ]
                        : null,
                    'post' => $post
                        ? [
                            'id' => $post->id,
                            'title' => $post->title,
                            'slug' => $post->slug ?? null,
                            'status' => $post->status ?? null,
                        ]
                        : null,
                ],
            ),
            'comment_author' => [
                'id' => $commentAuthor?->id ?? $this->comment_author_id_snapshot,
                'name' => $commentAuthor?->name ?? 'Bilinmiyor',
            ],
            'post' => $post
                ? [
                    'id' => $post->id,
                    'title' => $post->title,
                    'slug' => $post->slug ?? null,
                    'status' => $post->status ?? null,
                ]
                : ($this->post_id_snapshot
                    ? [
                        'id' => $this->post_id_snapshot,
                        'title' => null,
                        'slug' => null,
                        'status' => null,
                    ]
                    : null),
        ];
    }

    private function resolvePendingReportsCount(): int
    {
        if ($this->post_comment_id !== null) {
            return (int) (self::$pendingCountsByCommentId[$this->post_comment_id] ?? 0);
        }

        $snapshotKey = PostCommentReport::snapshotGroupKey(
            $this->comment_content_snapshot,
            $this->comment_author_id_snapshot,
            $this->post_id_snapshot,
        );

        return (int) (self::$pendingCountsBySnapshotKey[$snapshotKey] ?? 0);
    }
}
