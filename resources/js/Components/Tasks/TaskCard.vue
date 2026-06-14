<script setup>
import { computed, inject, ref } from 'vue';
import { PRIORITY_MAP } from '@/constants/priorities';

const props = defineProps({
    task: {
        type: Object,
        required: true,
    },
    showList: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
    showCheckbox: {
        type: Boolean,
        default: true,
    },
    highlightedSubtask: {
        type: Object,
        default: null,
    },
    parentTaskName: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['click', 'complete', 'open-detail', 'toggle-subtask', 'open-subtask']);

// Inject CPM critical path checker (provided by parent)
const isSubtaskCritical = inject('isSubtaskCritical', () => false);

// Check if this task/subtask is on critical path
const isCritical = computed(() => isSubtaskCritical(props.task.id));

const priority = computed(() => {
    if (!props.task.priority_level) return null;
    return PRIORITY_MAP[props.task.priority_level] || null;
});

// Status color for left border
const statusColor = computed(() => props.task.status?.color || '#6b7280');

// Due date formatting
const dueDate = computed(() => {
    if (!props.task.due_date) return null;
    const date = new Date(props.task.due_date);

    // For completed tasks measuring against when it was completed
    if (props.task.completed_at) {
        const completed = new Date(props.task.completed_at);
        const lateDays = Math.ceil((completed - date) / (1000 * 60 * 60 * 24));
        if (lateDays > 0) {
            return { text: `${lateDays}d late`, color: '#f87171', overdue: true };
        }
        return { text: 'On time', color: '#4ade80', overdue: false };
    }

    const now = new Date();
    const diffDays = Math.ceil((date - now) / (1000 * 60 * 60 * 24));

    if (diffDays < 0) {
        return { text: `${Math.abs(diffDays)}d overdue`, color: '#f87171', overdue: true };
    } else if (diffDays === 0) {
        return { text: 'Today', color: '#fbbf24', overdue: false };
    } else if (diffDays === 1) {
        return { text: 'Tomorrow', color: '#60a5fa', overdue: false };
    } else if (diffDays <= 7) {
        return { text: `${diffDays}d`, color: '#9ca3af', overdue: false };
    } else {
        return { text: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }), color: '#9ca3af', overdue: false };
    }
});

// Is completed
const isCompleted = computed(() => !!props.task.completed_at);

// Progress for subtasks
const subtaskProgress = computed(() => {
    if (!props.task.subtasks_count) return null;
    const completed = props.task.completed_subtasks_count || 0;
    const total = props.task.subtasks_count;
    return {
        completed,
        total,
        percentage: Math.round((completed / total) * 100),
    };
});

// Display assignees: prefer highlighted subtask assignees (dashboard context),
// then direct task assignees, then aggregated from all subtasks.
const displayAssignees = computed(() => {
    const toList = (value) => {
        if (Array.isArray(value)) return value;
        if (Array.isArray(value?.data)) return value.data;
        if (value && typeof value === 'object') return Object.values(value).filter(Boolean);
        return [];
    };

    // When a highlighted subtask is provided, show its assignees
    if (props.highlightedSubtask) {
        const subtaskAssignees = toList(props.highlightedSubtask.assignees);
        if (subtaskAssignees.length) return subtaskAssignees;
    }

    const directAssignees = toList(props.task.assignees);
    if (directAssignees.length) return directAssignees;

    const subtasks = toList(props.task.subtasks);
    if (subtasks.length === 0) return [];

    const assigneeMap = new Map();
    subtasks.forEach(subtask => {
        const subtaskAssignees = toList(subtask?.assignees);
        if (subtaskAssignees.length) {
            subtaskAssignees.forEach(a => {
                if (!assigneeMap.has(a.id)) assigneeMap.set(a.id, a);
            });
        }
    });
    return Array.from(assigneeMap.values());
});

// Aggregate time spent from subtasks
const aggregatedTimeSpent = computed(() => {
    if (!props.task.subtasks || props.task.subtasks.length === 0) return null;
    const totalMinutes = props.task.subtasks.reduce((sum, s) => sum + (s.time_spent || 0), 0);
    if (totalMinutes === 0) return null;
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
});

const showSubtasks = ref(false);

const topLevelSubtasks = computed(() => {
    // Task mode: task.subtasks (Task -> Subtask, needs parent_id filter)
    if (props.task.subtasks?.length) {
        return props.task.subtasks.filter(s => !s.parent_id);
    }
    // Subtask mode: task.children (Subtask -> Subtask, already scoped by parent_id)
    if (props.task.children?.length) {
        return props.task.children;
    }
    return [];
});

// Handle complete toggle
const toggleComplete = (e) => {
    e.stopPropagation();
    emit('complete', props.task);
};

// Open task detail
const openDetail = () => {
    emit('open-detail', props.task);
};
</script>

<template>
    <div class="task-card" :class="{
        'task-card--completed': isCompleted,
        'task-card--critical': isCritical && !isCompleted,
        'task-card--no-checkbox': !showCheckbox,
    }" @click="openDetail">
        <!-- Left status color strip -->
        <div class="task-card__status-bar" :style="{ backgroundColor: statusColor }"></div>

        <div class="task-card__content">
            <!-- Top: Critical indicator -->
            <div v-if="isCritical && !isCompleted" class="task-card__id-row">
                <v-tooltip location="top">
                    <template #activator="{ props: tp }">
                        <v-icon v-bind="tp" size="12" color="error" class="task-card__critical-badge">
                            mdi-alert-circle
                        </v-icon>
                    </template>
                    <span>On Critical Path</span>
                </v-tooltip>
            </div>

            <!-- Parent task context -->
            <div v-if="parentTaskName" class="task-card__subtask-hint">
                <span class="task-card__subtask-hint-text">{{ parentTaskName }}</span>
            </div>

            <!-- Task name row -->
            <div class="task-card__name-row">
                <button v-if="showCheckbox" class="task-card__checkbox"
                    :class="{ 'task-card__checkbox--done': isCompleted }"
                    :style="!isCompleted ? { borderColor: statusColor } : {}" @click="toggleComplete">
                    <v-icon v-if="isCompleted" size="12" color="white">mdi-check</v-icon>
                </button>
                <v-icon v-if="parentTaskName" size="11" color="grey-lighten-1" class="flex-shrink-0">mdi-subdirectory-arrow-right</v-icon>
                <span class="task-card__name" :class="{ 'task-card__name--done': isCompleted }">
                    {{ task.name }}
                </span>
            </div>

            <!-- Highlighted subtask -->
            <div v-if="!parentTaskName && highlightedSubtask" class="task-card__subtask-hint">
                <v-icon size="11" color="grey-lighten-1">mdi-subdirectory-arrow-right</v-icon>
                <span class="task-card__subtask-hint-text">{{ highlightedSubtask.name }}</span>
            </div>

            <!-- Labels -->
            <div v-if="task.labels?.length" class="task-card__labels">
                <span v-for="label in task.labels.slice(0, 4)" :key="label.id" class="task-card__label"
                    :style="{ backgroundColor: label.color + '22', color: label.color, borderColor: label.color + '44' }">
                    {{ label.name }}
                </span>
                <span v-if="task.labels.length > 4" class="task-card__label task-card__label--more">
                    +{{ task.labels.length - 4 }}
                </span>
            </div>

            <!-- Bottom meta row -->
            <div v-if="!compact" class="task-card__footer">
                <div class="task-card__meta">
                    <!-- Priority -->
                    <v-tooltip v-if="priority" location="top">
                        <template #activator="{ props: tp }">
                            <div v-bind="tp" class="task-card__meta-item">
                                <v-icon size="14" :style="{ color: priority.color }">mdi-flag</v-icon>
                            </div>
                        </template>
                        <span>{{ priority.name }}</span>
                    </v-tooltip>

                    <!-- Due Date -->
                    <div v-if="dueDate" class="task-card__meta-item" :style="{ color: dueDate.color }">
                        <v-icon size="13" :style="{ color: dueDate.color }">mdi-calendar-blank-outline</v-icon>
                        <span>{{ dueDate.text }}</span>
                    </div>

                    <!-- Subtask Progress -->
                    <div v-if="subtaskProgress" class="task-card__meta-item task-card__meta-item--subtle">
                        <v-icon size="13">mdi-file-tree-outline</v-icon>
                        <span>{{ subtaskProgress.completed }}/{{ subtaskProgress.total }}</span>
                    </div>

                    <!-- Time -->
                    <div v-if="aggregatedTimeSpent" class="task-card__meta-item task-card__meta-item--subtle">
                        <v-icon size="13">mdi-clock-outline</v-icon>
                        <span>{{ aggregatedTimeSpent }}</span>
                    </div>

                    <!-- Comments -->
                    <div v-if="task.comments_count" class="task-card__meta-item task-card__meta-item--subtle">
                        <v-icon size="13">mdi-chat-outline</v-icon>
                        <span>{{ task.comments_count }}</span>
                    </div>
                </div>

                <!-- Assignees -->
                <div v-if="displayAssignees.length" class="task-card__assignees">
                    <v-tooltip v-for="assignee in displayAssignees.slice(0, 3)" :key="assignee.id" location="top">
                        <template #activator="{ props: tp }">
                            <v-avatar v-bind="tp" :color="assignee.avatar_color || '#4f46e5'" size="24"
                                class="task-card__avatar">
                                <img v-if="assignee.profile_photo_url" :src="assignee.profile_photo_url"
                                    :alt="assignee.name" />
                                <span v-else class="task-card__avatar-text">{{ assignee.name?.charAt(0) }}</span>
                            </v-avatar>
                        </template>
                        <span>{{ assignee.name }}</span>
                    </v-tooltip>
                    <v-avatar v-if="displayAssignees.length > 3" color="#374151" size="24" class="task-card__avatar">
                        <span class="task-card__avatar-text">+{{ displayAssignees.length - 3 }}</span>
                    </v-avatar>
                </div>
            </div>

            <!-- Inline subtask rows (ClickUp-style) -->
            <div v-if="topLevelSubtasks.length" class="task-card__subtask-section" @click.stop>
                <button class="task-card__subtask-toggle" @click.stop="showSubtasks = !showSubtasks">
                    <v-icon size="11" class="task-card__subtask-toggle-icon">
                        {{ showSubtasks ? 'mdi-chevron-down' : 'mdi-chevron-right' }}
                    </v-icon>
                    <span>{{ topLevelSubtasks.filter(s => s.completed_at).length }}/{{ topLevelSubtasks.length }} subtask</span>
                </button>
                <div v-if="showSubtasks" class="task-card__subtask-rows">
                    <div v-for="subtask in topLevelSubtasks" :key="subtask.id"
                        class="task-card__subtask-row"
                        @click.stop="emit('open-subtask', subtask)">
                        <button
                            class="task-card__subtask-check"
                            :class="{ 'task-card__subtask-check--done': subtask.completed_at }"
                            :style="!subtask.completed_at ? { borderColor: subtask.status?.color || '#6b7280' } : {}"
                            @click.stop="emit('toggle-subtask', subtask)">
                            <v-icon v-if="subtask.completed_at" size="9" color="white">mdi-check</v-icon>
                        </button>
                        <span class="task-card__subtask-row-name"
                            :class="{ 'task-card__subtask-row-name--done': subtask.completed_at }">
                            {{ subtask.name }}
                        </span>
                        <v-avatar v-if="subtask.assignees?.length"
                            :color="subtask.assignees[0].avatar_color || '#4f46e5'"
                            size="14" class="task-card__subtask-row-avatar flex-shrink-0">
                            <img v-if="subtask.assignees[0].profile_photo_url"
                                :src="subtask.assignees[0].profile_photo_url"
                                :alt="subtask.assignees[0].name" />
                            <span v-else style="font-size: 8px; font-weight: 600;">
                                {{ subtask.assignees[0].name?.charAt(0) }}
                            </span>
                        </v-avatar>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ClickUp-style card */
.task-card {
    display: flex;
    background: #1e1e2e;
    border: 1px solid #2e2e3e;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
    overflow: hidden;
    position: relative;
}

.task-card:hover {
    background: #242438;
    border-color: #3e3e52;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.task-card--completed {
    opacity: 1;
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.08) 0%, #1e2430 55%);
    border-color: #35506a;
}

.task-card--completed:hover {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.12) 0%, #222a38 55%);
    border-color: #3f6282;
}

.task-card--completed .task-card__status-bar {
    opacity: 0.7;
}

.task-card--completed .task-card__name {
    color: #d1d9e5;
}

.task-card--completed .task-card__name--done {
    color: #9fb0c4;
}

.task-card--completed .task-card__meta-item {
    color: #9fb0c4;
}

.task-card--completed .task-card__meta-item--subtle {
    color: #89a0b8;
}

.task-card--critical {
    border-color: rgba(239, 68, 68, 0.5);
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.06) 0%, #1e1e2e 40%);
}

.task-card--critical:hover {
    border-color: rgba(239, 68, 68, 0.7);
    box-shadow: 0 2px 12px rgba(239, 68, 68, 0.15);
}

/* Left colored strip */
.task-card__status-bar {
    width: 4px;
    flex-shrink: 0;
    border-radius: 8px 0 0 8px;
}

/* Content area */
.task-card__content {
    flex: 1;
    min-width: 0;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

/* Task ID */
.task-card__id-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.task-card__id {
    font-size: 10px;
    color: #6b7280;
    font-weight: 500;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.task-card__critical-badge {
    animation: critical-pulse 2s infinite;
}

@keyframes critical-pulse {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.5;
    }
}

/* Name row */
.task-card__name-row {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.task-card__checkbox {
    width: 16px;
    height: 16px;
    min-width: 16px;
    border-radius: 4px;
    border: 2px solid #6b7280;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    margin-top: 2px;
}

.task-card__checkbox:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: scale(1.1);
}

.task-card__checkbox--done {
    background: #22c55e !important;
    border-color: #22c55e !important;
}

.task-card__name {
    font-size: 13px;
    font-weight: 500;
    color: #e5e7eb;
    line-height: 1.45;
    word-break: break-word;
    flex: 1;
    min-width: 0;
}

.task-card__name--done {
    text-decoration: line-through;
    color: #6b7280;
}

/* Labels */
.task-card__labels {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding-left: 24px;
}

.task-card__label {
    font-size: 10px;
    font-weight: 600;
    padding: 1px 8px;
    border-radius: 10px;
    border: 1px solid;
    white-space: nowrap;
    line-height: 1.6;
}

.task-card__label--more {
    background: rgba(107, 114, 128, 0.15);
    color: #9ca3af;
    border-color: rgba(107, 114, 128, 0.3);
}

/* Footer meta row */
.task-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-left: 24px;
    margin-top: 2px;
}

.task-card__meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.task-card__meta-item {
    display: flex;
    align-items: center;
    gap: 3px;
    font-size: 11px;
    font-weight: 500;
    color: #9ca3af;
    white-space: nowrap;
}

.task-card__meta-item--subtle {
    color: #6b7280;
}

/* Assignees */
.task-card__assignees {
    display: flex;
    flex-shrink: 0;
}

.task-card__avatar {
    border: 2px solid #1e1e2e;
    margin-left: -6px;
    cursor: default;
    font-size: 11px;
}

.task-card__avatar:first-child {
    margin-left: 0;
}

.task-card__avatar-text {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
}

.task-card--no-checkbox .task-card__labels,
.task-card--no-checkbox .task-card__footer {
    padding-left: 0;
}

/*  Inline subtask rows  */
.task-card__subtask-section {
    margin-top: 2px;
    border-top: 1px solid rgba(255, 255, 255, 0.04);
    padding-top: 4px;
}

.task-card__subtask-toggle {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: #6b7280;
    background: none;
    border: none;
    padding: 2px 0;
    cursor: pointer;
    width: 100%;
    transition: color 0.15s ease;
    text-align: left;
}

.task-card__subtask-toggle:hover {
    color: #9ca3af;
}

.task-card__subtask-toggle-icon {
    opacity: 0.7;
}

.task-card__subtask-rows {
    display: flex;
    flex-direction: column;
    gap: 1px;
    margin-top: 2px;
}

.task-card__subtask-row {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 3px 4px;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.1s ease;
    min-height: 24px;
}

.task-card__subtask-row:hover {
    background: rgba(255, 255, 255, 0.04);
}

.task-card__subtask-check {
    width: 12px;
    height: 12px;
    min-width: 12px;
    border-radius: 3px;
    border: 1.5px solid #6b7280;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.task-card__subtask-check:hover {
    background: rgba(255, 255, 255, 0.08);
}

.task-card__subtask-check--done {
    background: #22c55e !important;
    border-color: #22c55e !important;
}

.task-card__subtask-row-name {
    font-size: 11px;
    color: #d1d5db;
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.task-card__subtask-row-name--done {
    text-decoration: line-through;
    color: #6b7280;
}

.task-card__subtask-row-avatar {
    border: 1.5px solid #1e1e2e;
}
</style>
