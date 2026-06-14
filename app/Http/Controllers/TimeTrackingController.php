<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TimeEntry\LogTimeRequest;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Services\TimeTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * TimeTrackingController
 *
 * Handles time tracking using Inertia.js (monolith).
 * Updated for Hierarchy: Workspace -> Space -> Folder -> List -> Task
 */
class TimeTrackingController extends Controller
{
    private TimeTrackingService $timeTrackingService;

    public function __construct(TimeTrackingService $timeTrackingService)
    {
        $this->timeTrackingService = $timeTrackingService;
    }

    /**
     * Start a timer for a task.
     */
    public function startTimer(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task->list->space);

        $this->timeTrackingService->startTimer($task, $request->user());

        return back()->with('success', 'Timer dimulai.');
    }

    /**
     * Stop a running timer.
     */
    public function stopTimer(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        // Only allow the owner to stop the timer
        if ($timeEntry->user_id !== $request->user()->id) {
            return back()->withErrors(['error' => 'You cannot stop another user\'s timer.']);
        }

        $this->timeTrackingService->stopTimer($timeEntry, $request->user());

        return back()->with('success', 'Timer stopped.');
    }

    /**
     * Pause a timer (set task to on hold).
     */
    public function pauseTimer(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task->list->space);

        $this->timeTrackingService->pauseTimer($task, $request->user());

        return back()->with('success', 'Timer paused. Task is now On Hold.');
    }

    /**
     * Resume a timer (continue from on hold).
     */
    public function resumeTimer(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task->list->space);

        $this->timeTrackingService->resumeTimer($task, $request->user());

        return back()->with('success', 'Timer resumed.');
    }

    /**
     * Complete a task and stop any running timers.
     */
    public function completeTask(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task->list->space);

        $this->timeTrackingService->completeTask($task, $request->user());

        return back()->with('success', 'Task completed.');
    }

    /**
     * Log manual time entry.
     */
    public function logManualTime(LogTimeRequest $request): RedirectResponse
    {
        $task = Task::findOrFail($request->task_id);
        $this->authorize('update', $task->list->space);

        $this->timeTrackingService->logManualTime($task, $request->user(), $request->validated());

        return back()->with('success', 'Time logged successfully.');
    }

    /**
     * Update a time entry.
     */
    public function update(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        // Only allow the owner to update
        if ($timeEntry->user_id !== $request->user()->id) {
            return back()->withErrors(['error' => 'You cannot edit another user\'s time entry.']);
        }

        $request->validate([
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->timeTrackingService->updateTimeEntry($timeEntry, $request->only(['duration_minutes', 'description']));

        return back()->with('success', 'Time entry updated successfully.');
    }

    /**
     * Delete a time entry.
     */
    public function destroy(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        // Only allow the owner to delete
        if ($timeEntry->user_id !== $request->user()->id) {
            return back()->withErrors(['error' => 'You cannot delete another user\'s time entry.']);
        }

        $this->timeTrackingService->deleteTimeEntry($timeEntry);

        return back()->with('success', 'Time entry deleted successfully.');
    }

    /**
     * Stop all running timers for the authenticated user.
     */
    public function stopAllTimers(Request $request): RedirectResponse
    {
        $count = $this->timeTrackingService->stopAllRunningTimers($request->user());

        return back()->with('success', "{$count} timers stopped successfully.");
    }
}
