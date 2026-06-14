<script setup>
/**
 * FeatureModal Component
 * 
 * Modal for viewing and editing a feature with nested task kanban
 */
import { ref, computed, watch } from 'vue';
import { useDisplay } from 'vuetify';
import TaskKanban from './TaskKanban.vue';

const { smAndDown, mobile } = useDisplay();

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    feature: {
        type: Object,
        default: null
    },
    teamMembers: {
        type: Array,
        default: () => []
    },
    availableLabels: {
        type: Array,
        default: () => []
    },
    activeTimers: {
        type: Object,
        default: () => ({})
    },
    timerDisplays: {
        type: Object,
        default: () => ({})
    }
});

const emit = defineEmits([
    'update:modelValue',
    'update-feature',
    'delete-feature',
    'add-task-list',
    'rename-task-list',
    'delete-task-list',
    'add-task',
    'edit-task',
    'delete-task',
    'toggle-task-complete',
    'move-task',
    'start-timer',
    'pause-timer',
    'stop-timer',
    'discard-timer',
    'add-time'
]);

const activeTab = ref('tasks');
const isEditingTitle = ref(false);
const editedTitle = ref('');
const isEditingDescription = ref(false);
const editedDescription = ref('');

// Local copy of feature for editing
const localFeature = ref(null);

watch(() => props.modelValue, (isOpen) => {
    if (isOpen && props.feature) {
        localFeature.value = JSON.parse(JSON.stringify(props.feature));
        activeTab.value = 'tasks';
        isEditingTitle.value = false;
        isEditingDescription.value = false;
    }
});

watch(() => props.feature, (newFeature) => {
    if (props.modelValue && newFeature) {
        localFeature.value = JSON.parse(JSON.stringify(newFeature));
    }
}, { deep: true });

// Computed
const totalTasks = computed(() => {
    if (!localFeature.value?.taskLists) return 0;
    return localFeature.value.taskLists.reduce((sum, list) => sum + (list.tasks?.length || 0), 0);
});

const completedTasks = computed(() => {
    if (!localFeature.value?.taskLists) return 0;
    return localFeature.value.taskLists.reduce((sum, list) => {
        return sum + (list.tasks?.filter(t => t.completed).length || 0);
    }, 0);
});

const progressPercentage = computed(() => {
    if (totalTasks.value === 0) return 0;
    return Math.round((completedTasks.value / totalTasks.value) * 100);
});

const totalLoggedHours = computed(() => {
    if (!localFeature.value?.taskLists) return 0;
    return localFeature.value.taskLists.reduce((sum, list) => {
        return sum + (list.tasks?.reduce((taskSum, t) => taskSum + (t.loggedHours || 0), 0) || 0);
    }, 0);
});

const totalEstimatedHours = computed(() => {
    if (!localFeature.value?.taskLists) return 0;
    return localFeature.value.taskLists.reduce((sum, list) => {
        return sum + (list.tasks?.reduce((taskSum, t) => taskSum + (t.estimatedHours || 0), 0) || 0);
    }, 0);
});

// Methods
const formatDuration = (hours) => {
    if (!hours || hours === 0) return '0h';
    const h = Math.floor(hours);
    const m = Math.round((hours - h) * 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
};

const getPriorityColor = (priority) => {
    const colors = { high: 'error', medium: 'warning', low: 'success' };
    return colors[priority] || 'grey';
};

const handleStartEditTitle = () => {
    editedTitle.value = localFeature.value?.title || '';
    isEditingTitle.value = true;
};

const handleSaveTitle = () => {
    if (!editedTitle.value.trim()) return;
    emit('update-feature', { ...localFeature.value, title: editedTitle.value.trim() });
    isEditingTitle.value = false;
};

const handleCancelEditTitle = () => {
    isEditingTitle.value = false;
};

const handleStartEditDescription = () => {
    editedDescription.value = localFeature.value?.description || '';
    isEditingDescription.value = true;
};

const handleSaveDescription = () => {
    emit('update-feature', { ...localFeature.value, description: editedDescription.value });
    isEditingDescription.value = false;
};

const handleCancelEditDescription = () => {
    isEditingDescription.value = false;
};

const handleUpdatePriority = (priority) => {
    emit('update-feature', { ...localFeature.value, priority });
};

const handleUpdateDueDate = (date) => {
    emit('update-feature', { ...localFeature.value, dueDate: date });
};

const handleUpdateLabels = (labels) => {
    emit('update-feature', { ...localFeature.value, labels });
};

const handleUpdateAssignees = (assignees) => {
    emit('update-feature', { ...localFeature.value, assignees });
};

const handleClose = () => {
    emit('update:modelValue', false);
};

// Task List Handlers
const handleAddTaskList = (name) => {
    emit('add-task-list', { featureId: localFeature.value.id, name });
};

const handleRenameTaskList = ({ listId, name }) => {
    emit('rename-task-list', { featureId: localFeature.value.id, listId, name });
};

const handleDeleteTaskList = (list) => {
    emit('delete-task-list', { featureId: localFeature.value.id, listId: list.id });
};

// Task Handlers
const handleAddTask = ({ listId, title }) => {
    emit('add-task', { featureId: localFeature.value.id, listId, title });
};

const handleEditTask = (task) => {
    emit('edit-task', { featureId: localFeature.value.id, task });
};

const handleDeleteTask = (task) => {
    emit('delete-task', { featureId: localFeature.value.id, task });
};

const handleToggleTaskComplete = (task) => {
    emit('toggle-task-complete', { featureId: localFeature.value.id, task });
};

const handleMoveTask = (payload) => {
    emit('move-task', { featureId: localFeature.value.id, ...payload });
};

// Timer Handlers
const handleStartTimer = (task) => emit('start-timer', task);
const handlePauseTimer = (task) => emit('pause-timer', task);
const handleStopTimer = (task) => emit('stop-timer', task);
const handleDiscardTimer = (task) => emit('discard-timer', task);
const handleAddTime = (task) => emit('add-time', task);
</script>

<template>
    <v-dialog 
        :model-value="modelValue" 
        @update:model-value="$emit('update:modelValue', $event)" 
        max-width="1200"
        fullscreen
    >
        <v-card v-if="localFeature" color="background" class="feature-modal">
            <!-- Header -->
            <v-toolbar color="surface" density="compact" flat>
                <template v-if="isEditingTitle">
                    <v-text-field
                        v-model="editedTitle"
                        variant="outlined"
                        density="compact"
                        hide-details
                        autofocus
                        class="mx-2 mx-sm-4 flex-grow-1"
                        :style="{ maxWidth: smAndDown ? '100%' : '500px' }"
                        @keydown.enter="handleSaveTitle"
                        @keydown.esc="handleCancelEditTitle"
                    />
                    <v-btn icon size="small" color="success" @click="handleSaveTitle">
                        <v-icon>mdi-check</v-icon>
                    </v-btn>
                    <v-btn icon size="small" @click="handleCancelEditTitle">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </template>
                <template v-else>
                    <v-toolbar-title class="d-flex align-center">
                        <v-icon v-if="!smAndDown" start>mdi-view-dashboard-variant</v-icon>
                        <span :class="{ 'text-truncate': smAndDown }" :style="{ maxWidth: smAndDown ? '180px' : 'auto' }">
                            {{ localFeature.title }}
                        </span>
                        <v-btn icon size="x-small" variant="text" class="ml-1 ml-sm-2" @click="handleStartEditTitle">
                            <v-icon size="16">mdi-pencil</v-icon>
                        </v-btn>
                    </v-toolbar-title>
                </template>
                <v-spacer />

                <!-- Labels (hide on mobile) -->
                <div v-if="!smAndDown" class="d-flex align-center gap-1 mr-4">
                    <v-chip
                        v-for="label in localFeature.labels"
                        :key="label.id"
                        :style="{ backgroundColor: label.color }"
                        size="small"
                        text-color="white"
                    >
                        {{ label.name }}
                    </v-chip>
                </div>

                <!-- Priority -->
                <v-menu>
                    <template #activator="{ props: menuProps }">
                        <v-chip 
                            v-bind="menuProps" 
                            :color="getPriorityColor(localFeature.priority)"
                            :size="smAndDown ? 'x-small' : 'small'"
                            class="mr-1 mr-sm-2"
                        >
                            {{ smAndDown ? '' : (localFeature.priority || 'No Priority') }}
                            <v-icon v-if="smAndDown" size="14">mdi-flag</v-icon>
                        </v-chip>
                    </template>
                    <v-list density="compact" nav>
                        <v-list-item 
                            v-for="p in ['high', 'medium', 'low']" 
                            :key="p"
                            @click="handleUpdatePriority(p)"
                        >
                            <template #prepend>
                                <v-icon :color="getPriorityColor(p)" size="18">mdi-circle</v-icon>
                            </template>
                            <v-list-item-title class="text-capitalize">{{ p }}</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>

                <v-btn icon variant="text" @click="handleClose">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-toolbar>

            <v-divider />

            <!-- Stats Bar -->
            <v-sheet color="surface" class="d-flex align-center flex-wrap gap-3 gap-sm-6 px-3 px-sm-4 py-2 py-sm-3">
                <div class="d-flex align-center">
                    <v-icon :size="smAndDown ? 16 : 18" color="primary" class="mr-1 mr-sm-2">mdi-checkbox-marked-circle-outline</v-icon>
                    <span :class="smAndDown ? 'text-caption' : 'text-body-2'">{{ completedTasks }}/{{ totalTasks }} tasks</span>
                </div>
                <v-progress-linear 
                    v-if="!mobile"
                    :model-value="progressPercentage" 
                    color="primary" 
                    height="6"
                    rounded
                    style="max-width: 150px; min-width: 80px;"
                />
                <div class="d-flex align-center">
                    <v-icon :size="smAndDown ? 16 : 18" color="success" class="mr-1 mr-sm-2">mdi-clock-check-outline</v-icon>
                    <span :class="smAndDown ? 'text-caption' : 'text-body-2'">{{ formatDuration(totalLoggedHours) }} logged</span>
                </div>
                <div v-if="!smAndDown" class="d-flex align-center">
                    <v-icon size="18" color="warning" class="mr-2">mdi-clock-outline</v-icon>
                    <span class="text-body-2">{{ formatDuration(totalEstimatedHours) }} estimated</span>
                </div>
                <v-spacer v-if="!smAndDown" />
                <div v-if="!mobile" class="d-flex align-center">
                    <span class="text-body-2 text-medium-emphasis mr-2">Assignees:</span>
                    <v-avatar 
                        v-for="assignee in localFeature.assignees?.slice(0, 3)" 
                        :key="assignee.id" 
                        size="24"
                        :color="assignee.avatarColor || 'primary'"
                        class="mr-n1"
                    >
                        <span style="font-size: 10px;">{{ assignee.name?.charAt(0).toUpperCase() }}</span>
                    </v-avatar>
                    <v-chip 
                        v-if="(localFeature.assignees?.length || 0) > 3" 
                        size="x-small" 
                        class="ml-2"
                    >
                        +{{ localFeature.assignees.length - 3 }}
                    </v-chip>
                </div>
            </v-sheet>

            <v-divider />

            <!-- Tabs -->
            <v-tabs v-model="activeTab" color="primary" bg-color="surface">
                <v-tab value="tasks">
                    <v-icon start>mdi-format-list-checks</v-icon>
                    Tasks
                </v-tab>
                <v-tab value="details">
                    <v-icon start>mdi-information-outline</v-icon>
                    Details
                </v-tab>
            </v-tabs>

            <v-divider />

            <!-- Tab Content -->
            <v-window v-model="activeTab" class="flex-grow-1 overflow-auto">
                <!-- Tasks Tab -->
                <v-window-item value="tasks" class="fill-height">
                    <TaskKanban
                        :task-lists="localFeature.taskLists || []"
                        :team-members="teamMembers"
                        :active-timers="activeTimers"
                        :timer-displays="timerDisplays"
                        @add-list="handleAddTaskList"
                        @rename-list="handleRenameTaskList"
                        @delete-list="handleDeleteTaskList"
                        @add-task="handleAddTask"
                        @edit-task="handleEditTask"
                        @delete-task="handleDeleteTask"
                        @toggle-complete="handleToggleTaskComplete"
                        @move-task="handleMoveTask"
                        @start-timer="handleStartTimer"
                        @pause-timer="handlePauseTimer"
                        @stop-timer="handleStopTimer"
                        @discard-timer="handleDiscardTimer"
                        @add-time="handleAddTime"
                    />
                </v-window-item>

                <!-- Details Tab -->
                <v-window-item value="details" class="pa-6">
                    <v-row>
                        <v-col cols="12" md="8">
                            <!-- Description -->
                            <div class="mb-6">
                                <div class="d-flex align-center mb-2">
                                    <v-icon start size="20">mdi-text</v-icon>
                                    <span class="text-subtitle-1 font-weight-medium">Description</span>
                                    <v-btn 
                                        v-if="!isEditingDescription"
                                        icon 
                                        size="x-small" 
                                        variant="text" 
                                        class="ml-2"
                                        @click="handleStartEditDescription"
                                    >
                                        <v-icon size="16">mdi-pencil</v-icon>
                                    </v-btn>
                                </div>
                                <template v-if="isEditingDescription">
                                    <v-textarea
                                        v-model="editedDescription"
                                        variant="outlined"
                                        bg-color="surface-variant"
                                        rows="4"
                                        placeholder="Add a description..."
                                        autofocus
                                    />
                                    <div class="d-flex gap-2 mt-2">
                                        <v-btn size="small" color="primary" @click="handleSaveDescription">Save</v-btn>
                                        <v-btn size="small" variant="text" @click="handleCancelEditDescription">Cancel</v-btn>
                                    </div>
                                </template>
                                <template v-else>
                                    <p class="text-body-2" v-if="localFeature.description">
                                        {{ localFeature.description }}
                                    </p>
                                    <p class="text-body-2 text-medium-emphasis" v-else>
                                        No description added
                                    </p>
                                </template>
                            </div>
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-card color="surface" variant="outlined" class="pa-4">
                                <!-- Due Date -->
                                <div class="mb-4">
                                    <div class="text-caption text-medium-emphasis mb-1">Due Date</div>
                                    <v-text-field
                                        :model-value="localFeature.dueDate"
                                        type="date"
                                        variant="outlined"
                                        density="compact"
                                        hide-details
                                        bg-color="surface-variant"
                                        @update:model-value="handleUpdateDueDate"
                                    />
                                </div>

                                <!-- Labels -->
                                <div class="mb-4">
                                    <div class="text-caption text-medium-emphasis mb-1">Labels</div>
                                    <v-select
                                        :model-value="localFeature.labels"
                                        :items="availableLabels"
                                        item-title="name"
                                        item-value="id"
                                        return-object
                                        multiple
                                        chips
                                        closable-chips
                                        variant="outlined"
                                        density="compact"
                                        hide-details
                                        bg-color="surface-variant"
                                        @update:model-value="handleUpdateLabels"
                                    >
                                        <template #chip="{ item, props: chipProps }">
                                            <v-chip 
                                                v-bind="chipProps" 
                                                :style="{ backgroundColor: item.raw.color }"
                                                text-color="white"
                                                size="small"
                                            >
                                                {{ item.raw.name }}
                                            </v-chip>
                                        </template>
                                    </v-select>
                                </div>

                                <!-- Assignees -->
                                <div>
                                    <div class="text-caption text-medium-emphasis mb-1">Assignees</div>
                                    <v-select
                                        :model-value="localFeature.assignees"
                                        :items="teamMembers"
                                        item-title="name"
                                        item-value="id"
                                        return-object
                                        multiple
                                        chips
                                        closable-chips
                                        variant="outlined"
                                        density="compact"
                                        hide-details
                                        bg-color="surface-variant"
                                        @update:model-value="handleUpdateAssignees"
                                    >
                                        <template #chip="{ item, props: chipProps }">
                                            <v-chip v-bind="chipProps" size="small">
                                                <v-avatar 
                                                    start 
                                                    size="20" 
                                                    :color="item.raw.avatarColor || 'primary'"
                                                >
                                                    <span style="font-size: 10px;">
                                                        {{ item.raw.name?.charAt(0).toUpperCase() }}
                                                    </span>
                                                </v-avatar>
                                                {{ item.raw.name }}
                                            </v-chip>
                                        </template>
                                    </v-select>
                                </div>
                            </v-card>

                            <!-- Delete Button -->
                            <v-btn
                                color="error"
                                variant="outlined"
                                block
                                class="mt-4"
                                prepend-icon="mdi-delete"
                                @click="$emit('delete-feature', localFeature)"
                            >
                                Delete Feature
                            </v-btn>
                        </v-col>
                    </v-row>
                </v-window-item>
            </v-window>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.feature-modal {
    display: flex;
    flex-direction: column;
    height: 100%;
}
</style>
