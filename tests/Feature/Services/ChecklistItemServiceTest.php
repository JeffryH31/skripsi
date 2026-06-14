<?php

use App\Models\ChecklistItem;
use App\Services\ChecklistItemService;
use Illuminate\Validation\ValidationException;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new ChecklistItemService;
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
    $this->subtask = $this->createSubtask($this->hierarchy['task']);
});

// create
test('create checklist item', function () {
    $item = $this->service->create(['name' => 'Buy milk'], $this->subtask, $this->owner);

    expect($item)->toBeInstanceOf(ChecklistItem::class);
    expect($item->name)->toBe('Buy milk');
    expect($item->subtask_id)->toBe($this->subtask->id);
    expect($item->is_checked)->toBeFalse();
});

test('create checklist item with is_checked true', function () {
    $item = $this->service->create(['name' => 'Done', 'is_checked' => true], $this->subtask, $this->owner);

    expect($item->is_checked)->toBeTrue();
});

test('create checklist item with parent_id', function () {
    $parent = $this->service->create(['name' => 'Parent'], $this->subtask, $this->owner);

    $child = $this->service->create(['name' => 'Child', 'parent_id' => $parent->id], $this->subtask, $this->owner);

    expect($child->parent_id)->toBe($parent->id);
    expect($child->depth)->toBe(1);
});

test('create checklist item throws at max depth', function () {
    // Build chain up to MAX_DEPTH
    $item = $this->service->create(['name' => 'L0'], $this->subtask, $this->owner);
    for ($i = 1; $i <= ChecklistItem::MAX_DEPTH; $i++) {
        $item = $this->service->create(['name' => "L$i", 'parent_id' => $item->id], $this->subtask, $this->owner);
    }

    // Now at MAX_DEPTH — one more should throw
    expect(fn () => $this->service->create(
        ['name' => 'Too Deep', 'parent_id' => $item->id],
        $this->subtask,
        $this->owner
    ))->toThrow(ValidationException::class);
});

// update
test('update checklist item name', function () {
    $item = $this->service->create(['name' => 'Old'], $this->subtask, $this->owner);

    $updated = $this->service->update($item, ['name' => 'New']);

    expect($updated->name)->toBe('New');
});

// toggle
test('toggle flips checked state', function () {
    $item = $this->service->create(['name' => 'Toggle Me'], $this->subtask, $this->owner);

    $toggled = $this->service->toggle($item);
    expect($toggled->is_checked)->toBeTrue();

    $toggledBack = $this->service->toggle($toggled);
    expect($toggledBack->is_checked)->toBeFalse();
});

test('toggle with cascade updates children', function () {
    $parent = $this->service->create(['name' => 'Parent'], $this->subtask, $this->owner);
    $child = $this->service->create(['name' => 'Child', 'parent_id' => $parent->id], $this->subtask, $this->owner);

    $this->service->toggle($parent, cascade: true);

    expect($child->fresh()->is_checked)->toBeTrue();
});

// delete
test('delete checklist item', function () {
    $item = $this->service->create(['name' => 'Delete Me'], $this->subtask, $this->owner);

    $this->service->delete($item);

    expect(ChecklistItem::find($item->id))->toBeNull();
});

// reorder
test('reorder updates positions', function () {
    $i1 = $this->service->create(['name' => 'First'], $this->subtask, $this->owner);
    $i2 = $this->service->create(['name' => 'Second'], $this->subtask, $this->owner);

    $this->service->reorder($this->subtask, [$i2->id, $i1->id]);

    expect($i2->fresh()->position)->toBe(0);
    expect($i1->fresh()->position)->toBe(1);
});
