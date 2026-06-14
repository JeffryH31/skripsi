<script setup>
import { computed } from 'vue';
import { formatHours as formatDuration } from '@/utils/duration';

const props = defineProps({
    cpmData: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['subtask-click']);

const isSuccess = computed(() => props.cpmData?.success);
const summary = computed(() => props.cpmData?.data?.summary || {});
const criticalPath = computed(() => props.cpmData?.data?.criticalPath || []);
const subtasks = computed(() => props.cpmData?.data?.subtasks || {});

// Get critical subtask objects
const criticalSubtasks = computed(() => {
    return criticalPath.value.map(id => subtasks.value[id]).filter(Boolean);
});

// Stats
const stats = computed(() => {
    const all = Object.values(subtasks.value);
    return {
        total: all.length,
        critical: criticalPath.value.length,
        completed: all.filter(s => s.completedAt).length,
        withSlack: all.filter(s => s.slack > 0).length,
    };
});


// Handle click on subtask
const handleSubtaskClick = (subtask) => {
    emit('subtask-click', subtask);
};
</script>

<template>
    <v-card class="cpm-summary bg-[#2d2d30] border border-gray-700" variant="flat">
        <v-card-title class="d-flex align-center gap-2 py-3 border-b border-gray-700">
            <v-icon color="primary">mdi-chart-timeline-variant</v-icon>
            <span>CPM Analysis</span>
        </v-card-title>

        <!-- Error State -->
        <v-card-text v-if="!isSuccess" class="text-center py-8">
            <v-icon size="48" color="warning" class="mb-4">mdi-alert-circle-outline</v-icon>
            <p class="text-gray-400">{{ cpmData?.message || 'Unable to calculate CPM' }}</p>
            <p class="text-sm text-gray-500 mt-2">
                Ensure subtasks have time estimates and no circular dependencies.
            </p>
        </v-card-text>

        <!-- Success State -->
        <v-card-text v-else class="py-4">
            <!-- Stats Row -->
            <div class="grid grid-cols-4 gap-3 mb-4">
                <div class="stat-card bg-[#1e1e1e] rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-white">{{ stats.total }}</div>
                    <div class="text-xs text-gray-400">Total Subtasks</div>
                </div>
                <div class="stat-card bg-[#1e1e1e] rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-red-400">{{ stats.critical }}</div>
                    <div class="text-xs text-gray-400">Critical</div>
                </div>
                <div class="stat-card bg-[#1e1e1e] rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-green-400">{{ stats.completed }}</div>
                    <div class="text-xs text-gray-400">Completed</div>
                </div>
                <div class="stat-card bg-[#1e1e1e] rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-blue-400">{{ stats.withSlack }}</div>
                    <div class="text-xs text-gray-400">With Slack</div>
                </div>
            </div>

            <!-- Project Duration -->
            <div class="bg-[#1e1e1e] rounded-lg p-4 mb-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-400">Project Duration</span>
                    <span class="text-xl font-semibold text-white">
                        {{ formatDuration(summary.projectDurationHours) }}
                    </span>
                </div>
                <v-progress-linear :model-value="(stats.completed / stats.total) * 100" color="success" bg-color="#333"
                    height="8" rounded />
                <div class="flex justify-between mt-1 text-xs text-gray-500">
                    <span>Progress</span>
                    <span>{{ Math.round((stats.completed / stats.total) * 100) }}%</span>
                </div>
            </div>

            <!-- Critical Path -->
            <div class="mb-2">
                <div class="flex items-center gap-2 mb-2">
                    <v-icon size="18" color="error">mdi-alert-circle</v-icon>
                    <span class="text-sm font-medium text-gray-300">Critical Path</span>
                </div>

                <div v-if="criticalSubtasks.length === 0" class="text-sm text-gray-500 italic">
                    No critical path identified (no dependencies or all tasks parallel)
                </div>

                <div v-else class="flex flex-wrap items-center gap-1">
                    <template v-for="(subtask, index) in criticalSubtasks" :key="subtask.id">
                        <v-chip size="small" color="error" variant="tonal" class="cursor-pointer"
                            @click="handleSubtaskClick(subtask)">
                            {{ subtask.name }}
                        </v-chip>
                        <v-icon v-if="index < criticalSubtasks.length - 1" size="16" class="text-gray-600">
                            mdi-arrow-right
                        </v-icon>
                    </template>
                </div>
            </div>

            <!-- Info Note -->
            <v-alert type="info" variant="tonal" density="compact" class="mt-4 text-xs">
                <strong>Critical Path:</strong> Any delay in critical tasks directly delays the project.
                Tasks with slack (float) can be delayed without affecting the deadline.
            </v-alert>
        </v-card-text>
    </v-card>
</template>

<style scoped>
.cpm-summary {
    border-radius: 8px;
}

.stat-card {
    transition: transform 0.2s ease, background-color 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    background-color: #252526;
}
</style>
