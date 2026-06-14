<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TaskDependency Model
 *
 * @property int $id
 * @property int $task_id
 * @property int $depends_on_id
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TaskDependency extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'depends_on_id',
        'type',
    ];

    protected $attributes = [
        'type' => 'waiting_on',
    ];

    /**
     * Dependency types.
     */
    public const TYPE_WAITING_ON = 'waiting_on';

    public const TYPE_BLOCKING = 'blocking';

    /**
     * Get the task that has the dependency.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the task that this task depends on.
     */
    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'depends_on_id');
    }

    /**
     * Check if the dependency is "waiting on" type.
     */
    public function isWaitingOn(): bool
    {
        return $this->type === self::TYPE_WAITING_ON;
    }

    /**
     * Check if the dependency is "blocking" type.
     */
    public function isBlocking(): bool
    {
        return $this->type === self::TYPE_BLOCKING;
    }
}
