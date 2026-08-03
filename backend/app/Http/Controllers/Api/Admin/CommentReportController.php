<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveCommentReportRequest;
use App\Http\Resources\PostCommentReportResource;
use App\Models\PostComment;
use App\Models\PostCommentReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommentReportController extends Controller
{
    /**
     * Yorum şikâyetlerini listeler.
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->cannot('viewAny', PostCommentReport::class)) {
            return response()->json([
                'message' => 'Bu işlem için yetkiniz yok.',
            ], 403);
        }

        try {
            $query = $this->buildFilteredQuery($request);

            $perPage = max(1, min(50, (int) $request->input('per_page', 9)));

            $reports = (clone $query)
                ->with([
                    'comment.user:id,name',
                    'comment.post:id,title,slug,status',
                    'reporter:id,name',
                    'reviewer:id,name',
                ])
                ->paginate($perPage);

            $this->hydratePendingCounts($reports->getCollection());

            return response()->json([
                'message' => 'Yorum şikâyetleri başarıyla listelendi.',
                'reports' => PostCommentReportResource::collection($reports->items()),
                'meta' => [
                    'current_page' => $reports->currentPage(),
                    'last_page' => $reports->lastPage(),
                    'per_page' => $reports->perPage(),
                    'total' => $reports->total(),
                ],
                'summary' => $this->buildSummary(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Yorum şikâyetleri listelenirken bir hata oluştu.',
            ], 500);
        }
    }

    /**
     * Tek bir yorum şikâyetinin detayını döndürür.
     */
    public function show(Request $request, PostCommentReport $report): JsonResponse
    {
        if ($request->user()->cannot('view', $report)) {
            return response()->json([
                'message' => 'Bu işlem için yetkiniz yok.',
            ], 403);
        }

        $report->load([
            'comment.user:id,name',
            'comment.post:id,title,slug,status',
            'reporter:id,name',
            'reviewer:id,name',
        ]);

        $this->hydratePendingCounts(collect([$report]));

        return response()->json([
            'message' => 'Yorum şikâyeti başarıyla getirildi.',
            'report' => new PostCommentReportResource($report),
        ]);
    }

    /**
     * Bekleyen şikâyetleri sonuçlandırır.
     */
    public function resolve(
        ResolveCommentReportRequest $request,
        PostCommentReport $report,
    ): JsonResponse {
        if ($request->user()->cannot('resolve', $report)) {
            if ($report->status !== PostCommentReport::STATUS_PENDING) {
                return response()->json([
                    'message' => 'Bu şikâyet daha önce sonuçlandırılmış.',
                ], 409);
            }

            return response()->json([
                'message' => 'Bu işlem için yetkiniz yok.',
            ], 403);
        }

        $action = $request->validated('action');
        $adminNote = $request->validated('admin_note');
        $adminId = $request->user()->id;

        try {
            $result = DB::transaction(function () use ($report, $action, $adminNote, $adminId) {
                $lockedReport = PostCommentReport::query()
                    ->whereKey($report->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedReport->status !== PostCommentReport::STATUS_PENDING) {
                    return [
                        'error' => true,
                        'status' => 409,
                        'message' => 'Bu şikâyet daha önce sonuçlandırılmış.',
                    ];
                }

                $pendingReports = $this
                    ->pendingReportsQuery($lockedReport)
                    ->lockForUpdate()
                    ->get();

                if ($pendingReports->isEmpty()) {
                    return [
                        'error' => true,
                        'status' => 409,
                        'message' => 'Bu şikâyete ait bekleyen kayıt bulunamadı.',
                    ];
                }

                $comment = null;

                if ($lockedReport->post_comment_id !== null) {
                    $comment = PostComment::query()
                        ->whereKey($lockedReport->post_comment_id)
                        ->lockForUpdate()
                        ->first();
                }

                if ($action === 'remove' && $comment === null) {
                    $resolvedStatus = PostCommentReport::STATUS_RESOLVED_REMOVED;
                    $now = now();

                    foreach ($pendingReports as $pendingReport) {
                        $pendingReport->update([
                            'status' => $resolvedStatus,
                            'reviewed_by' => $adminId,
                            'reviewed_at' => $now,
                            'admin_note' => $adminNote,
                        ]);
                    }

                    return [
                        'error' => false,
                        'resolved_count' => $pendingReports->count(),
                        'status' => $resolvedStatus,
                        'message' => 'Yorum zaten kaldırılmış. İlgili şikâyetler sonuçlandırıldı.',
                    ];
                }

                if ($action === 'keep') {
                    $resolvedStatus = PostCommentReport::STATUS_RESOLVED_KEPT;
                } else {
                    $resolvedStatus = PostCommentReport::STATUS_RESOLVED_REMOVED;

                    if ($comment !== null) {
                        foreach ($pendingReports as $pendingReport) {
                            $pendingReport->fill([
                                'comment_content_snapshot' => $pendingReport->comment_content_snapshot
                                    ?? $comment->content,
                                'comment_author_id_snapshot' => $pendingReport->comment_author_id_snapshot
                                    ?? $comment->user_id,
                                'post_id_snapshot' => $pendingReport->post_id_snapshot
                                    ?? $comment->post_id,
                            ]);
                        }
                    }
                }

                $now = now();

                foreach ($pendingReports as $pendingReport) {
                    $pendingReport->update([
                        'status' => $resolvedStatus,
                        'reviewed_by' => $adminId,
                        'reviewed_at' => $now,
                        'admin_note' => $adminNote,
                        'comment_content_snapshot' => $pendingReport->comment_content_snapshot,
                        'comment_author_id_snapshot' => $pendingReport->comment_author_id_snapshot,
                        'post_id_snapshot' => $pendingReport->post_id_snapshot,
                    ]);
                }

                if ($action === 'remove' && $comment !== null) {
                    $comment->delete();
                }

                return [
                    'error' => false,
                    'resolved_count' => $pendingReports->count(),
                    'status' => $resolvedStatus,
                    'message' => $action === 'keep'
                        ? 'Yorum bırakıldı ve ilgili şikâyetler sonuçlandırıldı.'
                        : 'Yorum kaldırıldı ve ilgili şikâyetler sonuçlandırıldı.',
                ];
            });

            if ($result['error'] ?? false) {
                return response()->json([
                    'message' => $result['message'],
                ], $result['status'] ?? 409);
            }

            return response()->json([
                'message' => $result['message'],
                'resolved_count' => $result['resolved_count'],
                'status' => $result['status'],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Şikâyet sonuçlandırılırken bir hata oluştu.',
            ], 500);
        }
    }

    private function buildFilteredQuery(Request $request): Builder
    {
        $query = PostCommentReport::query();
        $status = $request->input('status');
        $reason = $request->input('reason');

        if (
            is_string($status)
            && $status !== ''
            && in_array($status, [
                PostCommentReport::STATUS_PENDING,
                PostCommentReport::STATUS_RESOLVED_REMOVED,
                PostCommentReport::STATUS_RESOLVED_KEPT,
            ], true)
        ) {
            $query->where('status', $status);
        }

        if (
            is_string($reason)
            && $reason !== ''
            && in_array($reason, PostCommentReport::REASONS, true)
        ) {
            $query->where('reason', $reason);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('description', 'like', '%' . $search . '%')
                    ->orWhere('comment_content_snapshot', 'like', '%' . $search . '%')
                    ->orWhereHas(
                        'reporter',
                        fn (Builder $reporterQuery) => $reporterQuery
                            ->where('name', 'like', '%' . $search . '%'),
                    )
                    ->orWhereHas(
                        'comment.user',
                        fn (Builder $authorQuery) => $authorQuery
                            ->where('name', 'like', '%' . $search . '%'),
                    )
                    ->orWhereHas(
                        'comment.post',
                        fn (Builder $postQuery) => $postQuery
                            ->where('title', 'like', '%' . $search . '%'),
                    );
            });
        }

        $sort = $request->input('sort', 'latest');

        if ($sort === 'oldest') {
            $query->orderBy('created_at');
        } else {
            $query->latest();
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    private function buildSummary(): array
    {
        $statusCounts = PostCommentReport::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'all' => (int) PostCommentReport::query()->count(),
            'pending' => (int) ($statusCounts[PostCommentReport::STATUS_PENDING] ?? 0),
            'resolved_removed' => (int) ($statusCounts[PostCommentReport::STATUS_RESOLVED_REMOVED] ?? 0),
            'resolved_kept' => (int) ($statusCounts[PostCommentReport::STATUS_RESOLVED_KEPT] ?? 0),
        ];
    }

    private function pendingReportsQuery(PostCommentReport $report): Builder
    {
        $query = PostCommentReport::query()
            ->where('status', PostCommentReport::STATUS_PENDING);

        if ($report->post_comment_id !== null) {
            return $query->where('post_comment_id', $report->post_comment_id);
        }

        return $query
            ->whereNull('post_comment_id')
            ->where('comment_content_snapshot', $report->comment_content_snapshot)
            ->where('comment_author_id_snapshot', $report->comment_author_id_snapshot)
            ->where('post_id_snapshot', $report->post_id_snapshot);
    }

    /**
     * @param \Illuminate\Support\Collection<int, PostCommentReport> $reports
     */
    private function hydratePendingCounts($reports): void
    {
        $commentIds = $reports
            ->pluck('post_comment_id')
            ->filter()
            ->unique()
            ->values();

        $countsByCommentId = $commentIds->isEmpty()
            ? collect()
            : PostCommentReport::query()
                ->selectRaw('post_comment_id, COUNT(*) as aggregate')
                ->where('status', PostCommentReport::STATUS_PENDING)
                ->whereIn('post_comment_id', $commentIds)
                ->groupBy('post_comment_id')
                ->pluck('aggregate', 'post_comment_id');

        $countsBySnapshotKey = [];

        $reports
            ->filter(fn (PostCommentReport $report) => $report->post_comment_id === null)
            ->each(function (PostCommentReport $report) use (&$countsBySnapshotKey) {
                $snapshotKey = PostCommentReport::snapshotGroupKey(
                    $report->comment_content_snapshot,
                    $report->comment_author_id_snapshot,
                    $report->post_id_snapshot,
                );

                if (array_key_exists($snapshotKey, $countsBySnapshotKey)) {
                    return;
                }

                $countsBySnapshotKey[$snapshotKey] = PostCommentReport::query()
                    ->where('status', PostCommentReport::STATUS_PENDING)
                    ->whereNull('post_comment_id')
                    ->where('comment_content_snapshot', $report->comment_content_snapshot)
                    ->where('comment_author_id_snapshot', $report->comment_author_id_snapshot)
                    ->where('post_id_snapshot', $report->post_id_snapshot)
                    ->count();
            });

        PostCommentReportResource::$pendingCountsByCommentId = $countsByCommentId
            ->map(fn ($count) => (int) $count)
            ->all();
        PostCommentReportResource::$pendingCountsBySnapshotKey = $countsBySnapshotKey;
    }
}
