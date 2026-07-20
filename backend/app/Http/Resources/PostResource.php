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
            'rejection_reason' => $this->rejection_reason,
            
            'author' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                ],

            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            ];
    }
}
