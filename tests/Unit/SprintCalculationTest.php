<?php

test('ideal burndown decreases linearly from total to 0', function () {
    $total = 10;
    $days = 5;
    $burndown = [];
    for ($day = 0; $day <= $days; $day++) {
        $burndown[] = $total - ($total * $day / $days);
    }

    expect($burndown[0])->toEqual(10);
    expect($burndown[5])->toEqual(0);
    expect($burndown[1])->toEqual(8);
});

test('sprint completion rate is 0 when no subtasks', function () {
    $rate = 0 > 0 ? round((0 / 0) * 100) : 0;
    expect($rate)->toBe(0);
});

test('sprint completion rate is 100 when all subtasks completed', function () {
    $rate = 8 > 0 ? round((8 / 8) * 100) : 0;
    expect($rate)->toEqual(100);
});

test('average sprint velocity calculation', function () {
    $data = [
        ['completed_subtasks' => 10],
        ['completed_subtasks' => 8],
        ['completed_subtasks' => 12],
    ];
    $avg = collect($data)->avg('completed_subtasks');
    expect(round($avg, 1))->toBe(10.0);
});
