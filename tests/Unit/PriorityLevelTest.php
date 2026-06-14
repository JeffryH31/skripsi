<?php

use App\Enums\PriorityLevel;

// label()
test('Urgent has label Urgent', function () {
    expect(PriorityLevel::Urgent->label())->toBe('Urgent');
});

test('High has label High', function () {
    expect(PriorityLevel::High->label())->toBe('High');
});

test('Normal has label Normal', function () {
    expect(PriorityLevel::Normal->label())->toBe('Normal');
});

test('Low has label Low', function () {
    expect(PriorityLevel::Low->label())->toBe('Low');
});

// color()
test('Urgent color is red', function () {
    expect(PriorityLevel::Urgent->color())->toBe('#EF4444');
});

test('High color is amber', function () {
    expect(PriorityLevel::High->color())->toBe('#F59E0B');
});

test('Normal color is blue', function () {
    expect(PriorityLevel::Normal->color())->toBe('#3B82F6');
});

test('Low color is gray', function () {
    expect(PriorityLevel::Low->color())->toBe('#6B7280');
});

// value (int backing)
test('Urgent value is 1', function () {
    expect(PriorityLevel::Urgent->value)->toBe(1);
});

test('High value is 2', function () {
    expect(PriorityLevel::High->value)->toBe(2);
});

test('Normal value is 3', function () {
    expect(PriorityLevel::Normal->value)->toBe(3);
});

test('Low value is 4', function () {
    expect(PriorityLevel::Low->value)->toBe(4);
});

// from() / tryFrom()
test('can create PriorityLevel from integer value', function () {
    expect(PriorityLevel::from(1))->toBe(PriorityLevel::Urgent);
    expect(PriorityLevel::from(2))->toBe(PriorityLevel::High);
    expect(PriorityLevel::from(3))->toBe(PriorityLevel::Normal);
    expect(PriorityLevel::from(4))->toBe(PriorityLevel::Low);
});

test('tryFrom returns null for invalid value', function () {
    expect(PriorityLevel::tryFrom(99))->toBeNull();
    expect(PriorityLevel::tryFrom(0))->toBeNull();
});

// toArray()
test('toArray returns level, name, and color keys', function () {
    $arr = PriorityLevel::High->toArray();

    expect($arr)->toHaveKeys(['level', 'name', 'color']);
    expect($arr['level'])->toBe(2);
    expect($arr['name'])->toBe('High');
    expect($arr['color'])->toBe('#F59E0B');
});

// allToArray()
test('allToArray returns all 4 priorities', function () {
    $all = PriorityLevel::allToArray();

    expect($all)->toHaveCount(4);
});

test('allToArray each item has required keys', function () {
    foreach (PriorityLevel::allToArray() as $item) {
        expect($item)->toHaveKeys(['level', 'name', 'color']);
    }
});
