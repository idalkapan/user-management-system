<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'featured_image' => $this->featured_image
               ? asset('storage/' . $this->featured_image)
               : null,
            'status' => $this->status,
            'rejection_reason' => $this->when(
                $this->canViewRejectionReason($request),
                $this->rejection_reason,
            ),

            'author' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                ],

            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),

            'views_count' => $this->whenCounted('views', fn () => (int) $this->views_count),

            'likes_count' => $this->whenCounted('likes', fn () => (int) $this->likes_count),

            'comments_count' => $this->whenCounted('comments', fn () => (int) $this->comments_count),

            'is_liked_by_current_user' => $this->resolveIsLikedByCurrentUser($request),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            ];
    }

    private function resolveIsLikedByCurrentUser(Request $request): bool
    {
        $user = $request->user();

        if (!$user || $user->role !== 'user') {
            return false;
        }

        return (bool) ($this->is_liked_by_current_user ?? false);
    }

    private function canViewRejectionReason(Request $request): bool
    {
        $user = $request->user();

        if (!$user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return $user->id === $this->user_id;
    }
}
