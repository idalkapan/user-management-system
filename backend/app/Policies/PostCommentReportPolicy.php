<?php

namespace App\Policies;

use App\Models\PostComment;
use App\Models\User;

class PostCommentReportPolicy
{
    /**
     * Yalnızca normal kullanıcılar, başkalarının yorumlarını
     * yayımlanmış yazılarda şikâyet edebilir.
     */
    public function create(User $user, PostComment $comment): bool
    {
        if ($user->role !== 'user') {
            return false;
        }

        if ($user->id === $comment->user_id) {
            return false;
        }

        $comment->loadMissing('post');

        return $comment->post?->status === 'published';
    }
}
