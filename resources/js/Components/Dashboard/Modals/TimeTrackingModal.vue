<script setup>
/**
 * TimeTrackingModal Component
 * 
 * Modal for time tracking overview and management
 */
import { computed } from 'vue';
import { useDisplay } from 'vuetify';

const { smAndDown, mobile } = useDisplay();

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    teamMembers: {
        type: Array,
        default: () => []
    },
    timeStats: {
        type: Object,
        default: () => ({})
    },
    activeTimers: {
        type: Object,
        default: () => ({})
    }
});

const emit = defineEmits(['update:modelValue']);

const hasActiveTimers = computed(() => {
    return Object.keys(props.activeTimers).length > 0;
});

const getActiveTimersList = computed(() => {
    return Object.entries(props.activeTimers).map(([taskId, timer]) => ({
        taskId,
        ...timer
    }));
});

const formatDuration = (hours) => {
    const h = Math.floor(hours);
    const m = Math.round((hours - h) * 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
};

const getProgressColor = (percentage) => {
    if (percentage >= 100) return 'success';
    if (percentage >= 75) return 'warning';
    return 'primary';
};

const handleClose = () => {
    emit('update:modelValue', false);
};
</script>

<template>
    <v-dialog 
        :model-value="modelValue" 
        @update:model-value="$emit('update:modelValue', $event)" 
        :max-width="smAndDown ? undefined : 700"
        :fullscreen="smAndDown"
        :transition="smAndDown ? 'dialog-bottom-transition' : 'dialog-transition'"
        scrollable
    >
        <v-card color="surface">
            <v-card-title class="d-flex align-center pa-4">
                <v-icon start>mdi-chart-timeline-variant</v-icon>
                Time Tracking Overview
                <v-spacer />
                <v-btn icon variant="text" size="small" @click="handleClose">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-4">
                <!-- Active Timers Section -->
                <div v-if="hasActiveTimers" class="mb-6">
                    <div class="text-subtitle-2 text-medium-emphasis mb-3">
                        <v-icon start size="small" class="text-warning">mdi-timer-sand</v-icon>
                        Active Timers
                    </div>
                    <v-card 
                        v-for="timer in getActiveTimersList" 
                        :key="timer.taskId" 
                        color="warning" 
                        variant="tonal"
                        class="mb-2 pa-3"
                    >
                        <div class="d-flex align-center">
                            <div>
                                <div class="text-body-2 font-weight-medium">{{ timer.taskTitle }}</div>
                                <div class="text-caption text-medium-emphasis">{{ timer.featureTitle }}</div>
                            </div>
                            <v-spacer />
                            <v-chip color="warning" size="small">
                                <v-icon start size="small">mdi-clock-outline</v-icon>
                                Timer Running
                            </v-chip>
                        </div>
                    </v-card>
                </div>

                <!-- Team Time Stats -->
                <div class="text-subtitle-2 text-medium-emphasis mb-3">
                    <v-icon start size="small">mdi-account-group</v-icon>
                    Team Time Statistics
                </div>
                
                <!-- Mobile View: Card Layout -->
                <div v-if="smAndDown" class="team-stats-mobile">
                    <v-card 
                        v-for="member in teamMembers" 
                        :key="member.id"
                        color="surface-variant"
                        variant="tonal"
                        class="mb-2 pa-3"
                    >
                        <div class="d-flex align-center mb-2">
                            <v-avatar 
                                size="28" 
                                :color="member.avatarColor || 'primary'" 
                                class="mr-2"
                            >
                                <span class="text-caption">
                                    {{ member.name?.charAt(0).toUpperCase() }}
                                </span>
                            </v-avatar>
                            <span class="text-body-2 font-weight-medium">{{ member.name }}</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <v-chip size="small" variant="tonal">
                                Today: {{ formatDuration(timeStats[member.id]?.today || 0) }}
                            </v-chip>
                            <v-chip size="small" variant="tonal">
                                Week: {{ formatDuration(timeStats[member.id]?.week || 0) }}
                            </v-chip>
                            <v-chip size="small" variant="tonal">
                                Tasks: {{ timeStats[member.id]?.tasks || 0 }}
                            </v-chip>
                        </div>
                    </v-card>
                </div>

                <!-- Desktop View: Table Layout -->
                <v-table v-else density="compact">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th class="text-right">Today</th>
                            <th class="text-right">This Week</th>
                            <th class="text-right">Tasks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="member in teamMembers" :key="member.id">
                            <td>
                                <div class="d-flex align-center py-2">
                                    <v-avatar 
                                        size="28" 
                                        :color="member.avatarColor || 'primary'" 
                                        class="mr-2"
                                    >
                                        <span class="text-caption">
                                            {{ member.name?.charAt(0).toUpperCase() }}
                                        </span>
                                    </v-avatar>
                                    {{ member.name }}
                                </div>
                            </td>
                            <td class="text-right">
                                {{ formatDuration(timeStats[member.id]?.today || 0) }}
                            </td>
                            <td class="text-right">
                                {{ formatDuration(timeStats[member.id]?.week || 0) }}
                            </td>
                            <td class="text-right">
                                {{ timeStats[member.id]?.tasks || 0 }}
                            </td>
                        </tr>
                    </tbody>
                </v-table>

                <div v-if="teamMembers.length === 0" class="text-center py-8">
                    <v-icon size="48" color="grey-darken-1" class="mb-2">mdi-account-group</v-icon>
                    <p class="text-body-2 text-medium-emphasis">No team members found</p>
                </div>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>
