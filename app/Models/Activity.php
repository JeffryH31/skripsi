<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'subject_type',
        'subject_id',
        'action',
        'properties',
        'changes',
    ];

    protected $casts = [
        'properties' => 'array',
        'changes' => 'array',
    ];

    protected $appends = [
        'description',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForWorkspace($query, Workspace $workspace)
    {
        return $query->where('workspace_id', $workspace->id);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForSubject($query, Model $subject)
    {
        return $query->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->id);
    }

    public static function log(
        Workspace $workspace,
        User $user,
        Model $subject,
        string $action,
        array $properties = [],
        array $changes = []
    ): self {
        return static::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'action' => $action,
            'properties' => $properties,
            'changes' => $changes,
        ]);
    }

    public function getDescriptionAttribute(): string
    {
        $subjectName = $this->properties['name'] ?? 'item';

        return match ($this->action) {
            'created' => "created {$subjectName}",
            'updated' => $this->buildUpdateDescription(),
            'deleted' => "deleted {$subjectName}",
            'completed' => "completed {$subjectName}",
            'reopened' => "reopened {$subjectName}",
            'assigned' => $this->buildAssignDescription(),
            'unassigned' => $this->buildUnassignDescription(),
            'moved' => $this->buildMovedDescription(),
            'commented' => $this->buildCommentDescription(),
            'comment_deleted' => 'deleted a comment',
            'comment_resolved' => 'resolved a comment',
            'time_logged' => $this->buildTimeLoggedDescription(),
            'status_changed' => $this->buildStatusChangedDescription(),
            'priority_changed' => $this->buildPriorityChangedDescription(),
            'member_added' => 'added a member',
            'member_removed' => 'removed a member',
            'member_role_updated' => 'updated a member role',
            'dependency_added' => 'added a dependency',
            'dependency_removed' => 'removed a dependency',
            'archived' => 'archived',
            'unarchived' => 'unarchived',
            'label_added' => $this->buildLabelDescription('added'),
            'label_removed' => $this->buildLabelDescription('removed'),
            'timer_started' => 'started timer',
            'timer_stopped' => $this->buildTimerStoppedDescription(),
            'time_updated' => $this->buildTimeUpdatedDescription(),
            'time_deleted' => $this->buildTimeDeletedDescription(),
            'deleted_subtask' => "deleted subtask {$subjectName}",
            'duplicated' => 'duplicated',
            default => "performed {$this->action}",
        };
    }

    private function buildUpdateDescription(): string
    {
        $changes = $this->getAttribute('changes') ?? [];
        if (empty($changes)) {
            return 'updated';
        }

        $changeList = [];
        foreach (array_slice($changes, 0, 3, true) as $field => $change) {
            $rawOld = $change['old'] ?? null;
            $rawNew = $change['new'] ?? null;

            // Skip array-value fields (e.g. raw assignee IDs) – they are logged separately
            if (is_array($rawOld) || is_array($rawNew)) {
                continue;
            }

            $minuteFields = ['time_estimate', 'optimistic_estimate', 'most_likely_estimate', 'pessimistic_estimate'];
            if (in_array($field, $minuteFields, true)) {
                $oldVal = $rawOld !== null ? $this->formatMinutes((int) $rawOld) : null;
                $newVal = $rawNew !== null ? $this->formatMinutes((int) $rawNew) : null;
            } else {
                $oldVal = $this->formatChangeValue($rawOld);
                $newVal = $this->formatChangeValue($rawNew);
            }

            // Guard against no-op logs where values are equivalent after normalization.
            if ($oldVal === $newVal) {
                continue;
            }

            $fieldLabel = $this->humanizeFieldName($field);

            if ($oldVal === null && $newVal !== null) {
                $changeList[] = "set {$fieldLabel} to {$newVal}";
            } elseif ($oldVal !== null && $newVal === null) {
                $changeList[] = "cleared {$fieldLabel}";
            } else {
                $changeList[] = "changed {$fieldLabel} from {$oldVal} to {$newVal}";
            }
        }

        if (empty($changeList)) {
            return 'updated';
        }

        $remaining = count($changes) - 3;
        $summary = implode(', ', $changeList);
        if ($remaining > 0) {
            $summary .= " and {$remaining} more";
        }

        return $summary;
    }

    /**
     * Convert a snake_case field name to a human-friendly label.
     */
    private function humanizeFieldName(string $field): string
    {
        $labels = [
            'name' => 'name',
            'description' => 'description',
            'status_id' => 'status',
            'priority_level' => 'priority',
            'due_date' => 'due date',
            'start_date' => 'start date',
            'baseline_start_date' => 'baseline start date',
            'baseline_due_date' => 'baseline due date',
            'time_estimate' => 'time estimate',
            'optimistic_estimate' => 'optimistic estimate',
            'most_likely_estimate' => 'most likely estimate',
            'pessimistic_estimate' => 'pessimistic estimate',
            'progress' => 'progress',
            'sprint_id' => 'sprint',
            'project_id' => 'project',
            'is_archived' => 'archived status',
            'hourly_rate' => 'hourly rate',
        ];

        return $labels[$field] ?? str_replace('_', ' ', $field);
    }

    /**
     * Normalize values for human-readable update summaries.
     */
    private function formatChangeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->format('M j, Y');
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}(?:[T\s].*)?$/', $value)) {
            try {
                return Carbon::parse($value)->format('M j, Y');
            } catch (\Throwable) {
                // Fall through and keep the raw value.
            }
        }

        // Truncate very long text values
        if (is_string($value) && mb_strlen($value) > 50) {
            return mb_substr($value, 0, 47).'...';
        }

        return is_scalar($value) ? (string) $value : null;
    }

    private function buildAssignDescription(): string
    {
        $changes = $this->getAttribute('changes') ?? [];

        if (! empty($this->properties['assignee_name'])) {
            return "assigned {$this->properties['assignee_name']}";
        }

        $assignees = $changes['assignees']['new'] ?? [];
        if (is_array($assignees) && ! empty($assignees)) {
            return 'assigned '.implode(', ', $assignees);
        }

        return 'assigned';
    }

    private function buildUnassignDescription(): string
    {
        $changes = $this->getAttribute('changes') ?? [];

        if (! empty($this->properties['assignee_name'])) {
            return "unassigned {$this->properties['assignee_name']}";
        }

        $assignees = $changes['assignees']['old'] ?? [];
        if (is_array($assignees) && ! empty($assignees)) {
            return 'unassigned '.implode(', ', $assignees);
        }

        return 'unassigned';
    }

    private function buildLabelDescription(string $action): string
    {
        $labelName = $this->properties['label_name'] ?? 'a label';

        return "{$action} label '{$labelName}'";
    }

    private function buildStatusChangedDescription(): string
    {
        $changes = $this->getAttribute('changes') ?? [];
        $old = $changes['status']['old'] ?? null;
        $new = $changes['status']['new'] ?? null;

        if ($old || $new) {
            return 'changed status from '.($old ?? 'none').' to '.($new ?? 'none');
        }

        return 'changed status';
    }

    private function buildPriorityChangedDescription(): string
    {
        $changes = $this->getAttribute('changes') ?? [];
        $old = $changes['priority']['old'] ?? null;
        $new = $changes['priority']['new'] ?? null;

        if ($new !== null && $old !== null) {
            return "changed priority from {$old} to {$new}";
        }

        if ($new !== null) {
            return "set priority to {$new}";
        }

        if ($old !== null) {
            return 'cleared priority';
        }

        return 'changed priority';
    }

    private function buildMovedDescription(): string
    {
        $changes = $this->getAttribute('changes') ?? [];
        $old = $changes['list']['old'] ?? null;
        $new = $changes['list']['new'] ?? null;

        if ($old || $new) {
            return 'moved from '.($old ?? 'unknown list').' to '.($new ?? 'unknown list');
        }

        return 'moved';
    }

    private function buildCommentDescription(): string
    {
        $preview = $this->properties['comment_preview'] ?? null;

        if ($preview) {
            return "commented: \"{$preview}\"";
        }

        return 'commented';
    }

    private function buildTimeLoggedDescription(): string
    {
        $duration = $this->properties['duration_formatted'] ?? null;

        if ($duration) {
            return "logged {$duration}";
        }

        return 'logged time';
    }

    private function buildTimerStoppedDescription(): string
    {
        $duration = $this->properties['duration_formatted'] ?? null;

        if ($duration) {
            return "stopped timer after {$duration}";
        }

        return 'stopped timer';
    }

    private function buildTimeUpdatedDescription(): string
    {
        $changes = $this->getAttribute('changes') ?? [];
        $old = $changes['duration']['old'] ?? null;
        $new = $changes['duration']['new'] ?? null;

        if ($old !== null || $new !== null) {
            return 'updated time entry from '.$this->formatMinutes($old).' to '.$this->formatMinutes($new);
        }

        return 'updated time entry';
    }

    private function buildTimeDeletedDescription(): string
    {
        $duration = $this->properties['duration'] ?? null;

        if ($duration !== null) {
            return 'deleted '.$this->formatMinutes($duration).' time entry';
        }

        return 'deleted time entry';
    }

    /**
     * Format minutes into a human-readable duration string.
     */
    private function formatMinutes(mixed $minutes): string
    {
        $m = (int) ($minutes ?? 0);
        if ($m < 60) {
            return "{$m}m";
        }
        $h = intdiv($m, 60);
        $rem = $m % 60;

        return $rem > 0 ? "{$h}h {$rem}m" : "{$h}h";
    }
}
