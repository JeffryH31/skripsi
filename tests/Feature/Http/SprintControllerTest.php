<?php

use App\Models\Sprint;
use Tests\Traits\CreatesWorkspaceHierarchy;

use function Pest\Laravel\actingAs;

uses(CreatesWorkspaceHierarchy::class);

// Shared setup
beforeEach(function () {
    $this->owner = $this->createUser();
    $this->h = $this->createFullHierarchy($this->owner);
    $this->subtask = $this->createSubtask($this->h['task']);
});

// store
test('owner can create a sprint', function () {
    actingAs($this->owner)
        ->from(route('spaces.show', [$this->h['workspace'], $this->h['space']]))
        ->post(route('sprints.store', [$this->h['workspace'], $this->h['space']]), [
            'name' => 'Sprint 1',
            'list_id' => $this->h['list']->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-14',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('sprints', [
        'space_id' => $this->h['space']->id,
        'name' => 'Sprint 1',
    ]);
});

test('unauthenticated user cannot create a sprint', function () {
    $this->post(route('sprints.store', [$this->h['workspace'], $this->h['space']]), [
        'name' => 'Ghost Sprint',
        'list_id' => $this->h['list']->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-14',
    ])
        ->assertRedirectToRoute('login');
});

test('creating a sprint without a name returns a validation error', function () {
    actingAs($this->owner)
        ->from(route('spaces.show', [$this->h['workspace'], $this->h['space']]))
        ->post(route('sprints.store', [$this->h['workspace'], $this->h['space']]), [
            'list_id' => $this->h['list']->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-14',
        ])
        ->assertSessionHasErrors(['name']);
});

test('creating a sprint with end_date before start_date returns a validation error', function () {
    actingAs($this->owner)
        ->from(route('spaces.show', [$this->h['workspace'], $this->h['space']]))
        ->post(route('sprints.store', [$this->h['workspace'], $this->h['space']]), [
            'name' => 'Bad Sprint',
            'list_id' => $this->h['list']->id,
            'start_date' => '2026-05-14',
            'end_date' => '2026-05-01',
        ])
        ->assertSessionHasErrors(['end_date']);
});

// update
test('owner can update a sprint', function () {
    $sprint = Sprint::create([
        'space_id' => $this->h['space']->id,
        'project_id' => $this->h['list']->id,
        'name' => 'Old Name',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-14',
    ]);

    actingAs($this->owner)
        ->from(route('spaces.show', [$this->h['workspace'], $this->h['space']]))
        ->patch(route('sprints.update', [$this->h['workspace'], $this->h['space'], $sprint]), [
            'name' => 'Renamed Sprint',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-21',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('sprints', [
        'id' => $sprint->id,
        'name' => 'Renamed Sprint',
    ]);
});

// start
test('owner can start a sprint', function () {
    $sprint = Sprint::create([
        'space_id' => $this->h['space']->id,
        'project_id' => $this->h['list']->id,
        'name' => 'Sprint A',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-14',
        'is_active' => false,
    ]);

    actingAs($this->owner)
        ->from(route('spaces.show', [$this->h['workspace'], $this->h['space']]))
        ->post(route('sprints.start', [$this->h['workspace'], $this->h['space'], $sprint]))
        ->assertRedirect();

    $this->assertDatabaseHas('sprints', [
        'id' => $sprint->id,
        'is_active' => true,
    ]);
});

test('starting a new sprint deactivates the previously active sprint', function () {
    $sprint1 = Sprint::create([
        'space_id' => $this->h['space']->id,
        'project_id' => $this->h['list']->id,
        'name' => 'Sprint A',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-14',
        'is_active' => true,
    ]);

    $sprint2 = Sprint::create([
        'space_id' => $this->h['space']->id,
        'project_id' => $this->h['list']->id,
        'name' => 'Sprint B',
        'start_date' => '2026-05-15',
        'end_date' => '2026-05-28',
        'is_active' => false,
    ]);

    actingAs($this->owner)
        ->from(route('spaces.show', [$this->h['workspace'], $this->h['space']]))
        ->post(route('sprints.start', [$this->h['workspace'], $this->h['space'], $sprint2]))
        ->assertRedirect();

    $this->assertDatabaseHas('sprints', ['id' => $sprint1->id, 'is_active' => false]);
    $this->assertDatabaseHas('sprints', ['id' => $sprint2->id, 'is_active' => true]);
});

// complete
test('owner can complete an active sprint', function () {
    $sprint = Sprint::create([
        'space_id' => $this->h['space']->id,
        'project_id' => $this->h['list']->id,
        'name' => 'Sprint X',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-14',
        'is_active' => true,
    ]);

    actingAs($this->owner)
        ->from(route('spaces.show', [$this->h['workspace'], $this->h['space']]))
        ->post(route('sprints.complete', [$this->h['workspace'], $this->h['space'], $sprint]))
        ->assertRedirect();

    $this->assertDatabaseHas('sprints', [
        'id' => $sprint->id,
        'is_active' => false,
    ]);
});

// addTask / removeTask
test('owner can add a subtask to a sprint', function () {
    $sprint = Sprint::create([
        'space_id' => $this->h['space']->id,
        'project_id' => $this->h['list']->id,
        'name' => 'Sprint With Tasks',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-14',
    ]);

    actingAs($this->owner)
        ->from(route('spaces.show', [$this->h['workspace'], $this->h['space']]))
        ->post(route('sprints.tasks.add', [$this->h['workspace'], $this->h['space'], $sprint]), [
            'subtask_id' => $this->subtask->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('subtasks', [
        'id' => $this->subtask->id,
        'sprint_id' => $sprint->id,
    ]);
});

test('owner can remove a subtask from a sprint', function () {
    $sprint = Sprint::create([
        'space_id' => $this->h['space']->id,
        'project_id' => $this->h['list']->id,
        'name' => 'Sprint With Tasks',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-14',
    ]);

    $this->subtask->update(['sprint_id' => $sprint->id]);

    actingAs($this->owner)
        ->from(route('spaces.show', [$this->h['workspace'], $this->h['space']]))
        ->delete(route('sprints.tasks.remove', [$this->h['workspace'], $this->h['space'], $sprint]), [
            'subtask_id' => $this->subtask->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('subtasks', [
        'id' => $this->subtask->id,
        'sprint_id' => null,
    ]);
});

// destroy
test('owner can delete a sprint', function () {
    $sprint = Sprint::create([
        'space_id' => $this->h['space']->id,
        'project_id' => $this->h['list']->id,
        'name' => 'Sprint to Delete',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-14',
    ]);

    actingAs($this->owner)
        ->delete(route('sprints.destroy', [$this->h['workspace'], $this->h['space'], $sprint]))
        ->assertRedirect();

    $this->assertDatabaseMissing('sprints', ['id' => $sprint->id]);
});

test('deleting a sprint clears the sprint_id on its subtasks', function () {
    $sprint = Sprint::create([
        'space_id' => $this->h['space']->id,
        'project_id' => $this->h['list']->id,
        'name' => 'Sprint to Delete',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-14',
    ]);
    $this->subtask->update(['sprint_id' => $sprint->id]);

    actingAs($this->owner)
        ->delete(route('sprints.destroy', [$this->h['workspace'], $this->h['space'], $sprint]))
        ->assertRedirect();

    $this->assertDatabaseHas('subtasks', [
        'id' => $this->subtask->id,
        'sprint_id' => null,
    ]);
});
