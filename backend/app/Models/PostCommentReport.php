<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostCommentReport extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED_REMOVED = 'resolved_removed';

    public const STATUS_RESOLVED_KEPT = 'resolved_kept';

    public const REASONS = [
        'spam',
        'harassment',
        'hate_speech',
        'inappropriate',
        'misinformation',
        'other',
    ];

    protected $fillable = [
        'post_comment_id',
        'reported_by',
        'reason',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_note',
        'comment_content_snapshot',
        'comment_author_id_snapshot',
        'post_id_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'post_comment_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
