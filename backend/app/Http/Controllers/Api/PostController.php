<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\PostResource;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $posts = Post::with('user')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Yazılar başarıyla listelendi.',
            'posts' => PostResource::collection($posts),
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $post = Post::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']) . '-' . time(),
            'content' => $validated['content'],
            'featured_image' => $validated['featured_image'] ?? null,
            'status' => $validated['status'],
            ]);

        $post->load('user');

        return response()->json([
            'message' => 'Yazı başarıyla oluşturuldu.',
            'post' => new PostResource($post),
            ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
         $post = Post::with('user')->findOrFail($id);

        return response()->json([
            'message' => 'Yazı başarıyla getirildi.',
            'post' => new PostResource($post),
            ]);
    }

    /**
     * Update the specified resource in storage.
     */
    
    public function update(UpdatePostRequest $request, string $id): JsonResponse
    {
        $post = Post::findOrFail($id);

        Gate::authorize('update', $post);

        $validated = $request->validated();

        $post->update([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']) . '-' . time(),
            'content' => $validated['content'],
            'featured_image' => $validated['featured_image'] ?? null,
            'status' => $validated['status'],
            ]);

        $post->load('user');

        return response()->json([
            'message' => 'Yazı başarıyla güncellendi.',
            'post' => new PostResource($post),
            ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        
        Gate::authorize('delete', $post);

        $post->delete();
        
        return response()->json([
            'message' => 'Yazı başarıyla silindi.',
            ]);
    }
}
