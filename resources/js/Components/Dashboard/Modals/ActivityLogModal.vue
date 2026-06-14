<script setup>
/**
 * ActivityLogModal Component
 * 
 * Modal showing activity history
 */
import { useDisplay } from 'vuetify';

const { smAndDown } = useDisplay();

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    activities: {
        type: Array,
        default: () => []
    },
    teamMembers: {
        type: Array,
        default: () => []
    },
    filterUserId: {
        type: Number,
        default: null
    }
});

const emit = defineEmits(['update:modelValue', 'update:filterUserId']);

const getMember = (memberId) => {
    return props.teamMembers.find(m => m.id === memberId);
};

const getActivityIcon = (type) => {
    const icons = {
        'time_logged': 'mdi-clock-plus-outline',
        'task_completed': 'mdi-checkbox-marked-circle-outline',
        'task_moved': 'mdi-arrow-right-bold',
        'task_assigned': 'mdi-account-plus',
        'task_created': 'mdi-plus-circle-outline',
        'estimation_updated': 'mdi-timer-edit-outline',
        'comment_added': 'mdi-comment-plus-outline'
    };
    return icons[type] || 'mdi-circle-outline';
};

const getActivityColor = (type) => {
    const colors = {
        'time_logged': 'primary',
        'task_completed': 'success',
        'task_moved': 'info',
        'task_assigned': 'warning',
        'task_created': 'secondary',
        'estimation_updated': 'purple',
        'comment_added': 'grey'
    };
    return colors[type] || 'grey';
};

const getActivityDescription = (activity) => {
    const user = getMember(activity.userId);
    const userName = user?.name || 'Unknown';

    switch (activity.type) {
        case 'time_logged':
            return `${userName} logged ${activity.hours}h on "${activity.taskTitle}"`;
        case 'task_completed':
            return `${userName} completed "${activity.taskTitle}"`;
        case 'task_moved':
            return `${userName} moved "${activity.taskTitle}" from ${activity.fromList} to ${activity.toList}`;
        case 'task_assigned':
            const assignee = getMember(activity.assignedTo);
            return `${userName} assigned "${activity.taskTitle}" to ${assignee?.name || 'Unknown'}`;
        case 'task_created':
            return `${userName} created "${activity.taskTitle}"`;
        case 'estimation_updated':
            return `${userName} updated estimation for "${activity.taskTitle}" (${activity.oldEstimate}h → ${activity.newEstimate}h)`;
        default:
            return `${userName} performed an action`;
    }
};

const formatActivityTime = (timestamp) => {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const handleClose = () => {
    emit('update:modelValue', false);
};
</script>

<template>
    <v-dialog 
        :model-value="modelValue" 
        @update:model-value="$emit('update:modelValue', $event)" 
        :max-width="smAndDown ? undefined : 600"
        :fullscreen="smAndDown"
        :transition="smAndDown ? 'dialog-bottom-transition' : 'dialog-transition'"
        scrollable
    >
        <v-card color="surface">
            <v-card-title class="d-flex align-center flex-wrap pa-4">
                <v-icon start>mdi-history</v-icon>
                Activity Log
                <v-spacer v-if="!smAndDown" />
                <v-btn v-if="smAndDown" icon variant="text" size="small" class="ml-auto" @click="handleClose">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <div v-if="smAndDown" class="px-4 pb-2">
                <v-select
                    :model-value="filterUserId"
                    @update:model-value="$emit('update:filterUserId', $event)"
                    :items="[{ id: null, name: 'All Members' }, ...teamMembers]"
                    item-title="name"
                    item-value="id"
                    variant="outlined"
                    density="compact"
                    hide-details
                />
            </div>
            <div v-else class="d-flex align-center px-4 pb-2">
                <v-select
                    :model-value="filterUserId"
                    @update:model-value="$emit('update:filterUserId', $event)"
                    :items="[{ id: null, name: 'All Members' }, ...teamMembers]"
                    item-title="name"
                    item-value="id"
                    variant="outlined"
                    density="compact"
                    hide-details
                    style="max-width: 180px;"
                    class="mr-2"
                />
                <v-spacer />
                <v-btn icon variant="text" size="small" @click="handleClose">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </div>
            <v-divider />
            <v-card-text class="pa-4">
                <v-timeline density="compact" side="end">
                    <v-timeline-item
                        v-for="activity in activities"
                        :key="activity.id"
                        :dot-color="getActivityColor(activity.type)"
                        size="small"
                    >
                        <template #icon>
                            <v-icon size="14" color="white">
                                {{ getActivityIcon(activity.type) }}
                            </v-icon>
                        </template>
                        <div class="d-flex align-start">
                            <div class="flex-grow-1">
                                <p class="text-body-2 mb-1">
                                    {{ getActivityDescription(activity) }}
                                </p>
                                <p v-if="activity.featureTitle" class="text-caption text-medium-emphasis">
                                    in {{ activity.featureTitle }}
                                </p>
                            </div>
                            <span class="text-caption text-medium-emphasis ml-2">
                                {{ formatActivityTime(activity.timestamp) }}
                            </span>
                        </div>
                    </v-timeline-item>
                </v-timeline>
                
                <div v-if="activities.length === 0" class="text-center py-8">
                    <v-icon size="48" color="grey-darken-1" class="mb-2">mdi-history</v-icon>
                    <p class="text-body-2 text-medium-emphasis">No activities yet</p>
                </div>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>
