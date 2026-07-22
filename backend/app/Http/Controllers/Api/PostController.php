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

        $query = Post::with(['user', 'category'])->withCount('views');

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
                
        $posts = $query
            ->latest()
            ->get();

        
    
        return response()->json([
            'message' => 'Yazılar başarıyla listelendi.',
            'posts' => PostResource::collection($posts),
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
    public function show(string $id): JsonResponse
    {
        $post = Post::with(['user', 'category'])
            ->withCount('views')
            ->findOrFail($id);

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
        $posts = Post::with(['user', 'category'])
           ->withCount('views')
           ->where('user_id', $request->user()->id)
           ->latest()
           ->get();

        return response()->json([
            'message' => 'Yazılarınız başarıyla listelendi.',
            'posts' => PostResource::collection($posts),
            ]);
    }

    /**
     * Giriş yapan kullanıcının yayınlanmış yazılarına ait istatistikleri döndürür.
     */
    public function myStatistics(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $publishedPosts = Post::with('category')
            ->withCount('views')
            ->where('user_id', $userId)
            ->where('status', 'published')
            ->get();

        $publishedPostsCount = $publishedPosts->count();
        $totalViews = (int) $publishedPosts->sum('views_count');
        $averageViews = $publishedPostsCount > 0
            ? (int) round($totalViews / $publishedPostsCount)
            : 0;

        $categoriesCount = $publishedPosts
            ->pluck('category_id')
            ->filter()
            ->unique()
            ->count();

        $chartStartDate = Carbon::today()->subDays(29);
        $chartEndDate = Carbon::today();

        $dailyViewCounts = PostView::query()
            ->join('posts', 'post_views.post_id', '=', 'posts.id')
            ->where('posts.user_id', $userId)
            ->where('posts.status', 'published')
            ->whereDate('post_views.created_at', '>=', $chartStartDate)
            ->whereDate('post_views.created_at', '<=', $chartEndDate)
            ->selectRaw('DATE(post_views.created_at) as view_date, COUNT(*) as views')
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

        $mostViewedPost = $publishedPosts
            ->sort(function (Post $firstPost, Post $secondPost) {
                if ($firstPost->views_count === $secondPost->views_count) {
                    return $firstPost->id <=> $secondPost->id;
                }

                return $secondPost->views_count <=> $firstPost->views_count;
            })
            ->first();

        $leastViewedPost = $publishedPosts
            ->sort(function (Post $firstPost, Post $secondPost) {
                if ($firstPost->views_count === $secondPost->views_count) {
                    return $firstPost->created_at <=> $secondPost->created_at;
                }

                return $firstPost->views_count <=> $secondPost->views_count;
            })
            ->first();

        $latestPublishedPost = $publishedPosts
            ->sortByDesc('updated_at')
            ->first();

        $mostUsedCategory = null;
        $categoryUsage = $publishedPosts
            ->filter(fn (Post $post) => $post->category_id !== null && $post->category)
            ->groupBy('category_id')
            ->map(function ($postsInCategory) {
                $category = $postsInCategory->first()->category;

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'posts_count' => $postsInCategory->count(),
                ];
            })
            ->sort(function (array $firstCategory, array $secondCategory) {
                if ($firstCategory['posts_count'] === $secondCategory['posts_count']) {
                    return strcmp($firstCategory['name'], $secondCategory['name']);
                }

                return $secondCategory['posts_count'] <=> $firstCategory['posts_count'];
            })
            ->first();

        if ($categoryUsage) {
            $mostUsedCategory = $categoryUsage;
        }

        $posts = $publishedPosts
            ->sortByDesc('views_count')
            ->values()
            ->map(function (Post $post) {
                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'category' => $post->category
                        ? [
                            'id' => $post->category->id,
                            'name' => $post->category->name,
                        ]
                        : null,
                    'views_count' => (int) $post->views_count,
                    'published_at' => $post->updated_at,
                ];
            })
            ->all();

        return response()->json([
            'message' => 'İstatistikler başarıyla getirildi.',
            'statistics' => [
                'summary' => [
                    'total_views' => $totalViews,
                    'average_views' => $averageViews,
                    'published_posts_count' => $publishedPostsCount,
                    'categories_count' => $categoriesCount,
                ],
                'chart' => [
                    'period' => '30_days',
                    'daily_views' => $dailyViews,
                ],
                'performance' => [
                    'most_viewed_post' => $mostViewedPost
                        ? [
                            'id' => $mostViewedPost->id,
                            'title' => $mostViewedPost->title,
                            'views_count' => (int) $mostViewedPost->views_count,
                        ]
                        : null,
                    'least_viewed_post' => $leastViewedPost
                        ? [
                            'id' => $leastViewedPost->id,
                            'title' => $leastViewedPost->title,
                            'views_count' => (int) $leastViewedPost->views_count,
                        ]
                        : null,
                    'latest_published_post' => $latestPublishedPost
                        ? [
                            'id' => $latestPublishedPost->id,
                            'title' => $latestPublishedPost->title,
                            'published_at' => $latestPublishedPost->updated_at,
                        ]
                        : null,
                    'most_used_category' => $mostUsedCategory,
                ],
                'posts' => $posts,
            ],
        ]);
    }

    /**
 * Onay bekleyen yazıları listeler.
 */
    public function pending(): JsonResponse
    {
        $posts = Post::with(['user', 'category'])
           ->withCount('views')
           ->where('status', 'pending')
           ->latest()
           ->get();

        return response()->json([
            'message' => 'Onay bekleyen yazılar başarıyla listelendi.',
            'posts' => PostResource::collection($posts),
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
                'categories_total' => Category::count(),
                'categories_active' => Category::where('is_active', true)->count(),
                'views_total' => PostView::count(),
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