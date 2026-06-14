<script setup>
/**
 * TaskCard Component
 * 
 * Individual task card used within FeatureModal
 */
import { computed } from 'vue';

const props = defineProps({
    task: {
        type: Object,
        required: true
    },
    teamMembers: {
        type: Array,
        default: () => []
    },
    isTimerActive: {
        type: Boolean,
        default: false
    },
    timerDisplay: {
        type: String,
        default: ''
    }
});

const emit = defineEmits([
    'edit', 
    'delete', 
    'toggle-complete', 
    'start-timer', 
    'pause-timer', 
    'stop-timer',
    'discard-timer',
    'add-time'
]);

const getAssignee = computed(() => {
    if (!props.task.assigneeId) return null;
    return props.teamMembers.find(m => m.id === props.task.assigneeId);
});

const getPriorityColor = (priority) => {
    const colors = { high: 'error', medium: 'warning', low: 'success' };
    return colors[priority] || 'grey';
};

const getPriorityIcon = (priority) => {
    const icons = { high: 'mdi-chevron-triple-up', medium: 'mdi-chevron-up', low: 'mdi-chevron-down' };
    return icons[priority] || 'mdi-minus';
};

const formatDuration = (hours) => {
    if (!hours || hours === 0) return '0h';
    const h = Math.floor(hours);
    const m = Math.round((hours - h) * 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
};

const getProgressPercentage = computed(() => {
    if (!props.task.estimatedHours || props.task.estimatedHours === 0) return 0;
    return Math.min(100, Math.round((props.task.loggedHours / props.task.estimatedHours) * 100));
});

const getProgressColor = computed(() => {
    const pct = getProgressPercentage.value;
    if (pct >= 100) return 'error';
    if (pct >= 75) return 'warning';
    return 'primary';
});
</script>

<template>
    <v-card 
        class="task-card mb-2" 
        color="surface-variant"
        :class="{ 'completed-task': task.completed }"
        rounded="lg"
    >
        <v-card-text class="pa-3">
            <div class="d-flex align-start">
                <!-- Checkbox -->
                <v-checkbox
                    :model-value="task.completed"
                    hide-details
                    density="compact"
                    class="mt-0 mr-2"
                    @update:model-value="$emit('toggle-complete', task)"
                />

                <div class="flex-grow-1">
                    <!-- Task Title -->
                    <div 
                        class="text-body-2 mb-1" 
                        :class="{ 'text-decoration-line-through text-medium-emphasis': task.completed }"
                    >
                        {{ task.title }}
                    </div>

                    <!-- Task Meta -->
                    <div class="d-flex align-center flex-wrap gap-2 mt-2">
                        <!-- Priority -->
                        <v-chip 
                            v-if="task.priority" 
                            :color="getPriorityColor(task.priority)"
                            size="x-small"
                            variant="tonal"
                        >
                            <v-icon start size="12">{{ getPriorityIcon(task.priority) }}</v-icon>
                            {{ task.priority }}
                        </v-chip>

                        <!-- Time Progress -->
                        <v-chip v-if="task.estimatedHours" size="x-small" variant="tonal">
                            <v-icon start size="12">mdi-clock-outline</v-icon>
                            {{ formatDuration(task.loggedHours || 0) }} / {{ formatDuration(task.estimatedHours) }}
                        </v-chip>

                        <!-- Assignee -->
                        <v-avatar 
                            v-if="getAssignee" 
                            size="20" 
                            :color="getAssignee.avatarColor || 'primary'"
                        >
                            <span style="font-size: 10px;">{{ getAssignee.name?.charAt(0).toUpperCase() }}</span>
                        </v-avatar>
                    </div>

                    <!-- Progress Bar -->
                    <v-progress-linear
                        v-if="task.estimatedHours"
                        :model-value="getProgressPercentage"
                        :color="getProgressColor"
                        height="4"
                        rounded
                        class="mt-2"
                    />
                </div>

                <!-- Actions Menu -->
                <v-menu location="bottom end">
                    <template #activator="{ props: menuProps }">
                        <v-btn icon variant="text" size="x-small" v-bind="menuProps">
                            <v-icon size="16">mdi-dots-vertical</v-icon>
                        </v-btn>
                    </template>
                    <v-list density="compact" nav>
                        <v-list-item @click="$emit('edit', task)">
                            <template #prepend><v-icon size="18">mdi-pencil</v-icon></template>
                            <v-list-item-title>Edit</v-list-item-title>
                        </v-list-item>
                        <v-list-item @click="$emit('add-time', task)">
                            <template #prepend><v-icon size="18">mdi-clock-plus</v-icon></template>
                            <v-list-item-title>Add Time</v-list-item-title>
                        </v-list-item>
                        <v-divider />
                        <v-list-item class="text-error" @click="$emit('delete', task)">
                            <template #prepend><v-icon size="18" color="error">mdi-delete</v-icon></template>
                            <v-list-item-title>Delete</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>
            </div>

            <!-- Timer Controls -->
            <div v-if="!task.completed" class="timer-controls mt-2 pt-2 border-t">
                <template v-if="isTimerActive">
                    <div class="d-flex align-center gap-2">
                        <v-chip color="success" size="small" variant="tonal">
                            <v-icon start size="14">mdi-timer</v-icon>
                            {{ timerDisplay }}
                        </v-chip>
                        <v-btn icon size="x-small" color="warning" variant="tonal" @click="$emit('pause-timer', task)">
                            <v-icon size="14">mdi-pause</v-icon>
                        </v-btn>
                        <v-btn icon size="x-small" color="success" variant="tonal" @click="$emit('stop-timer', task)">
                            <v-icon size="14">mdi-stop</v-icon>
                        </v-btn>
                        <v-btn icon size="x-small" color="error" variant="text" @click="$emit('discard-timer', task)">
                            <v-icon size="14">mdi-close</v-icon>
                        </v-btn>
                    </div>
                </template>
                <template v-else>
                    <v-btn 
                        size="x-small" 
                        variant="tonal" 
                        color="primary"
                        prepend-icon="mdi-play"
                        @click="$emit('start-timer', task)"
                    >
                        Start Timer
                    </v-btn>
                </template>
            </div>
        </v-card-text>
    </v-card>
</template>

<style scoped>
.task-card {
    transition: all 0.2s ease;
}

.task-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.completed-task {
    opacity: 0.7;
}

.border-t {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
