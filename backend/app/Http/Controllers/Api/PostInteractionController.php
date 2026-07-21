<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostInteractionController extends Controller
{
    /**
     * Yazı görüntülenmesini kaydeder.
     */
    public function recordView(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();

        if ($post->status !== 'published') {
            return response()->json([
                'message' => 'Yalnızca yayınlanmış yazılar görüntülenme kaydı alabilir.',
            ], 403);
        }

        if ($post->user_id === $user->id) {
            return response()->json([
                'message' => 'Kendi yazınız için görüntülenme kaydı oluşturulmaz.',
            ]);
        }

        $recentViewExists = PostView::query()
            ->where('post_id', $post->id)
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->exists();

        if ($recentViewExists) {
            return response()->json([
                'message' => 'Bu görüntülenme son 2 dakika içinde zaten kaydedildi.',
            ]);
        }

        PostView::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Görüntülenme başarıyla kaydedildi.',
        ], 201);
    }
}
