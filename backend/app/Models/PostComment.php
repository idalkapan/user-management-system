<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'replied_to_comment_id',
        'replied_to_user_id',
        'content',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(PostComment::class, 'parent_id');
    }

    public function repliedToComment(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'replied_to_comment_id');
    }

    public function repliedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_to_user_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostCommentLike::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PostCommentReport::class, 'post_comment_id');
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }
}
