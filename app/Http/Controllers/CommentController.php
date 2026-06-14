<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Space;
use App\Models\Task;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\CommentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected CommentService $commentService,
        protected AccessService $accessService,
    ) {}

    /**
     * Store a new comment.
     */
    public function store(StoreCommentRequest $request, Workspace $workspace, Space $space, Project $project, Task $task): RedirectResponse
    {
        abort_unless($this->accessService->canComment($request->user(), $project), 403);
        try {
            $comment = $this->commentService->create($task, $request->user(), $request->validated());

            return redirect()->back()->with([
                'success' => 'Comment added successfully.',
                'comment' => new CommentResource($comment->load('user')),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to add comment: '.$e->getMessage()]);
        }
    }

    /**
     * Update comment.
     */
    public function update(UpdateCommentRequest $request, Comment $comment): RedirectResponse
    {
        try {
            $this->authorize('update', $comment);
            $updatedComment = $this->commentService->update($comment, $request->validated(), $request->user());

            return redirect()->back()->with([
                'success' => 'Comment updated successfully.',
                'comment' => new CommentResource($updatedComment->load('user')),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return redirect()->back()->withErrors(['error' => 'You are not authorized to update this comment.']);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update comment: '.$e->getMessage()]);
        }
    }

    /**
     * Delete comment.
     */
    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        try {
            $this->authorize('delete', $comment);
            $this->commentService->delete($comment, $request->user());

            return redirect()->back()->with('success', 'Comment deleted successfully.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return redirect()->back()->withErrors(['error' => 'You are not authorized to delete this comment.']);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete comment: '.$e->getMessage()]);
        }
    }

    /**
     * Resolve comment.
     */
    public function resolve(Request $request, Comment $comment): RedirectResponse
    {
        abort_unless($this->accessService->canResolveComment($request->user(), $comment), 403);

        try {
            $this->commentService->resolve($comment, $request->user());

            return redirect()->back()->with('success', 'Comment resolved successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to resolve comment: '.$e->getMessage()]);
        }
    }

    /**
     * Unresolve comment.
     */
    public function unresolve(Request $request, Comment $comment): RedirectResponse
    {
        abort_unless($this->accessService->canResolveComment($request->user(), $comment), 403);

        try {
            $this->commentService->unresolve($comment, $request->user());

            return redirect()->back()->with('success', 'Comment unresolve successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to unresolve comment: '.$e->getMessage()]);
        }
    }
}
