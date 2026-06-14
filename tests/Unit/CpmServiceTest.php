<?php

use App\Services\CpmService;

// Helper: build a minimal subtask-like stdClass for CPM tests
function makeCpmSubtask(int $id, int $timeEstimateMinutes = 60, array $deps = []): object
{
    $obj = new stdClass;
    $obj->id = $id;
    $obj->subtask_id = 'ST-'.$id;
    $obj->name = 'Subtask '.$id;
    $obj->time_estimate = $timeEstimateMinutes;
    $obj->planned_estimate = $timeEstimateMinutes;
    $obj->pert_expected_estimate = null;
    $obj->optimistic_estimate = null;
    $obj->most_likely_estimate = null;
    $obj->pessimistic_estimate = null;
    $obj->pert_variance = null;
    $obj->status = null;
    $obj->priority = null;
    $obj->assignees = collect();
    $obj->start_date = null;
    $obj->due_date = null;
    $obj->completed_at = null;

    $depCollection = collect();
    foreach ($deps as $depId) {
        $dep = new stdClass;
        $dep->id = $depId;
        $dep->pivot = new stdClass;
        $dep->pivot->dependency_type = 'blocks';
        $depCollection->push($dep);
    }
    $obj->dependencies = $depCollection;
    $obj->dependents = collect();

    return $obj;
}

// isSchedulingDependency
test('isSchedulingDependency returns true for blocks type', function () {
    $service = new CpmService;
    $method = new ReflectionMethod($service, 'isSchedulingDependency');

    expect($method->invoke($service, 'blocks'))->toBeTrue();
});

test('isSchedulingDependency returns false for non-blocks types', function () {
    $service = new CpmService;
    $method = new ReflectionMethod($service, 'isSchedulingDependency');

    expect($method->invoke($service, 'relates_to'))->toBeFalse();
    expect($method->invoke($service, null))->toBeFalse();
    expect($method->invoke($service, ''))->toBeFalse();
});

// buildDependencyGraph
test('buildDependencyGraph creates empty adjacency lists for isolated nodes', function () {
    $subtasks = collect([makeCpmSubtask(1), makeCpmSubtask(2)]);

    $service = new CpmService;
    $method = new ReflectionMethod($service, 'buildDependencyGraph');
    $graph = $method->invoke($service, $subtasks);

    expect($graph['forward'][1])->toBe([]);
    expect($graph['forward'][2])->toBe([]);
    expect($graph['reverse'][1])->toBe([]);
    expect($graph['reverse'][2])->toBe([]);
});

test('buildDependencyGraph maps a single dependency edge', function () {
    $subtasks = collect([makeCpmSubtask(1), makeCpmSubtask(2, 60, [1])]);

    $service = new CpmService;
    $method = new ReflectionMethod($service, 'buildDependencyGraph');
    $graph = $method->invoke($service, $subtasks);

    expect($graph['forward'][1])->toContain(2);
    expect($graph['reverse'][2])->toContain(1);
});

// hasCycle
test('hasCycle returns false for acyclic linear chain', function () {
    $subtasks = collect([makeCpmSubtask(1), makeCpmSubtask(2, 60, [1]), makeCpmSubtask(3, 60, [2])]);

    $service = new CpmService;
    $graphMethod = new ReflectionMethod($service, 'buildDependencyGraph');
    $graph = $graphMethod->invoke($service, $subtasks);

    $cycleMethod = new ReflectionMethod($service, 'hasCycle');
    expect($cycleMethod->invoke($service, $graph, $subtasks))->toBeFalse();
});

test('hasCycle returns true for direct cycle', function () {
    $graph = ['forward' => [1 => [2], 2 => [1]], 'reverse' => [1 => [2], 2 => [1]]];
    $subtasks = collect([makeCpmSubtask(1), makeCpmSubtask(2)]);

    $service = new CpmService;
    $method = new ReflectionMethod($service, 'hasCycle');
    expect($method->invoke($service, $graph, $subtasks))->toBeTrue();
});

// topologicalSort
test('topologicalSort returns all nodes', function () {
    $subtasks = collect([makeCpmSubtask(1), makeCpmSubtask(2), makeCpmSubtask(3)]);

    $service = new CpmService;
    $graphMethod = new ReflectionMethod($service, 'buildDependencyGraph');
    $graph = $graphMethod->invoke($service, $subtasks);

    $sortMethod = new ReflectionMethod($service, 'topologicalSort');
    $sorted = $sortMethod->invoke($service, $graph, $subtasks);

    expect($sorted)->toHaveCount(3);
});

test('topologicalSort respects dependency order', function () {
    $subtasks = collect([makeCpmSubtask(1), makeCpmSubtask(2, 60, [1]), makeCpmSubtask(3, 60, [2])]);

    $service = new CpmService;
    $graphMethod = new ReflectionMethod($service, 'buildDependencyGraph');
    $graph = $graphMethod->invoke($service, $subtasks);

    $sortMethod = new ReflectionMethod($service, 'topologicalSort');
    $sorted = $sortMethod->invoke($service, $graph, $subtasks);

    expect(array_search(1, $sorted))->toBeLessThan(array_search(2, $sorted));
    expect(array_search(2, $sorted))->toBeLessThan(array_search(3, $sorted));
});

// forwardPass
test('forwardPass sets ES=0 EF=duration for isolated node', function () {

    $service = new CpmService;
    $graphMethod = new ReflectionMethod($service, 'buildDependencyGraph');
    $graph = $graphMethod->invoke($service, $subtasks);
    $sortMethod = new ReflectionMethod($service, 'topologicalSort');
    $sorted = $sortMethod->invoke($service, $graph, $subtasks);
    $forwardMethod = new ReflectionMethod($service, 'forwardPass');
    $cpmData = $forwardMethod->invoke($service, $subtasks, $sorted, $graph);

    expect($cpmData[1]['earlyStart'])->toEqual(0.0);
    expect($cpmData[1]['earlyFinish'])->toEqual(2.0);
});

test('forwardPass calculates sequential chain correctly', function () {
    $subtasks = collect([makeCpmSubtask(1, 120), makeCpmSubtask(2, 180, [1]), makeCpmSubtask(3, 60, [2])]);

    $service = new CpmService;
    $graphMethod = new ReflectionMethod($service, 'buildDependencyGraph');
    $graph = $graphMethod->invoke($service, $subtasks);
    $sortMethod = new ReflectionMethod($service, 'topologicalSort');
    $sorted = $sortMethod->invoke($service, $graph, $subtasks);
    $forwardMethod = new ReflectionMethod($service, 'forwardPass');
    $cpmData = $forwardMethod->invoke($service, $subtasks, $sorted, $graph);

    expect($cpmData[2]['earlyStart'])->toEqual(2.0);
    expect($cpmData[2]['earlyFinish'])->toEqual(5.0);
    expect($cpmData[3]['earlyStart'])->toEqual(5.0);
    expect($cpmData[3]['earlyFinish'])->toEqual(6.0);
});

test('forwardPass takes max EF of predecessors for merge node', function () {
    $subtasks = collect([makeCpmSubtask(1, 120), makeCpmSubtask(2, 240), makeCpmSubtask(3, 60, [1, 2])]);

    $service = new CpmService;
    $graphMethod = new ReflectionMethod($service, 'buildDependencyGraph');
    $graph = $graphMethod->invoke($service, $subtasks);
    $sortMethod = new ReflectionMethod($service, 'topologicalSort');
    $sorted = $sortMethod->invoke($service, $graph, $subtasks);
    $forwardMethod = new ReflectionMethod($service, 'forwardPass');
    $cpmData = $forwardMethod->invoke($service, $subtasks, $sorted, $graph);

    expect($cpmData[3]['earlyStart'])->toEqual(4.0);
    expect($cpmData[3]['earlyFinish'])->toEqual(5.0);
});

// calculateSlackAndCriticalPath
test('calculateSlackAndCriticalPath identifies critical and non-critical nodes', function () {
    $subtasks = collect([makeCpmSubtask(1, 120), makeCpmSubtask(2, 240), makeCpmSubtask(3, 60, [1, 2])]);

    $service = new CpmService;
    $graphMethod = new ReflectionMethod($service, 'buildDependencyGraph');
    $graph = $graphMethod->invoke($service, $subtasks);
    $sortMethod = new ReflectionMethod($service, 'topologicalSort');
    $sorted = $sortMethod->invoke($service, $graph, $subtasks);
    $forwardMethod = new ReflectionMethod($service, 'forwardPass');
    $cpmData = $forwardMethod->invoke($service, $subtasks, $sorted, $graph);
    $backwardMethod = new ReflectionMethod($service, 'backwardPass');
    $cpmData = $backwardMethod->invoke($service, $cpmData, $sorted, $graph);
    $slackMethod = new ReflectionMethod($service, 'calculateSlackAndCriticalPath');
    $cpmData = $slackMethod->invoke($service, $cpmData);

    expect($cpmData[1]['isCritical'])->toBeFalse();
    expect($cpmData[1]['slack'])->toBe(2.0);
    expect($cpmData[2]['isCritical'])->toBeTrue();
    expect($cpmData[2]['slack'])->toBe(0.0);
    expect($cpmData[3]['isCritical'])->toBeTrue();
});

// getCriticalPathSubtasks
test('getCriticalPathSubtasks returns only critical nodes sorted by earlyStart', function () {
    $cpmData = [
        1 => ['earlyStart' => 0, 'isCritical' => true, 'name' => 'A'],
        2 => ['earlyStart' => 2, 'isCritical' => false, 'name' => 'B'],
        3 => ['earlyStart' => 4, 'isCritical' => true, 'name' => 'C'],
    ];

    $service = new CpmService;
    $method = new ReflectionMethod($service, 'getCriticalPathSubtasks');
    $result = $method->invoke($service, $cpmData);

    expect($result)->toHaveCount(2);
    expect($result[0]['name'])->toBe('A');
    expect($result[1]['name'])->toBe('C');
});
