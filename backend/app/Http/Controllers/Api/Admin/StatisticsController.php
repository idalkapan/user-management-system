<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\PostView;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    /**
     * Sistem geneli admin istatistiklerini döndürür.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $periodDays = $this->resolvePeriodDays($request->query('period'));
            $periodKey = $periodDays === 7 ? '7d' : '30d';

            $today = Carbon::today();
            $chartStartDate = $today->copy()->subDays($periodDays - 1)->startOfDay();
            $chartEndDate = $today->copy()->endOfDay();

            $totalViews = $this->countPublishedInteraction(PostView::query());
            $totalLikes = $this->countPublishedInteraction(PostLike::query());
            $totalComments = $this->countPublishedComments();
            $totalEngagement = $totalLikes + $totalComments;

            $periodViews = (int) PostView::query()
                ->join('posts', 'post_views.post_id', '=', 'posts.id')
                ->where('posts.status', 'published')
                ->whereBetween('post_views.created_at', [$chartStartDate, $chartEndDate])
                ->count();

            $averageEngagementRate = $totalViews > 0
                ? round(($totalEngagement / $totalViews) * 100, 2)
                : 0.0;

            $periodNewUsers = User::query()
                ->where('role', 'user')
                ->whereBetween('created_at', [$chartStartDate, $chartEndDate])
                ->count();

            // published_at kolonu olmadığı için yayın tarihi vekili olarak updated_at kullanılıyor.
            $periodPublishedPosts = Post::query()
                ->where('status', 'published')
                ->whereBetween('updated_at', [$chartStartDate, $chartEndDate])
                ->count();

            // published_at kolonu olmadığı için seçilen dönemde yayınlanmış yazı vekili olarak updated_at kullanılıyor.
            $periodActiveAuthors = User::query()
                ->where('role', 'user')
                ->whereHas('posts', function ($query) use ($chartStartDate, $chartEndDate) {
                    $query->where('status', 'published')
                        ->whereBetween('updated_at', [$chartStartDate, $chartEndDate]);
                })
                ->count();

            $engagementChart = $this->buildEngagementChart(
                $periodDays,
                $periodKey,
                $chartStartDate,
                $chartEndDate,
            );

            $growthChart = $this->buildGrowthChart(
                $periodDays,
                $periodKey,
                $chartStartDate,
                $chartEndDate,
            );

            return response()->json([
                'message' => 'Admin istatistikleri başarıyla getirildi.',
                'statistics' => [
                    'summary' => [
                        'period_views' => $periodViews,
                        'period_new_users' => $periodNewUsers,
                        'period_published_posts' => $periodPublishedPosts,
                        'period_active_authors' => $periodActiveAuthors,
                        'total_likes' => $totalLikes,
                        'total_comments' => $totalComments,
                        'total_engagement' => $totalEngagement,
                        'average_engagement_rate' => $averageEngagementRate,
                    ],
                    'engagement_chart' => $engagementChart,
                    'growth_chart' => $growthChart,
                    'status_distribution' => $this->buildStatusDistribution(),
                    'top_authors' => $this->getTopAuthors(),
                    'top_posts' => $this->getTopPosts(),
                    'category_performance' => $this->getCategoryPerformance(),
                ],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'İstatistikler getirilirken bir hata oluştu.',
            ], 500);
        }
    }

    private function resolvePeriodDays(?string $period): int
    {
        return match ($period) {
            '7d' => 7,
            '30d' => 30,
            default => 30,
        };
    }

    private function countPublishedInteraction(\Illuminate\Database\Eloquent\Builder $interactionQuery): int
    {
        $table = $interactionQuery->getModel()->getTable();

        return (int) $interactionQuery
            ->join('posts', "{$table}.post_id", '=', 'posts.id')
            ->where('posts.status', 'published')
            ->count();
    }

    private function countPublishedComments(): int
    {
        return (int) PostComment::query()
            ->join('posts', 'post_comments.post_id', '=', 'posts.id')
            ->where('posts.status', 'published')
            ->count();
    }

    /**
     * @return array{period: string, daily: array<int, array{date: string, views: int, likes: int, comments: int}>}
     */
    private function buildEngagementChart(
        int $periodDays,
        string $periodKey,
        Carbon $chartStartDate,
        Carbon $chartEndDate,
    ): array {
        $dailyViews = $this->buildDailyPublishedInteractionCounts(
            PostView::query(),
            $chartStartDate,
            $chartEndDate,
        );

        $dailyLikes = $this->buildDailyPublishedInteractionCounts(
            PostLike::query(),
            $chartStartDate,
            $chartEndDate,
        );

        $dailyComments = PostComment::query()
            ->join('posts', 'post_comments.post_id', '=', 'posts.id')
            ->where('posts.status', 'published')
            ->whereBetween('post_comments.created_at', [$chartStartDate, $chartEndDate])
            ->selectRaw('DATE(post_comments.created_at) as activity_date, COUNT(*) as total')
            ->groupBy('activity_date')
            ->pluck('total', 'activity_date')
            ->map(fn ($count) => (int) $count);

        return [
            'period' => $periodKey,
            'daily' => $this->mergeDailyChartSeries(
                $periodDays,
                $chartStartDate,
                $dailyViews,
                $dailyLikes,
                $dailyComments,
            ),
        ];
    }

    /**
     * @return array{period: string, daily: array<int, array{date: string, new_users: int, new_posts: int}>}
     */
    private function buildGrowthChart(
        int $periodDays,
        string $periodKey,
        Carbon $chartStartDate,
        Carbon $chartEndDate,
    ): array {
        $dailyNewUsers = User::query()
            ->where('role', 'user')
            ->whereBetween('created_at', [$chartStartDate, $chartEndDate])
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as total')
            ->groupBy('activity_date')
            ->pluck('total', 'activity_date')
            ->map(fn ($count) => (int) $count);

        $dailyNewPosts = Post::query()
            ->whereBetween('created_at', [$chartStartDate, $chartEndDate])
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as total')
            ->groupBy('activity_date')
            ->pluck('total', 'activity_date')
            ->map(fn ($count) => (int) $count);

        $daily = [];

        for ($dayOffset = 0; $dayOffset < $periodDays; $dayOffset++) {
            $date = $chartStartDate->copy()->addDays($dayOffset)->format('Y-m-d');

            $daily[] = [
                'date' => $date,
                'new_users' => (int) ($dailyNewUsers[$date] ?? 0),
                'new_posts' => (int) ($dailyNewPosts[$date] ?? 0),
            ];
        }

        return [
            'period' => $periodKey,
            'daily' => $daily,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function buildDailyPublishedInteractionCounts(
        \Illuminate\Database\Eloquent\Builder $interactionQuery,
        Carbon $chartStartDate,
        Carbon $chartEndDate,
    ): \Illuminate\Support\Collection {
        $table = $interactionQuery->getModel()->getTable();

        return $interactionQuery
            ->join('posts', "{$table}.post_id", '=', 'posts.id')
            ->where('posts.status', 'published')
            ->whereBetween("{$table}.created_at", [$chartStartDate, $chartEndDate])
            ->selectRaw("DATE({$table}.created_at) as activity_date, COUNT(*) as total")
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
     * @return array{published: int, pending: int, rejected: int, draft: int}
     */
    private function buildStatusDistribution(): array
    {
        $postCounts = Post::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'published' => (int) ($postCounts['published'] ?? 0),
            'pending' => (int) ($postCounts['pending'] ?? 0),
            'rejected' => (int) ($postCounts['rejected'] ?? 0),
            'draft' => (int) ($postCounts['draft'] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTopAuthors(): array
    {
        $postsSub = DB::table('posts')
            ->select('user_id')
            ->selectRaw('COUNT(*) as published_posts_count')
            ->where('status', 'published')
            ->groupBy('user_id');

        $viewsSub = DB::table('post_views')
            ->join('posts', 'post_views.post_id', '=', 'posts.id')
            ->where('posts.status', 'published')
            ->select('posts.user_id')
            ->selectRaw('COUNT(*) as total_views')
            ->groupBy('posts.user_id');

        $likesSub = DB::table('post_likes')
            ->join('posts', 'post_likes.post_id', '=', 'posts.id')
            ->where('posts.status', 'published')
            ->select('posts.user_id')
            ->selectRaw('COUNT(*) as total_likes')
            ->groupBy('posts.user_id');

        $commentsSub = DB::table('post_comments')
            ->join('posts', 'post_comments.post_id', '=', 'posts.id')
            ->where('posts.status', 'published')
            ->select('posts.user_id')
            ->selectRaw('COUNT(*) as total_comments')
            ->groupBy('posts.user_id');

        return User::query()
            ->where('users.role', 'user')
            ->joinSub($postsSub, 'post_stats', 'post_stats.user_id', '=', 'users.id')
            ->leftJoinSub($viewsSub, 'view_stats', 'view_stats.user_id', '=', 'users.id')
            ->leftJoinSub($likesSub, 'like_stats', 'like_stats.user_id', '=', 'users.id')
            ->leftJoinSub($commentsSub, 'comment_stats', 'comment_stats.user_id', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                'post_stats.published_posts_count',
            )
            ->selectRaw('COALESCE(view_stats.total_views, 0) as total_views')
            ->selectRaw('COALESCE(like_stats.total_likes, 0) as total_likes')
            ->selectRaw('COALESCE(comment_stats.total_comments, 0) as total_comments')
            ->selectRaw(
                '(COALESCE(like_stats.total_likes, 0) + COALESCE(comment_stats.total_comments, 0)) as total_engagement'
            )
            ->orderByDesc('total_engagement')
            ->orderByDesc('total_views')
            ->orderByDesc('published_posts_count')
            ->orderByDesc('users.id')
            ->limit(10)
            ->get()
            ->map(function (User $user) {
                $publishedPostsCount = (int) $user->published_posts_count;
                $totalViews = (int) $user->total_views;
                $totalLikes = (int) $user->total_likes;
                $totalComments = (int) $user->total_comments;
                $totalEngagement = (int) $user->total_engagement;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'published_posts_count' => $publishedPostsCount,
                    'total_views' => $totalViews,
                    'total_likes' => $totalLikes,
                    'total_comments' => $totalComments,
                    'total_engagement' => $totalEngagement,
                    'engagement_rate' => $totalViews > 0
                        ? round(($totalEngagement / $totalViews) * 100, 2)
                        : 0.0,
                    'average_engagement_per_post' => $publishedPostsCount > 0
                        ? round($totalEngagement / $publishedPostsCount, 2)
                        : 0.0,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTopPosts(): array
    {
        return Post::query()
            ->with(['user:id,name', 'category:id,name'])
            ->withCount(['views', 'likes', 'comments'])
            ->where('status', 'published')
            ->orderByRaw('(likes_count + comments_count) DESC')
            ->orderByDesc('views_count')
            ->orderByDesc('likes_count')
            ->orderByDesc('comments_count')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (Post $post) {
                $viewsCount = (int) $post->views_count;
                $likesCount = (int) $post->likes_count;
                $commentsCount = (int) $post->comments_count;
                $engagementCount = $likesCount + $commentsCount;

                return [
                    'id' => $post->id,
                    'title' => $post->title,
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
                    'views_count' => $viewsCount,
                    'likes_count' => $likesCount,
                    'comments_count' => $commentsCount,
                    'engagement_count' => $engagementCount,
                    'engagement_rate' => $viewsCount > 0
                        ? round(($engagementCount / $viewsCount) * 100, 2)
                        : 0.0,
                    // published_at kolonu olmadığı için geçici yayın tarihi yaklaşımı.
                    'published_at' => $post->updated_at,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCategoryPerformance(): array
    {
        $postsSub = DB::table('posts')
            ->select('category_id')
            ->selectRaw('COUNT(*) as published_posts_count')
            ->where('status', 'published')
            ->groupBy('category_id');

        $viewsSub = DB::table('post_views')
            ->join('posts', 'post_views.post_id', '=', 'posts.id')
            ->where('posts.status', 'published')
            ->select('posts.category_id')
            ->selectRaw('COUNT(*) as total_views')
            ->groupBy('posts.category_id');

        $likesSub = DB::table('post_likes')
            ->join('posts', 'post_likes.post_id', '=', 'posts.id')
            ->where('posts.status', 'published')
            ->select('posts.category_id')
            ->selectRaw('COUNT(*) as total_likes')
            ->groupBy('posts.category_id');

        $commentsSub = DB::table('post_comments')
            ->join('posts', 'post_comments.post_id', '=', 'posts.id')
            ->where('posts.status', 'published')
            ->select('posts.category_id')
            ->selectRaw('COUNT(*) as total_comments')
            ->groupBy('posts.category_id');

        return Category::query()
            ->joinSub($postsSub, 'post_stats', 'post_stats.category_id', '=', 'categories.id')
            ->leftJoinSub($viewsSub, 'view_stats', 'view_stats.category_id', '=', 'categories.id')
            ->leftJoinSub($likesSub, 'like_stats', 'like_stats.category_id', '=', 'categories.id')
            ->leftJoinSub($commentsSub, 'comment_stats', 'comment_stats.category_id', '=', 'categories.id')
            ->select(
                'categories.id',
                'categories.name',
                'post_stats.published_posts_count',
            )
            ->selectRaw('COALESCE(view_stats.total_views, 0) as total_views')
            ->selectRaw('COALESCE(like_stats.total_likes, 0) as total_likes')
            ->selectRaw('COALESCE(comment_stats.total_comments, 0) as total_comments')
            ->selectRaw(
                '(COALESCE(like_stats.total_likes, 0) + COALESCE(comment_stats.total_comments, 0)) as total_engagement'
            )
            ->orderByDesc('total_engagement')
            ->orderByDesc('total_views')
            ->orderByDesc('published_posts_count')
            ->orderByDesc('categories.id')
            ->limit(10)
            ->get()
            ->map(function (Category $category) {
                $totalViews = (int) $category->total_views;
                $totalLikes = (int) $category->total_likes;
                $totalComments = (int) $category->total_comments;
                $totalEngagement = (int) $category->total_engagement;

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'published_posts_count' => (int) $category->published_posts_count,
                    'total_views' => $totalViews,
                    'total_likes' => $totalLikes,
                    'total_comments' => $totalComments,
                    'total_engagement' => $totalEngagement,
                    'engagement_rate' => $totalViews > 0
                        ? round(($totalEngagement / $totalViews) * 100, 2)
                        : 0.0,
                ];
            })
            ->all();
    }
}
