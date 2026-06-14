<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use App\Services\AccessService;

class CommentPolicy
{
    public function __construct(protected AccessService $accessService) {}

    /**
     * Determine whether the user can update the comment.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $this->accessService->canManageComment($user, $comment);
    }

    /**
     * Determine whether the user can delete the comment.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $this->accessService->canManageComment($user, $comment);
    }
}
