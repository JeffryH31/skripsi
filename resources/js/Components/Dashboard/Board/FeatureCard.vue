<script setup>
/**
 * FeatureCard Component
 * 
 * Individual feature card displayed in the kanban board
 */
const props = defineProps({
    feature: {
        type: Object,
        required: true
    },
    teamMembers: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['click', 'open-menu']);

// Helper functions
const getMember = (memberId) => {
    return props.teamMembers.find(m => m.id === memberId);
};

const getPriorityColor = (priority) => {
    const colors = {
        high: 'error',
        medium: 'warning',
        low: 'success'
    };
    return colors[priority] || 'grey';
};

const getProgressColor = (progress) => {
    if (progress >= 100) return 'success';
    if (progress >= 50) return 'primary';
    if (progress >= 25) return 'warning';
    return 'grey';
};

const getTotalTasks = (feature) => {
    return feature.taskLists?.reduce((sum, list) => sum + (list.tasks?.length || 0), 0) || 0;
};

const getCompletedTasks = (feature) => {
    return feature.taskLists?.reduce((sum, list) =>
        sum + (list.tasks?.filter(t => t.completed).length || 0), 0) || 0;
};

const formatDueDate = (date) => {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const isOverdue = (dueDate) => {
    if (!dueDate) return false;
    return new Date(dueDate) < new Date();
};

const isDueSoon = (dueDate) => {
    if (!dueDate) return false;
    const due = new Date(dueDate);
    const now = new Date();
    const diff = due - now;
    return diff > 0 && diff < 3 * 24 * 60 * 60 * 1000; // 3 days
};
</script>

<template>
    <v-card 
        class="feature-card mb-2" 
        color="surface-variant" 
        rounded="lg"
        @click="$emit('click')"
    >
        <v-card-text class="pa-3">
            <!-- Labels -->
            <div v-if="feature.labels?.length > 0" class="d-flex flex-wrap ga-1 mb-2">
                <div 
                    v-for="label in feature.labels.slice(0, 3)" 
                    :key="label.id"
                    class="feature-label" 
                    :style="{ backgroundColor: label.color }"
                    :title="label.name"
                />
                <v-chip 
                    v-if="feature.labels.length > 3" 
                    size="x-small" 
                    variant="text"
                    class="px-1"
                >
                    +{{ feature.labels.length - 3 }}
                </v-chip>
            </div>

            <!-- Title -->
            <h4 class="text-body-2 font-weight-medium mb-2">{{ feature.title }}</h4>

            <!-- Progress -->
            <div v-if="getTotalTasks(feature) > 0" class="mb-2">
                <div class="d-flex align-center justify-space-between mb-1">
                    <span class="text-caption text-medium-emphasis">
                        {{ getCompletedTasks(feature) }}/{{ getTotalTasks(feature) }} tasks
                    </span>
                    <span class="text-caption font-weight-bold" :class="`text-${getProgressColor(feature.progress)}`">
                        {{ feature.progress || 0 }}%
                    </span>
                </div>
                <v-progress-linear 
                    :model-value="feature.progress || 0" 
                    :color="getProgressColor(feature.progress)"
                    height="4" 
                    rounded 
                />
            </div>

            <!-- Meta info -->
            <div class="d-flex align-center flex-wrap ga-2">
                <!-- Due Date -->
                <v-chip 
                    v-if="feature.dueDate" 
                    size="x-small"
                    :color="isOverdue(feature.dueDate) ? 'error' : isDueSoon(feature.dueDate) ? 'warning' : 'default'"
                    :variant="isOverdue(feature.dueDate) || isDueSoon(feature.dueDate) ? 'flat' : 'tonal'"
                >
                    <v-icon start size="12">mdi-calendar</v-icon>
                    {{ formatDueDate(feature.dueDate) }}
                </v-chip>

                <!-- Priority -->
                <v-chip size="x-small" :color="getPriorityColor(feature.priority)" variant="tonal">
                    <v-icon size="10">mdi-flag</v-icon>
                </v-chip>

                <v-spacer />

                <!-- Assignees -->
                <div v-if="feature.assignees?.length > 0" class="d-flex">
                    <v-avatar 
                        v-for="assigneeId in feature.assignees.slice(0, 2)"
                        :key="assigneeId" 
                        size="22" 
                        :color="getMember(assigneeId)?.color || 'grey'"
                        class="feature-assignee"
                    >
                        <span class="text-caption" style="font-size: 9px;">
                            {{ getMember(assigneeId)?.avatar || '?' }}
                        </span>
                    </v-avatar>
                    <v-avatar 
                        v-if="feature.assignees.length > 2" 
                        size="22"
                        color="grey-darken-1" 
                        class="feature-assignee"
                    >
                        <span class="text-caption" style="font-size: 9px;">
                            +{{ feature.assignees.length - 2 }}
                        </span>
                    </v-avatar>
                </div>
            </div>
        </v-card-text>
    </v-card>
</template>

<style scoped>
.feature-card {
    cursor: pointer;
    transition: transform 0.1s ease, box-shadow 0.1s ease;
}

.feature-card:hover {
    transform: translateY(-2px);
}

@media (hover: none) {
    .feature-card:hover {
        transform: none;
    }
}

.feature-label {
    width: 40px;
    height: 8px;
    border-radius: 4px;
}

.feature-assignee {
    margin-left: -6px;
    border: 2px solid rgb(var(--v-theme-surface-variant));
}

.feature-assignee:first-child {
    margin-left: 0;
}
</style>
