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