<?php

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;

// StoreTaskRequest
test('StoreTaskRequest requires name field', function () {
    $request = new StoreTaskRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('name');
    expect($rules['name'])->toContain('required');
});

test('StoreTaskRequest name has max 255 chars', function () {
    $request = new StoreTaskRequest;
    $rules = $request->rules();

    expect($rules['name'])->toContain('max:255');
});

test('StoreTaskRequest description is nullable string with max 10000', function () {
    $request = new StoreTaskRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('description');
    expect($rules['description'])->toContain('nullable');
    expect($rules['description'])->toContain('string');
    expect($rules['description'])->toContain('max:10000');
});

test('StoreTaskRequest priority_level must be in 1,2,3,4', function () {
    $request = new StoreTaskRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('priority_level');
    expect($rules['priority_level'])->toContain('in:1,2,3,4');
});

test('StoreTaskRequest due_date must be after_or_equal start_date', function () {
    $request = new StoreTaskRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('due_date');
    expect($rules['due_date'])->toContain('after_or_equal:start_date');
});

test('StoreTaskRequest time_estimate has min 0 and max 525600', function () {
    $request = new StoreTaskRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('time_estimate');
    expect($rules['time_estimate'])->toContain('min:0');
    expect($rules['time_estimate'])->toContain('max:525600');
});

test('StoreTaskRequest assignee_ids is nullable array', function () {
    $request = new StoreTaskRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('assignee_ids');
    expect($rules['assignee_ids'])->toContain('nullable');
    expect($rules['assignee_ids'])->toContain('array');
});

test('StoreTaskRequest authorize returns true', function () {
    $request = new StoreTaskRequest;
    expect($request->authorize())->toBeTrue();
});

test('StoreTaskRequest has custom error messages', function () {
    $request = new StoreTaskRequest;
    $messages = $request->messages();

    expect($messages)->toHaveKey('name.required');
    expect($messages)->toHaveKey('name.max');
    expect($messages)->toHaveKey('due_date.after_or_equal');
});

// UpdateTaskRequest
test('UpdateTaskRequest name uses sometimes rule', function () {
    $request = new UpdateTaskRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('name');
    expect($rules['name'])->toContain('sometimes');
});

test('UpdateTaskRequest authorize returns true', function () {
    $request = new UpdateTaskRequest;
    expect($request->authorize())->toBeTrue();
});

test('UpdateTaskRequest has priority_level field', function () {
    $request = new UpdateTaskRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('priority_level');
    expect($rules['priority_level'])->toContain('nullable');
    expect($rules['priority_level'])->toContain('integer');
});

test('UpdateTaskRequest time_estimate has max 525600', function () {
    $request = new UpdateTaskRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('time_estimate');
    expect($rules['time_estimate'])->toContain('max:525600');
});
