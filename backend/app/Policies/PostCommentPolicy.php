<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;

class PostCommentPolicy
{
    /**
     * Yalnızca kullanıcılar yayımlanmış yazılara yorum ekleyebilir.
     */
    public function create(User $user, Post $post): bool
    {
        return $user->role === 'user' && $post->status === 'published';
    }

    /**
     * Yalnızca kullanıcılar yayımlanmış yazılardaki yorumlara yanıt verebilir.
     */
    public function reply(User $user, PostComment $comment): bool
    {
        if ($user->role !== 'user') {
            return false;
        }

        return $comment->post?->status === 'published';
    }

    /**
     * Yalnızca kullanıcılar yayımlanmış yazılardaki yorumları beğenebilir.
     */
    public function like(User $user, PostComment $comment): bool
    {
        if ($user->role !== 'user') {
            return false;
        }

        return $comment->post?->status === 'published';
    }

    /**
     * Yalnızca yorum sahibi kendi yorumunu düzenleyebilir.
     */
    public function update(User $user, PostComment $comment): bool
    {
        if ($user->role !== 'user') {
            return false;
        }

        if ($user->id !== $comment->user_id) {
            return false;
        }

        return $comment->post?->status === 'published';
    }

    /**
     * Yorum sahibi veya admin yorumu silebilir.
     */
    public function delete(User $user, PostComment $comment): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role !== 'user') {
            return false;
        }

        if ($user->id !== $comment->user_id) {
            return false;
        }

        return $comment->post?->status === 'published';
    }
}
