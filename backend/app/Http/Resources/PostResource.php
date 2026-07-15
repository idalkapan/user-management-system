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
            'featured_image' => $this->featured_image,
            'status' => $this->status,
            
            'author' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                ],
                
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            ];
    }
}
