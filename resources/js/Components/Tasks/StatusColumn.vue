<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import TaskCard from './TaskCard.vue';
import ColorPicker from '@/Components/ColorPicker.vue';
import { useSnackbar } from '@/composables/useSnackbar';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { normalizeHexColor } from '@/utils/color';

const { showSnackbar } = useSnackbar();
const { confirm: confirmDialog } = useConfirmDialog();

const props = defineProps({
    status: {
        type: Object,
        required: true,
    },
    tasks: {
        type: Array,
        default: () => [],
    },
    workspace: {
        type: Object,
        required: true,
    },
    space: {
        type: Object,
        required: true,
    },
    list: {
        type: Object,
        required: true,
    },
    parentTask: {
        type: Object,
        default: null,
    },
    statuses: {
        type: Array,
        default: () => [],
    },
    canAddTask: {
        type: Boolean,
        default: true,
    },
    canManageSpace: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['task-moved', 'task-complete', 'task-open', 'add-task', 'task-open-subtask', 'task-subtask-toggle']);

// Drag state
const isDragging = ref(false);

// Add task form
const showAddTask = ref(false);
const newTaskName = ref('');

// Edit status dialog
const showEditStatus = ref(false);
const editStatusForm = ref({
    name: '',
    color: '',
    is_closed: false,
    applies_to: 'both',
});

// Delete status dialog
const showDeleteStatus = ref(false);
const moveToStatusId = ref(null);

// Task count
const taskCount = computed(() => props.tasks.length);

// Available statuses for moving tasks (exclude current status)
const availableStatuses = computed(() => {
    return props.statuses.filter(s => s.id !== props.status.id);
});

// Handle drag change
const onDragChange = (evt) => {
    if (evt.added) {
        emit('task-moved', {
            task: evt.added.element,
            statusId: props.status.id,
            newIndex: evt.added.newIndex,
            changeType: 'added',
        });
    } else if (evt.moved) {
        emit('task-moved', {
            task: evt.moved.element,
            statusId: props.status.id,
            newIndex: evt.moved.newIndex,
            changeType: 'moved',
        });
    }
};

// Add new task
const addTask = () => {
    if (!newTaskName.value.trim()) return;

    emit('add-task', {
        name: newTaskName.value.trim(),
        status_id: props.status.id,
    });

    newTaskName.value = '';
    showAddTask.value = false;
};

// Cancel add task
const cancelAddTask = () => {
    newTaskName.value = '';
    showAddTask.value = false;
};

// Open edit status dialog
const openEditStatus = () => {
    editStatusForm.value = {
        name: props.status.name,
        color: props.status.color,
        is_closed: props.status.is_closed || false,
        applies_to: props.parentTask ? 'subtasks' : 'tasks',
    };
    showEditStatus.value = true;
};

// Save status
const saveStatus = () => {
    const payload = {
        ...editStatusForm.value,
        color: normalizeHexColor(editStatusForm.value.color),
    };

    router.patch(
        route('spaces.statuses.update', [props.workspace.id, props.space.id, props.status.id]),
        payload,
        {
            preserveScroll: true,
            onSuccess: () => {
                showEditStatus.value = false;
                showSnackbar('Status updated successfully!', 'success');
            },
            onError: () => showSnackbar('Failed to update status', 'error'),
        }
    );
};

// Open delete status dialog
const openDeleteStatus = () => {
    moveToStatusId.value = availableStatuses.value[0]?.id || null;
    showDeleteStatus.value = true;
};

// Delete status
const deleteStatus = async () => {
    const ok = await confirmDialog(
        `Delete status "${props.status.name}"? All items will be moved to the selected status.`,
        'Delete Status'
    );
    if (!ok) return;

    router.delete(
        route('spaces.statuses.delete', [props.workspace.id, props.space.id, props.status.id]),
        {
            data: { move_to_status_id: moveToStatusId.value },
            preserveScroll: true,
            onSuccess: () => {
                showDeleteStatus.value = false;
                showSnackbar('Status deleted successfully!', 'success');
            },
            onError: () => showSnackbar('Failed to delete status', 'error'),
        }
    );
};

// Handle task complete
const handleTaskComplete = (task) => {
    emit('task-complete', task);
};

// Handle task open
const handleTaskOpen = (task) => {
    emit('task-open', task);
};

// Relay subtask open from card
const handleOpenSubtask = (task, subtask) => {
    emit('task-open-subtask', task, subtask);
};

// Relay subtask toggle from card
const handleSubtaskToggle = (task, subtask) => {
    emit('task-subtask-toggle', task, subtask);
};
</script>

<template>
    <div class="status-column">
        <!-- Column Header -->
        <div class="column-header">
            <div class="flex items-center gap-2">
                <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: status.color }" />
                <span class="font-medium text-sm">{{ status.name }}</span>
                <span class="text-xs text-gray-500 ml-1">{{ taskCount }}</span>
            </div>

            <div class="column-actions">
                <v-btn v-if="canAddTask" icon variant="text" size="x-small" @click="showAddTask = true">
                    <v-icon size="16">mdi-plus</v-icon>
                </v-btn>
                <v-menu v-if="canManageSpace">
                    <template v-slot:activator="{ props: menuProps }">
                        <v-btn v-bind="menuProps" icon variant="text" size="x-small">
                            <v-icon size="16">mdi-dots-horizontal</v-icon>
                        </v-btn>
                    </template>
                    <v-card color="surface">
                        <v-list density="compact">
                            <v-list-item prepend-icon="mdi-pencil-outline" title="Edit Status"
                                @click="openEditStatus" />
                            <v-divider v-if="availableStatuses.length > 0" />
                            <v-list-item v-if="availableStatuses.length > 0" prepend-icon="mdi-delete-outline"
                                title="Delete Status" class="text-error" @click="openDeleteStatus" />
                        </v-list>
                    </v-card>
                </v-menu>
            </div>
        </div>

        <!-- Tasks Container -->
        <div class="column-content">
            <!-- Draggable Tasks -->
            <draggable :list="tasks" group="tasks" item-key="id" :animation="200" ghost-class="task-ghost"
                drag-class="task-dragging" class="tasks-list" :class="{ 'tasks-list--dragging': isDragging }"
                @change="onDragChange" @start="isDragging = true" @end="isDragging = false">
                <template #item="{ element }">
                    <div class="task-wrapper">
                        <TaskCard :task="element" @complete="handleTaskComplete" @open-detail="handleTaskOpen"
                            @open-subtask="(subtask) => handleOpenSubtask(element, subtask)"
                            @toggle-subtask="(subtask) => handleSubtaskToggle(element, subtask)" />
                    </div>
                </template>
            </draggable>
        </div>

        <!-- Bottom Footer (Jira/ClickUp style) -->
        <div class="column-footer">
            <!-- Add Task Form -->
            <div v-if="canAddTask && showAddTask" class="add-task-form">
                <v-card variant="outlined" rounded="lg">
                    <v-card-text class="pa-3">
                        <v-text-field v-model="newTaskName" placeholder="Task name" variant="plain" density="compact"
                            hide-details autofocus @keydown.enter="addTask" @keydown.escape="cancelAddTask" />
                        <div class="flex items-center gap-2 mt-2">
                            <v-btn color="primary" size="small" variant="flat" @click="addTask">
                                Save
                            </v-btn>
                            <v-btn size="small" variant="text" @click="cancelAddTask">
                                Cancel
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>
            </div>

            <!-- Add Task Button (when form is hidden) -->
            <v-btn v-if="canAddTask && !showAddTask" variant="text" block class="add-task-btn" @click="showAddTask = true">
                <v-icon start size="16">mdi-plus</v-icon>
                {{ parentTask ? 'Add Subtask' : 'Add Task' }}
            </v-btn>
        </div>

        <!-- Edit Status Dialog -->
        <v-dialog v-model="showEditStatus" max-width="500">
            <v-card>
                <v-card-title class="d-flex justify-space-between align-center">
                    <span>Edit Status</span>
                    <v-btn icon="mdi-close" variant="text" size="small" @click="showEditStatus = false" />
                </v-card-title>
                <v-divider />
                <v-card-text class="pa-4">
                    <v-text-field v-model="editStatusForm.name" label="Status Name" variant="outlined"
                        density="comfortable" class="mb-3" />

                    <ColorPicker v-model="editStatusForm.color" />
                </v-card-text>
                <v-divider />
                <v-card-actions class="pa-4">
                    <v-spacer />
                    <v-btn variant="text" @click="showEditStatus = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat" @click="saveStatus">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Status Dialog -->
        <v-dialog v-model="showDeleteStatus" max-width="500">
            <v-card>
                <v-card-title class="d-flex justify-space-between align-center">
                    <span>Delete Status</span>
                    <v-btn icon="mdi-close" variant="text" size="small" @click="showDeleteStatus = false" />
                </v-card-title>
                <v-divider />
                <v-card-text class="pa-4">
                    <p class="mb-4">
                        Are you sure you want to delete the status "{{ status.name }}"?
                        <strong>{{ taskCount }}</strong> item(s) will be moved to the selected status.
                    </p>

                    <v-select v-model="moveToStatusId" label="Move items to" :items="availableStatuses"
                        item-title="name" item-value="id" variant="outlined" density="comfortable" />
                </v-card-text>
                <v-divider />
                <v-card-actions class="pa-4">
                    <v-spacer />
                    <v-btn variant="text" @click="showDeleteStatus = false">Cancel</v-btn>
                    <v-btn color="error" variant="flat" @click="deleteStatus">Delete</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<style scoped>
.status-column {
    width: 300px;
    min-width: 300px;
    display: flex;
    flex-direction: column;
    background-color: #1a1a1a;
    border-radius: 8px;
    max-height: calc(100vh - 180px);
}

.column-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    border-bottom: 1px solid #2d2d30;
}

.column-actions {
    display: flex;
    gap: 2px;
    opacity: 0;
    transition: opacity 0.15s;
}

.status-column:hover .column-actions {
    opacity: 1;
}

.column-content {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}

.tasks-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 100%;
    border-radius: 8px;
    padding: 2px;
}

.tasks-list::after {
    content: '';
    display: block;
    min-height: 16px;
}

.tasks-list--dragging {
    background: rgba(255, 255, 255, 0.03);
    outline: 1px dashed rgba(255, 255, 255, 0.16);
}

.tasks-list--dragging::after {
    min-height: 72px;
}

.column-footer {
    padding: 8px;
    border-top: 1px solid #2d2d30;
    background-color: #1a1a1a;
}

.task-wrapper {
    cursor: grab;
}

.task-wrapper:active {
    cursor: grabbing;
}

.task-ghost {
    opacity: 0.5;
}

.task-dragging {
    transform: rotate(2deg);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
}

.add-task-btn {
    justify-content: flex-start;
    opacity: 0.6;
}

.add-task-btn:hover {
    opacity: 1;
}

.add-task-form :deep(.v-field) {
    background-color: transparent;
}

.add-task-form :deep(.v-field--focused .v-field__outline) {
    --v-field-border-opacity: 0;
    box-shadow: none;
}

.add-task-form :deep(.v-card) {
    border-color: #2d2d30 !important;
}

.add-task-form :deep(*:focus),
.add-task-form :deep(*:focus-visible) {
    outline: none !important;
    box-shadow: none !important;
}

.add-task-form :deep(.v-field__input) {
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    min-height: 32px;
}
</style>
