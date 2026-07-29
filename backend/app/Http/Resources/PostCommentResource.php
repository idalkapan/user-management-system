<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_edited' => $this->created_at?->ne($this->updated_at) ?? false,
            'is_reply' => $this->parent_id !== null,
            'parent_id' => $this->when($this->parent_id !== null, $this->parent_id),
            'replies_count' => $this->whenCounted(
                'replies',
                fn () => (int) $this->replies_count,
            ),
            'likes_count' => $this->whenCounted(
                'likes',
                fn () => (int) $this->likes_count,
            ),
            'is_liked_by_current_user' => $this->when(
                isset($this->is_liked_by_current_user),
                (bool) $this->is_liked_by_current_user,
            ),
            'replied_to_user' => $this->when(
                $this->parent_id !== null && $this->relationLoaded('repliedToUser'),
                fn () => $this->repliedToUser
                    ? [
                        'id' => $this->repliedToUser->id,
                        'name' => $this->repliedToUser->name,
                    ]
                    : null,
            ),
            'author' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'profile_photo' => $this->user->profile_photo
                    ? asset('storage/' . $this->user->profile_photo)
                    : null,
            ],
        ];
    }
}
