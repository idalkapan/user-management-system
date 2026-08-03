<?php

namespace App\Policies;

use App\Models\PostComment;
use App\Models\PostCommentReport;
use App\Models\User;

class PostCommentReportPolicy
{
    /**
     * Yalnızca adminler şikâyetleri listeleyebilir.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Yalnızca adminler şikâyet detayını görüntüleyebilir.
     */
    public function view(User $user, PostCommentReport $report): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Yalnızca adminler bekleyen şikâyetleri sonuçlandırabilir.
     */
    public function resolve(User $user, PostCommentReport $report): bool
    {
        return $user->role === 'admin'
            && $report->status === PostCommentReport::STATUS_PENDING;
    }

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
