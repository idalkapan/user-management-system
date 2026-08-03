<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Requests\RejectPostRequest;
use App\Models\Category;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\PostView;
use App\Models\User;
use Illuminate\Support\Carbon;

class PostController extends Controller
{
    /**
     * Yazıları listeler.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Post::with(['user', 'category'])
            ->withCount(['views', 'likes', 'comments']);

        if ($user?->role === 'user') {
            $query->withExists([
                'likes as is_liked_by_current_user' => fn ($likeQuery) =>
                    $likeQuery->where('user_id', $user->id),
            ]);
        }

        if (!$user || $user->role !== 'admin') {
            $query->where('status', 'published');
            }

        if ($request->filled('search')) {
            $search = $request->input('search');
            
            $query->where(function ($query) use ($search) {
                $query
                 ->where('title', 'like', '%' . $search . '%')
                 ->orWhere('content', 'like', '%' . $search . '%');
                 });
                 }
            if ($request->filled('category')) {
                $categorySlug = $request->input('category');
                
                $query->whereHas('category', function ($query) use ($categorySlug) {
                     $query
                      ->where('slug', $categorySlug)
                      ->where('is_active', true);
                });
                }

        $perPage = max(1, min(50, (int) $request->input('per_page', 9)));

        $posts = $query
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'message' => 'Yazılar başarıyla listelendi.',
            'posts' => PostResource::collection($posts->items()),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Yeni yazı oluşturur.
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $imagePath = null;

        if ($request->hasFile('featured_image')) {
            $imagePath = $request
                ->file('featured_image')
                ->store('posts', 'public');
        }

        $post = Post::create([
            'user_id' => $request->user()->id,
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']) . '-' . time(),
            'content' => $validated['content'],
            'featured_image' => $imagePath,
            'status' => $validated['status'],
            'rejection_reason' => null,
        ]);

        $post->load(['user', 'category']);
        $post->loadCount('views');

        return response()->json([
            'message' => $validated['status'] === 'draft'
               ? 'Yazınız taslak olarak kaydedildi.'
               : 'Yazınız oluşturuldu ve yönetici onayına gönderildi.',
            'post' => new PostResource($post),
        ], 201);
    }

    /**
     * Belirli bir yazıyı getirir.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $query = Post::with(['user', 'category'])
            ->withCount(['views', 'likes', 'comments']);

        if ($user?->role === 'user') {
            $query->withExists([
                'likes as is_liked_by_current_user' => fn ($likeQuery) =>
                    $likeQuery->where('user_id', $user->id),
            ]);
        }

        $post = $query->findOrFail($id);

        if ($user->cannot('view', $post)) {
            return response()->json([
                'message' => 'Bu yazıyı görüntüleme yetkiniz bulunmamaktadır.',
            ], 403);
        }

        return response()->json([
            'message' => 'Yazı başarıyla getirildi.',
            'post' => new PostResource($post),
        ]);
    }

    /**
     * Yazıyı günceller.
     */
    public function update(
        UpdatePostRequest $request,
        string $id
    ): JsonResponse {
        $post = Post::findOrFail($id);

        Gate::authorize('update', $post);

        $validated = $request->validated();

        $imagePath = $post->featured_image;

        if ($request->hasFile('featured_image')) {
            if ($post->featured_image) {
                Storage::disk('public')->delete(
                    $post->featured_image
                );
            }

            $imagePath = $request
                ->file('featured_image')
                ->store('posts', 'public');
        }

        $post->update([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']) . '-' . time(),
            'content' => $validated['content'],
            'category_id' => $validated['category_id'],
            'featured_image' => $imagePath,
            'status' => $validated['status'],
            'rejection_reason' => null,
        ]);

        $post->load(['user', 'category']);
        $post->loadCount('views');

        return response()->json([
            'message' => $validated['status'] === 'draft'
               ? 'Yazınız taslak olarak güncellendi.'
               : 'Yazınız güncellendi ve yönetici onayına gönderildi.',
            'post' => new PostResource($post),
        ]);
    }
    public function myPosts(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $allowedStatuses = ['draft', 'pending', 'published', 'rejected'];
        $status = $request->input('status');

        $query = Post::with(['user', 'category'])
            ->withCount(['views', 'likes', 'comments'])
            ->where('user_id', $userId);

        if (
            is_string($status)
            && $status !== ''
            && in_array($status, $allowedStatuses, true)
        ) {
            $query->where('status', $status);
        }

        $perPage = max(1, min(50, (int) $request->input('per_page', 9)));

        $posts = $query
            ->latest()
            ->paginate($perPage);

        $statusCounts = Post::query()
            ->where('user_id', $userId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $summary = [
            'all' => (int) Post::query()->where('user_id', $userId)->count(),
            'draft' => (int) ($statusCounts['draft'] ?? 0),
            'pending' => (int) ($statusCounts['pending'] ?? 0),
            'published' => (int) ($statusCounts['published'] ?? 0),
            'rejected' => (int) ($statusCounts['rejected'] ?? 0),
        ];

        return response()->json([
            'message' => 'Yazılarınız başarıyla listelendi.',
            'posts' => PostResource::collection($posts->items()),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Giriş yapan kullanıcının yayınlanmış yazılarına ait istatistikleri döndürür.
     */
    public function myStatistics(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $periodDays = $this->resolveStatisticsPeriodDays($request->query('period'));
        $periodKey = $periodDays === 7 ? '7_days' : '30_days';

        $publishedPostsQuery = Post::query()
            ->where('user_id', $userId)
            ->where('status', 'published');

        $publishedPostsCount = (clone $publishedPostsQuery)->count();

        if ($publishedPostsCount === 0) {
            return response()->json([
                'message' => 'İstatistikler başarıyla getirildi.',
                'statistics' => [
                    'summary' => $this->buildEmptyMyStatisticsSummary(),
                    'chart' => [
                        'period' => $periodKey,
                        'daily' => $this->buildEmptyDailyChart($periodDays),
                    ],
                    'top_posts' => [],
                    'category_performance' => [],
                ],
            ]);
        }

        $totalViews = $this->countUserPublishedInteraction(PostView::query(), $userId);
        $totalLikes = $this->countUserPublishedInteraction(PostLike::query(), $userId);
        $totalRootComments = $this->countUserPublishedComments($userId, rootOnly: true);
        $totalReplies = $this->countUserPublishedComments($userId, rootOnly: false);
        $totalComments = $totalRootComments + $totalReplies;
        $totalEngagement = $totalLikes + $totalComments;

        $averageViews = (int) round($totalViews / $publishedPostsCount);
        $averageLikes = (int) round($totalLikes / $publishedPostsCount);
        $averageComments = (int) round($totalComments / $publishedPostsCount);
        $engagementRate = $totalViews > 0
            ? round(($totalEngagement / $totalViews) * 100, 2)
            : 0.0;

        $chartStartDate = Carbon::today()->subDays($periodDays - 1)->startOfDay();
        $chartEndDate = Carbon::today()->endOfDay();

        $dailyViews = $this->buildDailyInteractionCounts(
            PostView::query(),
            $userId,
            $chartStartDate,
            $chartEndDate,
        );

        $dailyLikes = $this->buildDailyInteractionCounts(
            PostLike::query(),
            $userId,
            $chartStartDate,
            $chartEndDate,
        );

        $dailyComments = $this->buildDailyCommentCounts(
            $userId,
            $chartStartDate,
            $chartEndDate,
        );

        $dailyChart = $this->mergeDailyChartSeries(
            $periodDays,
            $chartStartDate,
            $dailyViews,
            $dailyLikes,
            $dailyComments,
        );

        $topPosts = Post::query()
            ->with('category:id,name')
            ->withCount(['views', 'likes', 'comments'])
            ->where('user_id', $userId)
            ->where('status', 'published')
            ->orderByRaw('(likes_count + comments_count) DESC')
            ->orderByDesc('views_count')
            ->orderByDesc('likes_count')
            ->orderByDesc('comments_count')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (Post $post) {
                $viewsCount = (int) $post->views_count;
                $likesCount = (int) $post->likes_count;
                $commentsCount = (int) $post->comments_count;
                $engagementCount = $likesCount + $commentsCount;

                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'category' => $post->category
                        ? [
                            'id' => $post->category->id,
                            'name' => $post->category->name,
                        ]
                        : null,
                    'views_count' => $viewsCount,
                    'likes_count' => $likesCount,
                    'comments_count' => $commentsCount,
                    'engagement_count' => $engagementCount,
                    'engagement_rate' => $viewsCount > 0
                        ? round(($engagementCount / $viewsCount) * 100, 2)
                        : 0.0,
                    // published_at kolonu yok; yayın tarihi proxy olarak updated_at kullanılıyor.
                    'published_at' => $post->updated_at,
                ];
            })
            ->values()
            ->all();

        $categoryPerformance = $this->buildUserCategoryPerformance($userId);

        return response()->json([
            'message' => 'İstatistikler başarıyla getirildi.',
            'statistics' => [
                'summary' => [
                    'published_posts_count' => $publishedPostsCount,
                    'total_views' => $totalViews,
                    'total_likes' => $totalLikes,
                    'total_comments' => $totalComments,
                    'total_root_comments' => $totalRootComments,
                    'total_replies' => $totalReplies,
                    'total_engagement' => $totalEngagement,
                    'average_views' => $averageViews,
                    'average_likes' => $averageLikes,
                    'average_comments' => $averageComments,
                    'engagement_rate' => $engagementRate,
                ],
                'chart' => [
                    'period' => $periodKey,
                    'daily' => $dailyChart,
                ],
                'top_posts' => $topPosts,
                'category_performance' => $categoryPerformance,
            ],
        ]);
    }

    private function resolveStatisticsPeriodDays(?string $period): int
    {
        return match ($period) {
            '7d' => 7,
            '30d' => 30,
            default => 30,
        };
    }

    /**
     * @return array<string, int|float>
     */
    private function buildEmptyMyStatisticsSummary(): array
    {
        return [
            'published_posts_count' => 0,
            'total_views' => 0,
            'total_likes' => 0,
            'total_comments' => 0,
            'total_root_comments' => 0,
            'total_replies' => 0,
            'total_engagement' => 0,
            'average_views' => 0,
            'average_likes' => 0,
            'average_comments' => 0,
            'engagement_rate' => 0.0,
        ];
    }

    /**
     * @return array<int, array{date: string, views: int, likes: int, comments: int}>
     */
    private function buildEmptyDailyChart(int $periodDays): array
    {
        $chartStartDate = Carbon::today()->subDays($periodDays - 1);

        return $this->mergeDailyChartSeries(
            $periodDays,
            $chartStartDate,
            collect(),
            collect(),
            collect(),
        );
    }

    private function countUserPublishedInteraction(
        \Illuminate\Database\Eloquent\Builder $interactionQuery,
        int $userId,
    ): int {
        $table = $interactionQuery->getModel()->getTable();

        return (int) $interactionQuery
            ->join('posts', "{$table}.post_id", '=', 'posts.id')
            ->where('posts.user_id', $userId)
            ->where('posts.status', 'published')
            ->count();
    }

    private function countUserPublishedComments(int $userId, bool $rootOnly): int
    {
        $query = PostComment::query()
            ->join('posts', 'post_comments.post_id', '=', 'posts.id')
            ->where('posts.user_id', $userId)
            ->where('posts.status', 'published');

        if ($rootOnly) {
            $query->whereNull('post_comments.parent_id');
        } else {
            $query->whereNotNull('post_comments.parent_id');
        }

        return (int) $query->count();
    }

    /**
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function buildDailyInteractionCounts(
        \Illuminate\Database\Eloquent\Builder $interactionQuery,
        int $userId,
        Carbon $chartStartDate,
        Carbon $chartEndDate,
    ): \Illuminate\Support\Collection {
        $table = $interactionQuery->getModel()->getTable();

        return $interactionQuery
            ->join('posts', "{$table}.post_id", '=', 'posts.id')
            ->where('posts.user_id', $userId)
            ->where('posts.status', 'published')
            ->whereBetween("{$table}.created_at", [$chartStartDate, $chartEndDate])
            ->selectRaw("DATE({$table}.created_at) as activity_date, COUNT(*) as total")
            ->groupBy('activity_date')
            ->pluck('total', 'activity_date')
            ->map(fn ($count) => (int) $count);
    }

    /**
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function buildDailyCommentCounts(
        int $userId,
        Carbon $chartStartDate,
        Carbon $chartEndDate,
    ): \Illuminate\Support\Collection {
        return PostComment::query()
            ->join('posts', 'post_comments.post_id', '=', 'posts.id')
            ->where('posts.user_id', $userId)
            ->where('posts.status', 'published')
            ->whereBetween('post_comments.created_at', [$chartStartDate, $chartEndDate])
            ->selectRaw('DATE(post_comments.created_at) as activity_date, COUNT(*) as total')
            ->groupBy('activity_date')
            ->pluck('total', 'activity_date')
            ->map(fn ($count) => (int) $count);
    }

    /**
     * @return array<int, array{date: string, views: int, likes: int, comments: int}>
     */
    private function mergeDailyChartSeries(
        int $periodDays,
        Carbon $chartStartDate,
        \Illuminate\Support\Collection $dailyViews,
        \Illuminate\Support\Collection $dailyLikes,
        \Illuminate\Support\Collection $dailyComments,
    ): array {
        $dailyChart = [];

        for ($dayOffset = 0; $dayOffset < $periodDays; $dayOffset++) {
            $date = $chartStartDate->copy()->addDays($dayOffset)->format('Y-m-d');

            $dailyChart[] = [
                'date' => $date,
                'views' => (int) ($dailyViews[$date] ?? 0),
                'likes' => (int) ($dailyLikes[$date] ?? 0),
                'comments' => (int) ($dailyComments[$date] ?? 0),
            ];
        }

        return $dailyChart;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildUserCategoryPerformance(int $userId): array
    {
        $postsByCategory = Post::query()
            ->where('user_id', $userId)
            ->where('status', 'published')
            ->selectRaw('COALESCE(category_id, 0) as category_key')
            ->selectRaw('COUNT(*) as posts_count')
            ->groupBy('category_key')
            ->pluck('posts_count', 'category_key')
            ->map(fn ($count) => (int) $count);

        if ($postsByCategory->isEmpty()) {
            return [];
        }

        $viewsByCategory = $this->aggregatePublishedMetricByCategory(
            PostView::query(),
            $userId,
        );

        $likesByCategory = $this->aggregatePublishedMetricByCategory(
            PostLike::query(),
            $userId,
        );

        $commentsByCategory = PostComment::query()
            ->join('posts', 'post_comments.post_id', '=', 'posts.id')
            ->where('posts.user_id', $userId)
            ->where('posts.status', 'published')
            ->selectRaw('COALESCE(posts.category_id, 0) as category_key')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('category_key')
            ->pluck('total', 'category_key')
            ->map(fn ($count) => (int) $count);

        $categoryIds = $postsByCategory->keys()
            ->filter(fn ($categoryKey) => (int) $categoryKey > 0)
            ->map(fn ($categoryKey) => (int) $categoryKey)
            ->values()
            ->all();

        $categoryNames = Category::query()
            ->whereIn('id', $categoryIds)
            ->pluck('name', 'id');

        $performance = [];

        foreach ($postsByCategory as $categoryKey => $postsCount) {
            $categoryId = (int) $categoryKey;
            $viewsCount = (int) ($viewsByCategory[$categoryKey] ?? 0);
            $likesCount = (int) ($likesByCategory[$categoryKey] ?? 0);
            $commentsCount = (int) ($commentsByCategory[$categoryKey] ?? 0);
            $engagementCount = $likesCount + $commentsCount;

            $performance[] = [
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'category_name' => $categoryId > 0
                    ? ($categoryNames[$categoryId] ?? 'Kategorisiz')
                    : 'Kategorisiz',
                'posts_count' => $postsCount,
                'views_count' => $viewsCount,
                'likes_count' => $likesCount,
                'comments_count' => $commentsCount,
                'engagement_count' => $engagementCount,
                'engagement_rate' => $viewsCount > 0
                    ? round(($engagementCount / $viewsCount) * 100, 2)
                    : 0.0,
            ];
        }

        usort($performance, function (array $firstCategory, array $secondCategory) {
            if ($firstCategory['views_count'] === $secondCategory['views_count']) {
                return $secondCategory['posts_count'] <=> $firstCategory['posts_count'];
            }

            return $secondCategory['views_count'] <=> $firstCategory['views_count'];
        });

        return $performance;
    }

    /**
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function aggregatePublishedMetricByCategory(
        \Illuminate\Database\Eloquent\Builder $interactionQuery,
        int $userId,
    ): \Illuminate\Support\Collection {
        $table = $interactionQuery->getModel()->getTable();

        return $interactionQuery
            ->join('posts', "{$table}.post_id", '=', 'posts.id')
            ->where('posts.user_id', $userId)
            ->where('posts.status', 'published')
            ->selectRaw('COALESCE(posts.category_id, 0) as category_key')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('category_key')
            ->pluck('total', 'category_key')
            ->map(fn ($count) => (int) $count);
    }

    /**
 * Onay bekleyen yazıları listeler.
 */
    public function pending(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, (int) $request->input('per_page', 9)));

        $posts = Post::with(['user', 'category'])
           ->withCount('views')
           ->where('status', 'pending')
           ->latest()
           ->paginate($perPage);

        return response()->json([
            'message' => 'Onay bekleyen yazılar başarıyla listelendi.',
            'posts' => PostResource::collection($posts->items()),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Admin dashboard özet verilerini döndürür.
     */
    public function dashboard(): JsonResponse
    {
        $postCounts = Post::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $postsPublished = (int) ($postCounts['published'] ?? 0);
        $postsPending = (int) ($postCounts['pending'] ?? 0);
        $postsRejected = (int) ($postCounts['rejected'] ?? 0);
        $postsDraft = (int) ($postCounts['draft'] ?? 0);

        $recentPendingPosts = Post::with(['user', 'category'])
            ->withCount('views')
            ->where('status', 'pending')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (Post $post) {
                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'status' => $post->status,
                    'author' => $post->user
                        ? [
                            'id' => $post->user->id,
                            'name' => $post->user->name,
                        ]
                        : null,
                    'category' => $post->category
                        ? [
                            'id' => $post->category->id,
                            'name' => $post->category->name,
                        ]
                        : null,
                    'views_count' => (int) $post->views_count,
                    'created_at' => $post->created_at,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'message' => 'Dashboard verileri başarıyla getirildi.',
            'summary' => [
                'users_total' => User::count(),
                'posts_total' => Post::count(),
                'posts_published' => $postsPublished,
                'posts_pending' => $postsPending,
                'posts_rejected' => $postsRejected,
                'posts_draft' => $postsDraft,
            ],
            'recent_pending_posts' => $recentPendingPosts,
        ]);
    }

    /**
    * Yazıyı onaylar.
    */
    public function approve(string $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        
        $post->update([
            'status' => 'published',
            'rejection_reason' => null,
            ]);

            $post->load(['user', 'category']);
            $post->loadCount('views');

        return response()->json([
            'message' => 'Yazı başarıyla onaylandı.',
            'post' => new PostResource($post),
            ]);
    }

    /**
    * Yazıyı reddeder.
    */
    public function reject(
        RejectPostRequest $request,
        string $id
    ): JsonResponse {
        $post = Post::findOrFail($id);
    
        $post->update([
            'status' => 'rejected',
            'rejection_reason' => $request->validated()['rejection_reason'],
        ]);
    
        $post->load(['user', 'category']);
        $post->loadCount('views');
    
        return response()->json([
            'message' => 'Yazı reddedildi.',
            'post' => new PostResource($post),
        ]);
    }

    /**
     * Yazıyı siler.
     */
    public function destroy(string $id): JsonResponse
    {
        $post = Post::findOrFail($id);

        Gate::authorize('delete', $post);

        if ($post->featured_image) {
            Storage::disk('public')->delete(
                $post->featured_image
            );
        }

        $post->delete();

        return response()->json([
            'message' => 'Yazı başarıyla silindi.',
        ]);
    }
}