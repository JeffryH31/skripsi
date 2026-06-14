<script setup>
/**
 * TaskKanban Component
 * 
 * Kanban board for tasks within a feature
 */
import { ref, computed } from 'vue';
import { useDisplay } from 'vuetify';
import draggable from 'vuedraggable';
import TaskCard from './TaskCard.vue';

const { smAndDown, mobile } = useDisplay();

const props = defineProps({
    taskLists: {
        type: Array,
        default: () => []
    },
    teamMembers: {
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
    'update:taskLists',
    'add-list',
    'rename-list',
    'delete-list',
    'add-task',
    'edit-task',
    'delete-task',
    'toggle-complete',
    'move-task',
    'start-timer',
    'pause-timer',
    'stop-timer',
    'discard-timer',
    'add-time'
]);

const localTaskLists = computed({
    get: () => props.taskLists,
    set: (value) => emit('update:taskLists', value)
});

const showAddListForm = ref(false);
const newListName = ref('');
const addingTaskToList = ref(null);
const newTaskTitle = ref('');
const editingListId = ref(null);
const editListName = ref('');

const handleAddList = () => {
    if (!newListName.value.trim()) return;
    emit('add-list', newListName.value.trim());
    newListName.value = '';
    showAddListForm.value = false;
};

const handleStartRenameList = (list) => {
    editingListId.value = list.id;
    editListName.value = list.name;
};

const handleSaveListRename = (list) => {
    if (!editListName.value.trim()) return;
    emit('rename-list', { listId: list.id, name: editListName.value.trim() });
    editingListId.value = null;
    editListName.value = '';
};

const handleCancelRenameList = () => {
    editingListId.value = null;
    editListName.value = '';
};

const handleDeleteList = (list) => {
    emit('delete-list', list);
};

const handleStartAddTask = (listId) => {
    addingTaskToList.value = listId;
    newTaskTitle.value = '';
};

const handleAddTask = (listId) => {
    if (!newTaskTitle.value.trim()) return;
    emit('add-task', { listId, title: newTaskTitle.value.trim() });
    newTaskTitle.value = '';
    addingTaskToList.value = null;
};

const handleCancelAddTask = () => {
    addingTaskToList.value = null;
    newTaskTitle.value = '';
};

const onTaskDragEnd = (evt, list) => {
    if (evt.added) {
        emit('move-task', {
            task: evt.added.element,
            toListId: list.id,
            newIndex: evt.added.newIndex
        });
    }
};

const isTimerActive = (taskId) => {
    return !!props.activeTimers[taskId];
};

const getTimerDisplay = (taskId) => {
    return props.timerDisplays[taskId] || '00:00:00';
};
</script>

<template>
    <div class="task-kanban d-flex gap-4 overflow-x-auto pa-2 pa-sm-4">
        <!-- Task Lists -->
        <div 
            v-for="list in localTaskLists" 
            :key="list.id" 
            class="task-list-column"
        >
            <v-card color="surface" rounded="lg" elevation="0" class="task-list-card">
                <!-- List Header -->
                <div class="d-flex align-center pa-3 border-b">
                    <template v-if="editingListId === list.id">
                        <v-text-field
                            v-model="editListName"
                            variant="outlined"
                            density="compact"
                            hide-details
                            autofocus
                            class="flex-grow-1"
                            @keydown.enter="handleSaveListRename(list)"
                            @keydown.esc="handleCancelRenameList"
                        />
                        <v-btn icon size="x-small" color="success" class="ml-1" @click="handleSaveListRename(list)">
                            <v-icon size="16">mdi-check</v-icon>
                        </v-btn>
                        <v-btn icon size="x-small" class="ml-1" @click="handleCancelRenameList">
                            <v-icon size="16">mdi-close</v-icon>
                        </v-btn>
                    </template>
                    <template v-else>
                        <span class="text-subtitle-2 font-weight-medium">{{ list.name }}</span>
                        <v-chip size="x-small" class="ml-2">{{ list.tasks?.length || 0 }}</v-chip>
                        <v-spacer />
                        <v-menu location="bottom end">
                            <template #activator="{ props: menuProps }">
                                <v-btn icon variant="text" size="x-small" v-bind="menuProps">
                                    <v-icon size="16">mdi-dots-horizontal</v-icon>
                                </v-btn>
                            </template>
                            <v-list density="compact" nav>
                                <v-list-item @click="handleStartRenameList(list)">
                                    <template #prepend><v-icon size="18">mdi-pencil</v-icon></template>
                                    <v-list-item-title>Rename</v-list-item-title>
                                </v-list-item>
                                <v-list-item class="text-error" @click="handleDeleteList(list)">
                                    <template #prepend><v-icon size="18" color="error">mdi-delete</v-icon></template>
                                    <v-list-item-title>Delete</v-list-item-title>
                                </v-list-item>
                            </v-list>
                        </v-menu>
                    </template>
                </div>

                <!-- Tasks Container -->
                <div class="tasks-container pa-2">
                    <draggable
                        :list="list.tasks"
                        group="tasks"
                        item-key="id"
                        :animation="200"
                        ghost-class="ghost-task"
                        @change="(evt) => onTaskDragEnd(evt, list)"
                    >
                        <template #item="{ element: task }">
                            <TaskCard
                                :task="task"
                                :team-members="teamMembers"
                                :is-timer-active="isTimerActive(task.id)"
                                :timer-display="getTimerDisplay(task.id)"
                                @edit="$emit('edit-task', $event)"
                                @delete="$emit('delete-task', $event)"
                                @toggle-complete="$emit('toggle-complete', $event)"
                                @start-timer="$emit('start-timer', $event)"
                                @pause-timer="$emit('pause-timer', $event)"
                                @stop-timer="$emit('stop-timer', $event)"
                                @discard-timer="$emit('discard-timer', $event)"
                                @add-time="$emit('add-time', $event)"
                            />
                        </template>
                    </draggable>

                    <!-- Add Task Form -->
                    <div v-if="addingTaskToList === list.id" class="add-task-form mt-2">
                        <v-text-field
                            v-model="newTaskTitle"
                            placeholder="Task title..."
                            variant="outlined"
                            density="compact"
                            hide-details
                            autofocus
                            bg-color="surface-variant"
                            @keydown.enter="handleAddTask(list.id)"
                            @keydown.esc="handleCancelAddTask"
                        />
                        <div class="d-flex gap-2 mt-2">
                            <v-btn size="small" color="primary" @click="handleAddTask(list.id)">Add</v-btn>
                            <v-btn size="small" variant="text" @click="handleCancelAddTask">Cancel</v-btn>
                        </div>
                    </div>

                    <!-- Add Task Button -->
                    <v-btn
                        v-else
                        variant="text"
                        color="primary"
                        size="small"
                        block
                        class="mt-2"
                        prepend-icon="mdi-plus"
                        @click="handleStartAddTask(list.id)"
                    >
                        Add Task
                    </v-btn>
                </div>
            </v-card>
        </div>

        <!-- Add List Column -->
        <div class="add-list-column">
            <v-card 
                v-if="showAddListForm" 
                color="surface" 
                rounded="lg" 
                class="pa-3" 
                min-width="280"
            >
                <v-text-field
                    v-model="newListName"
                    placeholder="List name..."
                    variant="outlined"
                    density="compact"
                    hide-details
                    autofocus
                    bg-color="surface-variant"
                    @keydown.enter="handleAddList"
                    @keydown.esc="showAddListForm = false"
                />
                <div class="d-flex gap-2 mt-2">
                    <v-btn size="small" color="primary" @click="handleAddList">Add List</v-btn>
                    <v-btn size="small" variant="text" @click="showAddListForm = false">Cancel</v-btn>
                </div>
            </v-card>
            <v-btn
                v-else
                variant="tonal"
                color="primary"
                prepend-icon="mdi-plus"
                min-width="280"
                @click="showAddListForm = true"
            >
                Add List
            </v-btn>
        </div>
    </div>
</template>

<style scoped>
.task-kanban {
    min-height: 400px;
    -webkit-overflow-scrolling: touch;
}

.task-list-column {
    flex: 0 0 300px;
}

@media (max-width: 600px) {
    .task-list-column {
        flex: 0 0 280px;
    }
}

.task-list-card {
    max-height: 500px;
    display: flex;
    flex-direction: column;
}

@media (max-width: 600px) {
    .task-list-card {
        max-height: 400px;
    }
}

.tasks-container {
    flex: 1;
    overflow-y: auto;
    min-height: 100px;
    -webkit-overflow-scrolling: touch;
}

.border-b {
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.ghost-task {
    opacity: 0.5;
    background: rgba(var(--v-theme-primary), 0.1);
}

.add-list-column {
    flex: 0 0 auto;
}

@media (max-width: 600px) {
    .add-list-column .v-btn {
        min-width: 200px;
    }
}
</style>
