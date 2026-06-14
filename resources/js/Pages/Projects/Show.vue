<script setup>
import { ref, computed, provide, watch, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import StatusColumn from '@/Components/Tasks/StatusColumn.vue';
import ColorPicker from '@/Components/ColorPicker.vue';
import TaskDetailPanel from '@/Components/Tasks/TaskDetailPanel.vue';
import GanttChart from '@/Components/Cpm/GanttChart.vue';
import CpmSummary from '@/Components/Cpm/CpmSummary.vue';
import CalendarView from '@/Components/Lists/CalendarView.vue';
import SprintView from '@/Components/Lists/SprintView.vue';
import DeleteConfirmDialog from '@/Components/DeleteConfirmDialog.vue';
import { useSnackbar } from '@/composables/useSnackbar';
import { useCpm } from '@/composables/useCpm';
import { PRIORITIES, getPriority } from '@/constants/priorities';
import { normalizeHexColor } from '@/utils/color';
import { getStoredSubtaskCompletionTarget } from '@/utils/subtaskCompletionAutomation';

const props = defineProps({
    workspace: { type: Object, required: true },
    space: { type: Object, required: true },
    list: { type: Object, required: true },
    tasksByStatus: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    sprints: { type: Array, default: () => [] },
    parentTask: { type: Object, default: null },
    canManageProject: { type: Boolean, default: false },
    canDeleteProject: { type: Boolean, default: false },
    canManageTaskStructure: { type: Boolean, default: false },
    canOperateTasks: { type: Boolean, default: false },
    canManageSpace: { type: Boolean, default: false },
});

const page = usePage();
const { showSnackbar } = useSnackbar();

// Local task state mirror (for optimistic DnD)
const initializeTasksByStatus = () => {
    const result = {};
    if (props.statuses) {
        props.statuses.forEach((status) => {
            result[status.id] = Array.isArray(props.tasksByStatus?.[status.id])
                ? [...props.tasksByStatus[status.id]]
                : [];
        });
    }
    return result;
};

const localTasksByStatus = ref(initializeTasksByStatus());

watch(
    () => props.tasksByStatus,
    (newValue) => {
        localTasksByStatus.value = initializeTasksByStatus();
        if (selectedTask.value) {
            const taskId = selectedTask.value.id;
            for (const statusId in newValue) {
                const updated = newValue[statusId].find((t) => t.id === taskId);
                if (updated) {
                    selectedTask.value = { ...updated };
                    break;
                }
            }
        }
    },
    { deep: true }
);

// Detail panel state
const selectedTask = ref(null);
const showTaskDetail = ref(false);
const panelParentTask = ref(null);

// View mode
const allowedViewModes = ['board', 'list', 'calendar', 'sprint', 'gantt'];

const getUrlQueryParam = (key) => {
    const queryString = page.url?.split('?')[1] || '';
    if (!queryString) return null;
    return new URLSearchParams(queryString).get(key);
};

const getRequestedSprintIdFromUrl = () => {
    const raw = Number(getUrlQueryParam('sprint_id') || 0);
    return raw > 0 ? raw : null;
};

const resolveViewModeFromUrl = () => {
    const requested = getUrlQueryParam('view');
    if (!requested || !allowedViewModes.includes(requested)) return 'board';
    if (requested === 'gantt' && !props.parentTask) return 'board';
    return requested;
};

const viewMode = ref(resolveViewModeFromUrl());

// Sprint state shared with SprintView
const selectedSprintId = ref(null);

// Filters
const filterPriority = ref([]);
const filterAssignee = ref([]);
const filterLabel = ref([]);
const filterSprint = ref(null);
const searchQuery = ref('');

const members = computed(() => props.workspace?.members || []);
const labels = computed(() => props.workspace?.labels || []);

const activeFilterCount = computed(() => {
    let count = 0;
    if (filterPriority.value.length) count++;
    if (filterAssignee.value.length) count++;
    if (filterLabel.value.length) count++;
    if (filterSprint.value != null) count++;
    return count;
});

const filteredTasksByStatus = computed(() => {
    const result = {};
    const q = searchQuery.value?.toLowerCase() || '';

    for (const statusId in localTasksByStatus.value) {
        let tasks = localTasksByStatus.value[statusId] || [];

        if (q) tasks = tasks.filter((t) => t.name?.toLowerCase().includes(q));
        if (filterPriority.value.length) {
            tasks = tasks.filter((t) => filterPriority.value.includes(t.priority_level));
        }
        if (filterAssignee.value.length) {
            tasks = tasks.filter((t) =>
                t.assignees?.some((a) => filterAssignee.value.includes(a.id))
            );
        }
        if (filterLabel.value.length) {
            tasks = tasks.filter((t) =>
                t.labels?.some((l) => filterLabel.value.includes(l.id))
            );
        }
        if (filterSprint.value != null && props.parentTask) {
            const sid = Number(filterSprint.value);
            tasks = tasks.filter((t) => Number(t.sprint_id) === sid);
        }
        result[statusId] = tasks;
    }
    return result;
});

const allFilteredItems = computed(() =>
    Object.values(filteredTasksByStatus.value).flat()
);

const siblingSubtasks = computed(() => {
    if (!props.parentTask) return [];
    const seen = new Set();
    return Object.values(localTasksByStatus.value)
        .flatMap((items) => (Array.isArray(items) ? items : []))
        .filter((item) => {
            const id = Number(item?.id || 0);
            if (!id || seen.has(id)) return false;
            seen.add(id);
            return true;
        });
});

// Reorder / move helpers
const getBoardOrderedIds = () =>
    (props.statuses || []).flatMap((status) =>
        (localTasksByStatus.value[status.id] || []).map((item) => item.id)
    );

const persistBoardOrder = () => {
    const onError = (msg) => {
        router.reload({ only: ['tasksByStatus'] });
        showSnackbar(msg, 'error');
    };

    if (props.parentTask) {
        router.post(
            route('tasks.subtasks.reorder', [
                props.workspace.id,
                props.space.id,
                props.list.id,
                props.parentTask.id,
            ]),
            { subtask_ids: getBoardOrderedIds() },
            {
                preserveScroll: true,
                onSuccess: () => router.reload({ only: ['tasksByStatus'] }),
                onError: () => onError('Failed to reorder subtasks'),
            }
        );
        return;
    }

    router.post(
        route('tasks.reorder', [props.workspace.id, props.space.id, props.list.id]),
        { order: getBoardOrderedIds() },
        {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['tasksByStatus'] }),
            onError: () => onError('Failed to reorder tasks'),
        }
    );
};

const handleTaskMoved = ({ task, statusId, changeType }) => {
    if (changeType === 'moved') {
        persistBoardOrder();
        return;
    }

    const onError = (msg) => {
        router.reload({ only: ['tasksByStatus'] });
        showSnackbar(msg, 'error');
    };

    if (props.parentTask) {
        router.patch(
            route('tasks.subtasks.update', [
                props.workspace.id,
                props.space.id,
                props.list.id,
                props.parentTask.id,
                task.id,
            ]),
            { status_id: statusId },
            {
                preserveScroll: true,
                onSuccess: () => persistBoardOrder(),
                onError: () => onError('Failed to move subtask'),
            }
        );
        return;
    }

    router.patch(
        route('tasks.change-status', [
            props.workspace.id,
            props.space.id,
            props.list.id,
            task.id,
        ]),
        { status_id: statusId },
        {
            preserveScroll: true,
            onSuccess: () => persistBoardOrder(),
            onError: () => onError('Failed to move task'),
        }
    );
};

const handleTaskComplete = (task) => {
    if (!props.parentTask) return;

    const wasCompleted = !!task.completed_at;
    const routeName = wasCompleted ? 'tasks.subtasks.reopen' : 'tasks.subtasks.complete';
    const targetStatusId = getStoredSubtaskCompletionTarget(props.space?.id, props.statuses);
    const payload = !wasCompleted && targetStatusId ? { target_status_id: targetStatusId } : {};

    router.post(
        route(routeName, [
            props.workspace.id,
            props.space.id,
            props.list.id,
            props.parentTask.id,
            task.id,
        ]),
        payload,
        {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['tasksByStatus'] }),
            onError: (errors) => {
                if (errors.dependency) showSnackbar(errors.dependency, 'error');
                router.reload({ only: ['tasksByStatus'] });
            },
        }
    );
};

// Detail panel handlers
const handleTaskOpen = (task) => {
    selectedTask.value = task;
    panelParentTask.value = null;
    showTaskDetail.value = true;
};

const handleOpenSubtaskFromCard = (task, subtask) => {
    panelParentTask.value = props.parentTask ?? task;
    selectedTask.value = subtask;
    showTaskDetail.value = true;
};

const handleSubtaskToggleFromCard = (task, subtask) => {
    const wasCompleted = !!subtask.completed_at;
    const routeName = wasCompleted ? 'tasks.subtasks.reopen' : 'tasks.subtasks.complete';
    const targetStatusId = getStoredSubtaskCompletionTarget(props.space?.id, props.statuses);
    const payload = !wasCompleted && targetStatusId ? { target_status_id: targetStatusId } : {};
    const taskRouteId = props.parentTask ? props.parentTask.id : task.id;

    router.post(
        route(routeName, [
            props.workspace.id,
            props.space.id,
            props.list.id,
            taskRouteId,
            subtask.id,
        ]),
        payload,
        {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['tasksByStatus'] }),
            onError: (errors) => {
                if (errors.dependency) showSnackbar(errors.dependency, 'error');
                router.reload({ only: ['tasksByStatus'] });
            },
        }
    );
};

const findTaskInBoard = (id) => {
    if (!id) return null;
    for (const statusId in localTasksByStatus.value) {
        const found = (localTasksByStatus.value[statusId] || []).find((t) => t.id === id);
        if (found) return found;
    }
    return null;
};

// Track open-from-query inside setup (no module-level mutable state)
const openedFromQuery = ref(false);

const openDetailFromQuery = () => {
    if (openedFromQuery.value) return;
    const queryString = page.url?.split('?')[1] || '';
    if (!queryString) return;

    const params = new URLSearchParams(queryString);
    const openTaskId = Number(params.get('open_task_id') || 0);
    const openSubtaskId = Number(params.get('open_subtask_id') || 0);

    if (openSubtaskId > 0) {
        const subtask = findTaskInBoard(openSubtaskId);
        if (subtask) {
            handleTaskOpen(subtask);
            openedFromQuery.value = true;
            return;
        }
    }

    if (!props.parentTask && openTaskId > 0) {
        const task = findTaskInBoard(openTaskId);
        if (task) {
            handleTaskOpen(task);
            openedFromQuery.value = true;
        }
    }
};

watch(
    () => localTasksByStatus.value,
    () => openDetailFromQuery(),
    { deep: true }
);

const viewSubtasks = (task) => {
    router.visit(
        route('projects.show', [props.workspace.id, props.space.id, props.list.id]) +
            `?task_id=${task.id}`
    );
};

const openSubtaskInPanel = (subtask) => {
    panelParentTask.value = props.parentTask || selectedTask.value;
    selectedTask.value = subtask;
    showTaskDetail.value = true;
};

const refreshTasks = () => router.reload({ only: ['tasksByStatus'] });

// Add task
const isAddingTask = ref(false);

const handleAddTask = ({ name, status_id }) => {
    if (isAddingTask.value) return;
    isAddingTask.value = true;

    if (props.parentTask) {
        router.post(
            route('tasks.subtasks.store', [
                props.workspace.id,
                props.space.id,
                props.list.id,
                props.parentTask.id,
            ]),
            { name, status_id, task_id: props.parentTask.id },
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.visit(
                        route('projects.show', [
                            props.workspace.id,
                            props.space.id,
                            props.list.id,
                        ]) + `?task_id=${props.parentTask.id}`,
                        { preserveScroll: true }
                    );
                },
                onError: () => showSnackbar('Failed to add subtask', 'error'),
                onFinish: () => {
                    isAddingTask.value = false;
                },
            }
        );
        return;
    }

    router.post(
        route('tasks.store', [props.workspace.id, props.space.id, props.list.id]),
        { name, status_id },
        {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['tasksByStatus'] }),
            onError: () => showSnackbar('Failed to add task', 'error'),
            onFinish: () => {
                isAddingTask.value = false;
            },
        }
    );
};

// Status / list dialogs
const showAddStatus = ref(false);
const newStatusName = ref('');
const newStatusColor = ref('#6366F1');

const addStatus = () => {
    if (!newStatusName.value.trim()) return;

    router.post(
        route('spaces.statuses.add', [props.workspace.id, props.space.id]),
        {
            name: newStatusName.value.trim(),
            color: normalizeHexColor(newStatusColor.value),
        },
        { preserveScroll: true }
    );

    newStatusName.value = '';
    showAddStatus.value = false;
};

const showEditList = ref(false);
const editListName = ref('');
const isDeleting = ref(false);

const openEditList = () => {
    editListName.value = props.list.name;
    showEditList.value = true;
};

const updateList = () => {
    if (!editListName.value.trim()) return;
    router.patch(
        route('projects.update', [props.workspace.id, props.space.id, props.list.id]),
        { name: editListName.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                showEditList.value = false;
            },
        }
    );
};

const duplicateList = () => {
    router.post(
        route('projects.duplicate', [props.workspace.id, props.space.id, props.list.id]),
        {},
        { preserveScroll: true }
    );
};

const showDeleteList = ref(false);

const confirmDeleteList = () => {
    if (isDeleting.value) return;
    isDeleting.value = true;
    router.delete(route('projects.destroy', [props.workspace.id, props.space.id, props.list.id]), {
        onSuccess: () => router.visit(route('spaces.show', [props.workspace.id, props.space.id])),
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

// Move-to-folder
const showMoveToFolder = ref(false);
const selectedFolder = ref(null);

const availableFolders = computed(() => [
    { id: null, name: 'No Folder (Root)' },
    ...(props.space?.folders || []),
]);

const openMoveToFolder = () => {
    selectedFolder.value = props.list.folder_id;
    showMoveToFolder.value = true;
};

const moveToFolder = () => {
    router.post(
        route('projects.move-to-folder', [props.workspace.id, props.space.id, props.list.id]),
        { folder_id: selectedFolder.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showMoveToFolder.value = false;
            },
        }
    );
};

// CPM (Gantt)
const {
    cpmData,
    loading: cpmLoading,
    fetchCpmData,
    addDependency: cpmAddDependency,
    removeDependency: cpmRemoveDependency,
    reset: cpmReset,
} = useCpm({
    workspace: computed(() => props.workspace),
    space: computed(() => props.space),
    list: computed(() => props.list),
    parentTask: computed(() => props.parentTask),
});

watch(viewMode, (mode) => {
    if (mode === 'gantt' && props.parentTask && !cpmData.value) {
        fetchCpmData();
    }
});

watch(
    () => props.parentTask,
    (parent) => {
        if (parent) {
            cpmReset();
            if (viewMode.value === 'gantt') fetchCpmData();
        }
    },
    { immediate: true }
);

const handleGanttSubtaskClick = (subtask) => {
    for (const statusId in localTasksByStatus.value) {
        const found = localTasksByStatus.value[statusId].find((s) => s.id === subtask.id);
        if (found) {
            handleTaskOpen(found);
            return;
        }
    }
};

const viewGantt = () => {
    viewMode.value = 'gantt';
    if (!cpmData.value) fetchCpmData();
};

const isSubtaskCritical = (subtaskId) => {
    if (!cpmData.value?.success) return false;
    return cpmData.value.data?.criticalPath?.includes(subtaskId) || false;
};

provide('isSubtaskCritical', isSubtaskCritical);
provide('cpmData', cpmData);

// URL syncing
watch(
    () => page.url,
    () => {
        viewMode.value = resolveViewModeFromUrl();
        const requestedSprintId = getRequestedSprintIdFromUrl();
        if (!requestedSprintId) return;
        if ((props.sprints || []).some((item) => item.id === requestedSprintId)) {
            selectedSprintId.value = requestedSprintId;
        }
    }
);

// Initialize selectedSprintId from URL on mount / when sprints load
watch(
    () => props.sprints,
    (items) => {
        if (!items?.length) {
            selectedSprintId.value = null;
            return;
        }
        const stillExists = items.some((item) => item.id === selectedSprintId.value);
        if (!stillExists) {
            const requested = getRequestedSprintIdFromUrl();
            const requestedSprint = requested
                ? items.find((item) => item.id === requested)
                : null;
            selectedSprintId.value = requestedSprint?.id || items[0].id;
        }
    },
    { immediate: true }
);

const openSprintBoard = (sprint) => {
    selectedSprintId.value = sprint.id;
    router.visit(route('sprints.show', [props.workspace.id, props.space.id, sprint.id]));
};

const onSprintSaved = () => router.reload({ only: ['sprints'] });

onMounted(() => {
    openDetailFromQuery();
});
</script>

<template>
    <MainLayout :title="list?.name || 'Project'">
        <div class="list-page">
            <!-- Breadcrumb -->
            <div class="breadcrumb-header">
                <div class="flex items-center gap-2 text-sm">
                    <v-icon size="16">mdi-view-dashboard</v-icon>
                    <a :href="route('dashboard')" class="text-gray-400 hover:text-white">Dashboard</a>
                    <v-icon size="16" class="text-gray-600">mdi-chevron-right</v-icon>
                    <a :href="route('workspaces.show', workspace.id)"
                        class="flex items-center gap-2 text-gray-400 hover:text-white">
                        <div class="w-4 h-4 rounded flex items-center justify-center text-white text-xs"
                            :style="{ backgroundColor: workspace?.color || '#6366F1' }">
                            {{ workspace?.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <span>{{ workspace?.name }}</span>
                    </a>
                    <v-icon size="16" class="text-gray-600">mdi-chevron-right</v-icon>
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 rounded flex items-center justify-center text-white text-xs"
                            :style="{ backgroundColor: space?.color || '#6366F1' }">
                            <v-icon size="10" color="white">mdi-folder</v-icon>
                        </div>
                        <a :href="route('spaces.show', [workspace.id, space.id])"
                            class="text-gray-400 hover:text-white">
                            {{ space?.name }}
                        </a>
                    </div>
                    <v-icon size="16" class="text-gray-600">mdi-chevron-right</v-icon>
                    <a v-if="parentTask" :href="route('projects.show', [workspace.id, space.id, list.id])"
                        class="flex items-center gap-2 text-gray-400 hover:text-white">
                        <v-icon size="16">mdi-package-variant-closed</v-icon>
                        <span>{{ list?.name }}</span>
                    </a>
                    <div v-else class="flex items-center gap-2">
                        <v-icon size="16" color="primary">mdi-package-variant-closed</v-icon>
                        <span class="font-medium text-white">{{ list?.name }}</span>
                    </div>
                    <template v-if="parentTask">
                        <v-icon size="16" class="text-gray-600">mdi-chevron-right</v-icon>
                        <div class="flex items-center gap-2">
                            <v-icon size="16" color="primary">mdi-file-tree-outline</v-icon>
                            <span class="font-medium text-white">{{ parentTask.name }}</span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Subtask View Banner -->
            <div v-if="parentTask"
                class="bg-gradient-to-r from-blue-900/40 to-purple-900/40 border border-blue-700/30 rounded-lg p-3 mb-4 flex items-center gap-3">
                <v-btn icon="mdi-arrow-left" variant="tonal" size="small" color="primary"
                    @click="router.visit(route('projects.show', [workspace.id, space.id, list.id]))" />
                <div class="flex items-center gap-2 flex-1">
                    <v-icon size="20" color="primary">mdi-file-tree-outline</v-icon>
                    <div>
                        <div class="text-xs text-gray-400">Viewing subtasks of</div>
                        <div class="font-semibold text-white">{{ parentTask.name }}</div>
                    </div>
                </div>
                <v-chip size="small" color="primary" variant="flat">Subtask Board</v-chip>
                <v-btn variant="tonal" color="warning" size="small" :loading="cpmLoading" @click="viewGantt">
                    <v-icon start size="16">mdi-chart-gantt</v-icon>
                    CPM Analysis
                </v-btn>
            </div>

            <!-- List Header -->
            <div class="list-header">
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold">
                        {{ parentTask ? 'Subtasks' : list?.name }}
                    </h1>
                </div>

                <div class="flex items-center gap-2 list-toolbar-controls">
                    <v-text-field v-model="searchQuery" placeholder="Search tasks..." prepend-inner-icon="mdi-magnify"
                        variant="outlined" density="compact" hide-details single-line style="width: 200px;"
                        class="toolbar-search" />

                    <v-menu :close-on-content-click="false">
                        <template v-slot:activator="{ props: menuProps }">
                            <v-btn v-bind="menuProps" variant="outlined" size="small" class="toolbar-filter-btn">
                                <v-icon start size="16">mdi-filter-variant</v-icon>
                                Filter
                                <v-badge v-if="activeFilterCount" :content="activeFilterCount" color="primary" inline
                                    class="ml-1" />
                            </v-btn>
                        </template>
                        <v-card width="280" color="surface">
                            <v-card-text>
                                <div class="text-sm font-medium mb-2">Filters</div>
                                <v-select v-model="filterPriority" :items="PRIORITIES" item-title="name"
                                    item-value="level" label="Priority" variant="outlined" density="compact" multiple
                                    chips closable-chips hide-details class="mb-3" bg-color="#1e1e1e" />
                                <v-autocomplete v-model="filterAssignee" :items="members" item-title="name"
                                    item-value="id" label="Assignee" variant="outlined" density="compact" multiple
                                    chips closable-chips hide-details class="mb-3" bg-color="#1e1e1e" />
                                <v-autocomplete v-model="filterLabel" :items="labels" item-title="name"
                                    item-value="id" label="Label" variant="outlined" density="compact" multiple chips
                                    closable-chips hide-details class="mb-3" bg-color="#1e1e1e">
                                    <template #chip="{ props: chipProps, item }">
                                        <v-chip v-bind="chipProps" :color="item.raw.color" size="small" label>
                                            {{ item.raw.name }}
                                        </v-chip>
                                    </template>
                                    <template #item="{ props: itemProps, item }">
                                        <v-list-item v-bind="itemProps">
                                            <template #prepend>
                                                <v-icon :color="item.raw.color" size="12">mdi-circle</v-icon>
                                            </template>
                                        </v-list-item>
                                    </template>
                                </v-autocomplete>
                                <v-autocomplete v-if="parentTask" v-model="filterSprint" :items="sprints"
                                    item-title="name" item-value="id" label="Sprint" variant="outlined"
                                    density="compact" clearable hide-details bg-color="#1e1e1e" />
                            </v-card-text>
                            <v-card-actions>
                                <v-btn variant="text" size="small"
                                    @click="filterPriority = []; filterAssignee = []; filterLabel = []; filterSprint = null">
                                    Clear All
                                </v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-menu>

                    <v-btn-group density="compact" variant="outlined" rounded="0" class="view-controls-group">
                        <v-btn size="small" rounded="0" class="view-mode-btn"
                            :class="{ 'view-mode-btn--active': viewMode === 'board' }" @click="viewMode = 'board'">
                            <v-icon size="16">mdi-view-column</v-icon>
                        </v-btn>
                        <v-btn size="small" rounded="0" class="view-mode-btn"
                            :class="{ 'view-mode-btn--active': viewMode === 'list' }" @click="viewMode = 'list'">
                            <v-icon size="16">mdi-format-list-bulleted</v-icon>
                        </v-btn>
                        <v-btn size="small" rounded="0" class="view-mode-btn"
                            :class="{ 'view-mode-btn--active': viewMode === 'calendar' }"
                            @click="viewMode = 'calendar'">
                            <v-icon size="16">mdi-calendar</v-icon>
                        </v-btn>
                        <v-btn size="small" rounded="0" class="view-mode-btn"
                            :class="{ 'view-mode-btn--active': viewMode === 'sprint' }" @click="viewMode = 'sprint'">
                            <v-icon size="16">mdi-calendar-clock</v-icon>
                        </v-btn>
                        <v-btn v-if="parentTask" size="small" rounded="0" class="view-mode-btn"
                            :class="{ 'view-mode-btn--active': viewMode === 'gantt' }" @click="viewMode = 'gantt'">
                            <v-icon size="16">mdi-chart-gantt</v-icon>
                        </v-btn>
                    </v-btn-group>

                    <v-menu>
                        <template v-slot:activator="{ props: menuProps }">
                            <v-btn v-bind="menuProps" icon variant="text" size="small">
                                <v-icon>mdi-dots-horizontal</v-icon>
                            </v-btn>
                        </template>
                        <v-card color="surface">
                            <v-list density="compact">
                                <v-list-item prepend-icon="mdi-account-lock-outline" title="Project Access"
                                    @click="router.visit(route('projects.settings', [workspace.id, space.id, list.id]))" />
                                <v-list-item v-if="canManageProject" prepend-icon="mdi-pencil-outline"
                                    title="Edit Project" @click="openEditList" />
                                <v-list-item v-if="canManageSpace" prepend-icon="mdi-folder-move-outline"
                                    title="Move to Folder" @click="openMoveToFolder" />
                                <v-list-item v-if="canManageProject" prepend-icon="mdi-content-copy"
                                    title="Duplicate Project" @click="duplicateList" />
                                <template v-if="canDeleteProject">
                                    <v-divider />
                                    <v-list-item prepend-icon="mdi-delete-outline" title="Delete Project"
                                        class="text-error" @click="showDeleteList = true" />
                                </template>
                            </v-list>
                        </v-card>
                    </v-menu>
                </div>
            </div>

            <!-- Board View -->
            <div v-if="viewMode === 'board'" class="board-container">
                <div class="board-columns">
                    <StatusColumn v-for="status in statuses" :key="status.id" :status="status" :statuses="statuses"
                        :tasks="filteredTasksByStatus[status.id] || []" :workspace="workspace" :space="space"
                        :list="list" :parent-task="parentTask" :can-add-task="canOperateTasks"
                        :can-manage-space="canManageSpace" @task-moved="handleTaskMoved"
                        @task-complete="handleTaskComplete" @task-open="handleTaskOpen" @add-task="handleAddTask"
                        @task-open-subtask="handleOpenSubtaskFromCard"
                        @task-subtask-toggle="handleSubtaskToggleFromCard" />

                    <div v-if="canManageSpace" class="add-status-column">
                        <v-btn v-if="!showAddStatus" variant="text" block class="add-status-btn"
                            @click="showAddStatus = true">
                            <v-icon start>mdi-plus</v-icon>
                            Add Status
                        </v-btn>

                        <v-card v-else variant="outlined" rounded="lg" class="pa-3">
                            <v-text-field v-model="newStatusName" placeholder="Status name" variant="outlined"
                                density="compact" hide-details autofocus class="mb-2" @keydown.enter="addStatus"
                                @keydown.escape="showAddStatus = false" />
                            <ColorPicker v-model="newStatusColor" class="mb-3" />
                            <div class="flex gap-2">
                                <v-btn color="primary" size="small" @click="addStatus">Add</v-btn>
                                <v-btn variant="text" size="small" @click="showAddStatus = false">Cancel</v-btn>
                            </div>
                        </v-card>
                    </div>
                </div>
            </div>

            <!-- List View -->
            <div v-else-if="viewMode === 'list'" class="list-view">
                <v-card variant="outlined" rounded="lg">
                    <div class="overflow-x-auto">
                        <v-table>
                            <thead>
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Task</th>
                                    <th style="width: 150px;">Status</th>
                                    <th style="width: 120px;">Priority</th>
                                    <th style="width: 150px;">Assignee</th>
                                    <th style="width: 120px;">Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="status in statuses" :key="status.id">
                                    <tr v-for="task in filteredTasksByStatus[status.id] || []" :key="task.id"
                                        class="task-row" @click="handleTaskOpen(task)">
                                        <td>
                                            <v-checkbox-btn :model-value="!!task.completed_at"
                                                @click.stop="handleTaskComplete(task)" hide-details
                                                density="compact" />
                                        </td>
                                        <td>
                                            <div class="font-medium">{{ task.name }}</div>
                                            <div v-if="task.description" class="text-sm text-gray-500 truncate"
                                                style="max-width: 300px;">
                                                {{ task.description }}
                                            </div>
                                        </td>
                                        <td>
                                            <v-chip :color="status.color" size="small" variant="tonal">
                                                {{ status.name }}
                                            </v-chip>
                                        </td>
                                        <td>
                                            <v-chip v-if="task.priority_level"
                                                :color="getPriority(task.priority_level)?.color" size="small"
                                                variant="tonal">
                                                {{ getPriority(task.priority_level)?.name }}
                                            </v-chip>
                                            <span v-else class="text-gray-500">-</span>
                                        </td>
                                        <td>
                                            <div v-if="task.assignees?.length" class="flex items-center gap-1">
                                                <v-avatar v-for="assignee in task.assignees.slice(0, 3)"
                                                    :key="assignee.id" size="24"
                                                    :color="assignee.avatar_color || 'primary'">
                                                    <span class="text-xs">{{ assignee.initials }}</span>
                                                </v-avatar>
                                                <span v-if="task.assignees.length > 3" class="text-xs text-gray-500">
                                                    +{{ task.assignees.length - 3 }}
                                                </span>
                                            </div>
                                            <span v-else class="text-gray-500">-</span>
                                        </td>
                                        <td>
                                            <span v-if="task.due_date" class="text-sm">
                                                {{ new Date(task.due_date).toLocaleDateString() }}
                                            </span>
                                            <span v-else class="text-gray-500">-</span>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="!Object.values(filteredTasksByStatus).some(tasks => tasks.length)">
                                    <td colspan="6" class="text-center py-12 text-gray-500">
                                        <v-icon size="48" class="mb-2">mdi-checkbox-marked-circle-outline</v-icon>
                                        <div>No tasks yet</div>
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>
                    </div>
                </v-card>
            </div>

            <!-- Calendar View -->
            <CalendarView v-else-if="viewMode === 'calendar'" :items="allFilteredItems" :statuses="statuses"
                @item-open="handleTaskOpen" />

            <!-- Sprint View -->
            <SprintView v-else-if="viewMode === 'sprint'" :workspace="workspace" :space="space" :list="list"
                :sprints="sprints" :can-manage-task-structure="canManageTaskStructure"
                :selected-sprint-id="selectedSprintId" @sprint-open="openSprintBoard"
                @sprint-saved="onSprintSaved" />

            <!-- Gantt View -->
            <div v-else-if="viewMode === 'gantt' && parentTask" class="gantt-view">
                <div v-if="cpmLoading" class="flex items-center justify-center h-64">
                    <v-progress-circular indeterminate color="primary" size="48" />
                    <span class="ml-4 text-gray-400">Calculating Critical Path...</span>
                </div>

                <template v-else>
                    <div class="mb-4">
                        <CpmSummary :cpm-data="cpmData" @subtask-click="handleGanttSubtaskClick" />
                    </div>
                    <GanttChart :cpm-data="cpmData" :workspace="workspace" :space="space" :list="list"
                        :task="parentTask" @subtask-click="handleGanttSubtaskClick"
                        @dependency-add="cpmAddDependency" @dependency-remove="cpmRemoveDependency" />
                </template>
            </div>
        </div>

        <!-- Task Detail Panel -->
        <TaskDetailPanel v-model="showTaskDetail" :task="selectedTask" :workspace="workspace" :space="space"
            :list="list" :parent-task="panelParentTask || parentTask" :statuses="statuses" :members="members"
            :labels="labels" :sprints="sprints" :sibling-subtasks="siblingSubtasks"
            :can-operate-tasks="canOperateTasks" :can-manage-task-structure="canManageTaskStructure"
            @view-subtasks="viewSubtasks" @updated="refreshTasks" @open-subtask="openSubtaskInPanel" />

        <!-- Edit Project Dialog -->
        <v-dialog v-model="showEditList" max-width="400">
            <v-card>
                <v-card-title>Edit Project</v-card-title>
                <v-card-text>
                    <v-text-field v-model="editListName" label="Project Name" variant="outlined" autofocus />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showEditList = false">Cancel</v-btn>
                    <v-btn color="primary" @click="updateList">Update</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Project Dialog -->
        <DeleteConfirmDialog
            v-model="showDeleteList"
            item-type="project"
            :item-name="list?.name"
            :loading="isDeleting"
            warning-message="This will permanently delete the project along with all its tasks and subtasks."
            @confirm="confirmDeleteList"
        />

        <!-- Move to Folder Dialog -->
        <v-dialog v-model="showMoveToFolder" max-width="400">
            <v-card>
                <v-card-title>Move Project to Folder</v-card-title>
                <v-card-text>
                    <v-select v-model="selectedFolder" :items="availableFolders" item-title="name" item-value="id"
                        label="Select Folder" variant="outlined" hide-details bg-color="#1e1e1e" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showMoveToFolder = false">Cancel</v-btn>
                    <v-btn color="primary" @click="moveToFolder">Move</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </MainLayout>
</template>

<style scoped>
.list-page {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 48px);
}

.breadcrumb-header {
    padding: 12px 24px;
    border-bottom: 1px solid #2d2d30;
}

.breadcrumb-header a {
    transition: color 0.15s;
}

.list-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    border-bottom: 1px solid #2d2d30;
    flex-shrink: 0;
}

.list-toolbar-controls {
    --toolbar-control-height: 40px;
}

.toolbar-search :deep(.v-field) {
    min-height: var(--toolbar-control-height) !important;
    height: var(--toolbar-control-height) !important;
}

.toolbar-search :deep(.v-field__input) {
    min-height: calc(var(--toolbar-control-height) - 2px) !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    align-items: center;
}

.toolbar-filter-btn {
    height: var(--toolbar-control-height) !important;
}

.view-controls-group {
    overflow: visible;
    border-radius: 10px;
}

.view-mode-btn {
    min-width: 52px;
    height: var(--toolbar-control-height) !important;
}

.view-mode-btn :deep(.v-btn__content) {
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.view-mode-btn :deep(.v-icon) {
    line-height: 1 !important;
    overflow: visible;
}

.view-controls-group :deep(.v-btn:first-child) {
    border-top-left-radius: 10px !important;
    border-bottom-left-radius: 10px !important;
}

.view-controls-group :deep(.v-btn:last-child) {
    border-top-right-radius: 10px !important;
    border-bottom-right-radius: 10px !important;
}

.view-mode-btn--active {
    background: rgba(255, 255, 255, 0.14) !important;
}

.board-container {
    flex: 1;
    overflow: hidden;
    padding: 16px;
}

.board-columns {
    display: flex;
    gap: 16px;
    height: 100%;
    overflow-x: auto;
    padding-bottom: 16px;
}

.add-status-column {
    width: 300px;
    min-width: 300px;
    flex-shrink: 0;
}

.add-status-btn {
    height: 48px;
    border: 2px dashed #2d2d30;
    border-radius: 8px;
    opacity: 0.6;
}

.add-status-btn:hover {
    opacity: 1;
    border-color: #3d3d40;
}

.list-view,
.gantt-view {
    padding: 24px;
}

.gantt-view {
    height: calc(100vh - 200px);
    overflow: auto;
}

.task-row {
    cursor: pointer;
    transition: background-color 0.15s;
}

.task-row:hover {
    background-color: #2d2d30;
}
</style>
