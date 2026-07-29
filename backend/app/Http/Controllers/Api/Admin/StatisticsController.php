<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class StatisticsController extends Controller
{
    private const STATUS_LABELS = [
        'draft' => 'Taslak',
        'pending' => 'Onay Bekleyen',
        'published' => 'Yayınlanan',
        'rejected' => 'Reddedilen',
    ];

    /**
     * Sistem geneli admin istatistiklerini döndürür.
     */
    public function index(): JsonResponse
    {
        try {
            $today = Carbon::today();
            $monthStart = $today->copy()->startOfMonth();
            $monthEnd = $today->copy()->endOfMonth();

            $viewsToday = PostView::query()
                ->whereDate('created_at', $today)
                ->count();

            $sevenDayStart = $today->copy()->subDays(6)->startOfDay();
            $sevenDayEnd = $today->copy()->endOfDay();

            $viewsLastSevenDays = PostView::query()
                ->whereBetween('created_at', [$sevenDayStart, $sevenDayEnd])
                ->count();

            $usersAddedThisMonth = User::query()
                ->whereDate('created_at', '>=', $monthStart)
                ->whereDate('created_at', '<=', $monthEnd)
                ->count();

            // published_at kolonu olmadığı için yayın tarihi olarak updated_at kullanılıyor.
            $postsPublishedThisMonth = Post::query()
                ->where('status', 'published')
                ->whereDate('updated_at', '>=', $monthStart)
                ->whereDate('updated_at', '<=', $monthEnd)
                ->count();

            $postsPending = Post::query()
                ->where('status', 'pending')
                ->count();

            $postsRejected = Post::query()
                ->where('status', 'rejected')
                ->count();

            $chartStartDate = $today->copy()->subDays(29)->startOfDay();
            $chartEndDate = $today->copy()->endOfDay();

            $dailyViewCounts = PostView::query()
                ->whereBetween('created_at', [$chartStartDate, $chartEndDate])
                ->selectRaw('DATE(created_at) as view_date, COUNT(*) as views')
                ->groupBy('view_date')
                ->pluck('views', 'view_date');

            $dailyViews = [];

            for ($dayOffset = 0; $dayOffset < 30; $dayOffset++) {
                $date = $chartStartDate->copy()->addDays($dayOffset)->format('Y-m-d');

                $dailyViews[] = [
                    'date' => $date,
                    'views' => (int) ($dailyViewCounts[$date] ?? 0),
                ];
            }

            $statusDistribution = $this->buildStatusDistribution();

            return response()->json([
                'message' => 'Admin istatistikleri başarıyla getirildi.',
                'statistics' => [
                    'summary' => [
                        'views_today' => $viewsToday,
                        'views_last_7_days' => $viewsLastSevenDays,
                        'users_added_this_month' => $usersAddedThisMonth,
                        'posts_published_this_month' => $postsPublishedThisMonth,
                        'posts_pending' => $postsPending,
                        'posts_rejected' => $postsRejected,
                    ],
                    'chart' => [
                        'period' => '30_days',
                        'daily_views' => $dailyViews,
                    ],
                    'status_distribution' => $statusDistribution,
                    'top_posts' => $this->getTopPosts(),
                    'top_authors' => $this->getTopAuthors(),
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

    /**
     * @return array<int, array{status: string, label: string, count: int}>
     */
    private function buildStatusDistribution(): array
    {
        $postCounts = Post::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $distribution = [];

        foreach (array_keys(self::STATUS_LABELS) as $status) {
            $distribution[] = [
                'status' => $status,
                'label' => self::STATUS_LABELS[$status],
                'count' => (int) ($postCounts[$status] ?? 0),
            ];
        }

        return $distribution;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTopPosts(): array
    {
        return Post::query()
            ->with(['user:id,name', 'category:id,name'])
            ->withCount('views')
            ->where('status', 'published')
            ->orderByDesc('views_count')
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(function (Post $post) {
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
                    'views_count' => (int) $post->views_count,
                    // published_at kolonu olmadığı için geçici yayın tarihi yaklaşımı.
                    'published_at' => $post->updated_at,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTopAuthors(): array
    {
        return User::query()
            ->where('users.role', 'user')
            ->select('users.id', 'users.name')
            ->selectRaw('COUNT(DISTINCT posts.id) as published_posts_count')
            ->selectRaw('COUNT(post_views.id) as total_views')
            ->join('posts', function ($join) {
                $join->on('posts.user_id', '=', 'users.id')
                    ->where('posts.status', '=', 'published');
            })
            ->leftJoin('post_views', 'post_views.post_id', '=', 'posts.id')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_views')
            ->orderByDesc('published_posts_count')
            ->limit(10)
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'published_posts_count' => (int) $user->published_posts_count,
                    'total_views' => (int) $user->total_views,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCategoryPerformance(): array
    {
        return Category::query()
            ->select('categories.id', 'categories.name')
            ->selectRaw('COUNT(DISTINCT posts.id) as published_posts_count')
            ->selectRaw('COUNT(post_views.id) as total_views')
            ->join('posts', function ($join) {
                $join->on('posts.category_id', '=', 'categories.id')
                    ->where('posts.status', '=', 'published');
            })
            ->leftJoin('post_views', 'post_views.post_id', '=', 'posts.id')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_views')
            ->orderByDesc('published_posts_count')
            ->limit(10)
            ->get()
            ->map(function (Category $category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'published_posts_count' => (int) $category->published_posts_count,
                    'total_views' => (int) $category->total_views,
                ];
            })
            ->all();
    }
}
