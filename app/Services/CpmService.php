<?php

namespace App\Services;

use App\Models\Subtask;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CpmService
{
    /**
     * Run Critical Path Method analysis on all subtasks of a task.
     *
     * Builds a dependency graph, performs topological sort, then executes
     * forward and backward passes to compute early/late start/finish times,
     * slack, and the critical path.
     *
     * @return array{success: bool, message?: string, data?: array}
     */
    public function analyze(Task $task): array
    {
        // Load subtasks with dependencies
        $subtasks = $task->subtasks()
            ->with(['dependencies', 'dependents', 'status', 'assignees'])
            ->get();

        if ($subtasks->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No subtasks found for CPM analysis',
                'data' => null,
            ];
        }

        // Check if any subtask has time estimate
        $subtasksWithEstimate = $subtasks->filter(fn ($s) => $s->time_estimate > 0);

        if ($subtasksWithEstimate->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No subtasks have time estimates. Please add time estimates to calculate CPM.',
                'data' => null,
            ];
        }

        // Build the dependency graph
        $graph = $this->buildDependencyGraph($subtasks);

        // Check for cycles
        if ($this->hasCycle($graph, $subtasks)) {
            return [
                'success' => false,
                'message' => 'Circular dependency detected. Please remove circular dependencies to calculate CPM.',
                'data' => null,
            ];
        }

        // Topological sort
        $sortedIds = $this->topologicalSort($graph, $subtasks);

        // Forward pass - calculate Early Start (ES) and Early Finish (EF)
        $cpmData = $this->forwardPass($subtasks, $sortedIds, $graph);

        // Backward pass - calculate Late Start (LS) and Late Finish (LF)
        $cpmData = $this->backwardPass($cpmData, $sortedIds, $graph);

        $cpmData = $this->calculateSlackAndCriticalPath($cpmData);

        // Get project summary
        $summary = $this->getProjectSummary($cpmData, $task);

        return [
            'success' => true,
            'message' => 'CPM analysis completed successfully',
            'data' => [
                'subtasks' => $cpmData,
                'summary' => $summary,
                'criticalPath' => $this->getCriticalPathSubtasks($cpmData),
            ],
        ];
    }

    /**
     * Build dependency graph from subtasks
     * Returns array where key is subtask_id and value is array of dependent subtask_ids
     */
    protected function buildDependencyGraph(Collection $subtasks): array
    {
        $graph = [];
        $reverseGraph = [];

        foreach ($subtasks as $subtask) {
            $graph[$subtask->id] = [];
            $reverseGraph[$subtask->id] = [];
        }

        foreach ($subtasks as $subtask) {
            // dependencies() returns subtasks that THIS subtask depends on
            // meaning this subtask cannot start until those are finished
            foreach ($subtask->dependencies as $dependency) {
                if (! $this->isSchedulingDependency($dependency->pivot?->dependency_type ?? null)) {
                    continue;
                }
                // Only include dependencies that are part of this task's subtasks
                if ($subtasks->contains('id', $dependency->id)) {
                    // dependency must finish before subtask can start
                    // In graph terms: dependency -> subtask (dependency points to subtask)
                    $graph[$dependency->id][] = $subtask->id;
                    $reverseGraph[$subtask->id][] = $dependency->id;
                }
            }
        }

        return [
            'forward' => $graph,      // predecessors -> successors
            'reverse' => $reverseGraph, // successors -> predecessors (for backward pass)
        ];
    }

    /**
     * Only blocking-style dependencies should affect CPM scheduling.
     */
    protected function isSchedulingDependency(?string $type): bool
    {
        return $type === 'blocks';
    }

    /**
     * Check if graph has a cycle using DFS
     */
    protected function hasCycle(array $graph, Collection $subtasks): bool
    {
        $visited = [];
        $recStack = [];

        foreach ($subtasks as $subtask) {
            $visited[$subtask->id] = false;
            $recStack[$subtask->id] = false;
        }

        foreach ($subtasks as $subtask) {
            if ($this->hasCycleDFS($subtask->id, $graph['forward'], $visited, $recStack)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Depth-first search helper used for cycle detection.
     *
     * @param  array<int, array<int>>  $graph
     * @param  array<int, bool>  $visited
     * @param  array<int, bool>  $recStack
     */
    protected function hasCycleDFS(int $nodeId, array $graph, array &$visited, array &$recStack): bool
    {
        if (! $visited[$nodeId]) {
            $visited[$nodeId] = true;
            $recStack[$nodeId] = true;

            foreach ($graph[$nodeId] ?? [] as $neighbor) {
                if (! $visited[$neighbor] && $this->hasCycleDFS($neighbor, $graph, $visited, $recStack)) {
                    return true;
                } elseif ($recStack[$neighbor]) {
                    return true;
                }
            }
        }

        $recStack[$nodeId] = false;

        return false;
    }

    /**
     * Topological sort using Kahn's algorithm
     */
    protected function topologicalSort(array $graph, Collection $subtasks): array
    {
        $inDegree = [];
        foreach ($subtasks as $subtask) {
            $inDegree[$subtask->id] = count($graph['reverse'][$subtask->id] ?? []);
        }

        // Find all nodes with no incoming edges (no dependencies)
        $queue = [];
        foreach ($inDegree as $id => $degree) {
            if ($degree === 0) {
                $queue[] = $id;
            }
        }

        $sorted = [];
        while (! empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $current;

            foreach ($graph['forward'][$current] ?? [] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        return $sorted;
    }

    /**
     * Forward pass - calculate Early Start (ES) and Early Finish (EF)
     */
    protected function forwardPass(Collection $subtasks, array $sortedIds, array $graph): array
    {
        $cpmData = [];

        // Initialize all subtasks
        foreach ($subtasks as $subtask) {
            $plannedMinutes = $subtask->planned_estimate ?? 0;
            $duration = $plannedMinutes / 60; // Convert minutes to hours

            $cpmData[$subtask->id] = [
                'id' => $subtask->id,
                'subtask_id' => $subtask->subtask_id,
                'name' => $subtask->name,
                'duration' => $duration,
                'durationMinutes' => $plannedMinutes,
                'durationSource' => $subtask->pert_expected_estimate ? 'pert' : 'manual',
                'earlyStart' => 0,
                'earlyFinish' => 0,
                'lateStart' => 0,
                'lateFinish' => 0,
                'slack' => 0,
                'isCritical' => false,
                'status' => $subtask->status,
                'priority' => $subtask->priority,
                'assignees' => $subtask->assignees,
                'startDate' => $subtask->start_date?->format('Y-m-d'),
                'dueDate' => $subtask->due_date?->format('Y-m-d'),
                'completedAt' => $subtask->completed_at?->format('Y-m-d H:i:s'),
                'dependencies' => $subtask->dependencies->pluck('id')->toArray(),
                'dependents' => $subtask->dependents->pluck('id')->toArray(),
                'pert' => [
                    'optimistic' => $subtask->optimistic_estimate,
                    'mostLikely' => $subtask->most_likely_estimate,
                    'pessimistic' => $subtask->pessimistic_estimate,
                    'expected' => $subtask->pert_expected_estimate,
                    'variance' => $subtask->pert_variance,
                ],
            ];
        }

        // Process in topological order
        foreach ($sortedIds as $id) {
            $predecessors = $graph['reverse'][$id] ?? [];

            if (empty($predecessors)) {
                // No dependencies - starts at time 0
                $cpmData[$id]['earlyStart'] = 0;
            } else {
                // ES = max(EF of all predecessors)
                $maxEF = 0;
                foreach ($predecessors as $predId) {
                    if (isset($cpmData[$predId])) {
                        $maxEF = max($maxEF, $cpmData[$predId]['earlyFinish']);
                    }
                }
                $cpmData[$id]['earlyStart'] = $maxEF;
            }

            // EF = ES + duration
            $cpmData[$id]['earlyFinish'] = $cpmData[$id]['earlyStart'] + $cpmData[$id]['duration'];
        }

        return $cpmData;
    }

    /**
     * Backward pass - calculate Late Start (LS) and Late Finish (LF)
     */
    protected function backwardPass(array $cpmData, array $sortedIds, array $graph): array
    {
        // Find project duration (maximum EF)
        $projectDuration = 0;
        foreach ($cpmData as $data) {
            $projectDuration = max($projectDuration, $data['earlyFinish']);
        }

        // Initialize LF for all end nodes (nodes with no successors)
        foreach ($cpmData as $id => &$data) {
            $successors = $graph['forward'][$id] ?? [];
            if (empty($successors)) {
                $data['lateFinish'] = $projectDuration;
                $data['lateStart'] = $data['lateFinish'] - $data['duration'];
            }
        }

        // Process in reverse topological order
        $reverseSorted = array_reverse($sortedIds);

        foreach ($reverseSorted as $id) {
            $successors = $graph['forward'][$id] ?? [];

            if (! empty($successors)) {
                // LF = min(LS of all successors)
                $minLS = PHP_INT_MAX;
                foreach ($successors as $succId) {
                    if (isset($cpmData[$succId])) {
                        $minLS = min($minLS, $cpmData[$succId]['lateStart']);
                    }
                }
                $cpmData[$id]['lateFinish'] = $minLS;
                $cpmData[$id]['lateStart'] = $cpmData[$id]['lateFinish'] - $cpmData[$id]['duration'];
            }
        }

        return $cpmData;
    }

    /**
     * Calculate slack (float) and identify critical path
     */
    protected function calculateSlackAndCriticalPath(array $cpmData): array
    {
        foreach ($cpmData as $id => &$data) {
            // Slack (Total Float) = LS - ES = LF - EF
            $data['slack'] = round($data['lateStart'] - $data['earlyStart'], 2);

            // Critical path = activities with zero slack
            $data['isCritical'] = abs($data['slack']) < 0.001; // Use small epsilon for float comparison
        }

        return $cpmData;
    }

    /**
     * Get critical path subtasks in order
     */
    protected function getCriticalPathSubtasks(array $cpmData): array
    {
        $criticalSubtasks = array_filter($cpmData, fn ($data) => $data['isCritical']);

        // Sort by early start
        uasort($criticalSubtasks, fn ($a, $b) => $a['earlyStart'] <=> $b['earlyStart']);

        return array_values($criticalSubtasks);
    }

    /**
     * Get project summary
     */
    protected function getProjectSummary(array $cpmData, Task $task): array
    {
        $projectDuration = 0;
        $totalDuration = 0;
        $criticalCount = 0;
        $completedCount = 0;

        foreach ($cpmData as $data) {
            $projectDuration = max($projectDuration, $data['earlyFinish']);
            $totalDuration += $data['duration'];
            if ($data['isCritical']) {
                $criticalCount++;
            }
            if ($data['completedAt']) {
                $completedCount++;
            }
        }

        $projectStart = $task->subtasks()
            ->whereNotNull('start_date')
            ->min('start_date');

        $startDate = $projectStart ? Carbon::parse($projectStart) : Carbon::today();

        // Assuming 8 working hours per day
        $workingHoursPerDay = config('business.working_hours_per_day', 8);
        $workingDays = ceil($projectDuration / $workingHoursPerDay);
        $endDate = $startDate->copy()->addWeekdays($workingDays);

        return [
            'projectDurationHours' => round($projectDuration, 2),
            'projectDurationDays' => round($projectDuration / $workingHoursPerDay, 1),
            'totalSubtasks' => count($cpmData),
            'criticalSubtasks' => $criticalCount,
            'nonCriticalSubtasks' => count($cpmData) - $criticalCount,
            'completedSubtasks' => $completedCount,
            'totalEffortHours' => round($totalDuration, 2),
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'workingHoursPerDay' => $workingHoursPerDay,
        ];
    }

    /**
     * Create a dependency relationship between two subtasks.
     *
     * Validates same-task constraint, cycle detection, and duplicate check
     * before attaching the dependency.
     *
     * @return array{success: bool, message: string}
     */
    public function addDependency(Subtask $subtask, Subtask $dependsOn, string $type = 'blocks'): array
    {
        // Check if both subtasks belong to the same task
        if ($subtask->task_id !== $dependsOn->task_id) {
            return [
                'success' => false,
                'message' => 'Dependencies can only be created between subtasks of the same task',
            ];
        }

        // Check if dependency would create a cycle
        if ($this->wouldCreateCycle($subtask, $dependsOn)) {
            return [
                'success' => false,
                'message' => 'This dependency would create a circular reference',
            ];
        }

        // Check if dependency already exists
        if ($subtask->dependencies()->where('depends_on_subtask_id', $dependsOn->id)->exists()) {
            return [
                'success' => false,
                'message' => 'This dependency already exists',
            ];
        }

        // Create the dependency
        $subtask->dependencies()->attach($dependsOn->id, ['dependency_type' => $type]);

        return [
            'success' => true,
            'message' => 'Dependency added successfully',
        ];
    }

    /**
     * Remove a dependency relationship between two subtasks.
     *
     * @return array{success: bool, message: string}
     */
    public function removeDependency(Subtask $subtask, Subtask $dependsOn): array
    {
        $subtask->dependencies()->detach($dependsOn->id);

        return [
            'success' => true,
            'message' => 'Dependency removed successfully',
        ];
    }

    /**
     * Detect if adding a dependency from $dependsOn → $subtask would create a cycle.
     *
     * Uses BFS from $subtask through existing dependents. If $dependsOn is
     * reachable, adding the reverse edge would close a loop.
     *
     * Pre-loads all edges in one query to avoid N+1.
     */
    protected function wouldCreateCycle(Subtask $subtask, Subtask $dependsOn): bool
    {
        // Edge direction is: dependency -> dependent.
        // Adding "subtask depends on dependsOn" means adding edge: dependsOn -> subtask.
        // This creates a cycle iff subtask can already reach dependsOn.
        if ($subtask->id === $dependsOn->id) {
            return true;
        }

        // Pre-load all dependency edges for the parent task in one query
        // to avoid N+1 inside the BFS loop.
        // "dependents of X" = subtasks that depend ON X = rows where depends_on_subtask_id = X
        $allDependents = \Illuminate\Support\Facades\DB::table('subtask_dependencies')
            ->whereIn('depends_on_subtask_id', function ($q) use ($subtask) {
                $q->select('id')->from('subtasks')->where('task_id', $subtask->task_id);
            })
            ->get()
            ->groupBy('depends_on_subtask_id')
            ->map(fn ($rows) => $rows->pluck('subtask_id')->all())
            ->all();

        $visited = [];
        $queue = [$subtask->id];

        while (! empty($queue)) {
            $current = array_shift($queue);

            if ($current === $dependsOn->id) {
                return true;
            }

            if (in_array($current, $visited)) {
                continue;
            }

            $visited[] = $current;

            foreach ($allDependents[$current] ?? [] as $dependentId) {
                if (! in_array($dependentId, $visited)) {
                    $queue[] = $dependentId;
                }
            }
        }

        return false;
    }
}
