<script setup>
import { ref, computed, inject } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import TaskCard from '@/Components/Tasks/TaskCard.vue';
import { useSnackbar } from '@/composables/useSnackbar';
import { formatMinutes as formatDuration } from '@/utils/duration';

const props = defineProps({
    workspaces: { type: Array, default: () => [] },
    activeWorkspace: { type: Object, default: null },
    mySubtasks: { type: Array, default: () => [] },
    overdueSubtasks: { type: Array, default: () => [] },
    runningTimer: { type: Object, default: null },
    timeStats: { type: Object, default: null },
    recentActivity: { type: Array, default: () => [] },
});

// Active tab for tasks
const activeTaskTab = ref('my-tasks');
const { showSnackbar } = useSnackbar();
const page = usePage();

const currentUserId = computed(() => page.props?.auth?.user?.id || null);

// Map a subtask to a task-like object for TaskCard rendering
const mapSubtaskToCard = (subtask) => ({
    id: subtask.id,
    name: subtask.name,
    status: subtask.status,
    labels: subtask.labels || [],
    priority_level: subtask.priority_level,
    due_date: subtask.due_date,
    time_spent: subtask.time_spent,
    completed_at: subtask.completed_at,
    assignees: subtask.assignees || [],
    project: subtask.task?.project,
    project_id: subtask.task?.project_id,
    // Extra context for navigation
    _subtask_id: subtask.id,
    _task: subtask.task,
});

const buildSubtaskDeepLink = (cardItem) => {
    const task = cardItem._task;
    if (!task) return '#';
    const workspaceId = task.project?.space?.workspace_id || props.activeWorkspace?.id;
    const spaceId = task.project?.space_id;
    const listId = task.project_id;

    const baseUrl = route('projects.show', [workspaceId, spaceId, listId]);
    return `${baseUrl}?task_id=${task.id}&open_subtask_id=${cardItem._subtask_id}`;
};


// Quick create
const showQuickCreate = ref(false);
const quickTaskName = ref('');
const quickProject = ref(null);
const isProcessing = ref(false);

// Get all projects from active workspace
const availableProjects = computed(() => {
    if (!props.activeWorkspace?.spaces) return [];

    const lists = [];
    props.activeWorkspace.spaces.forEach(space => {
        // Lists without folder
        if (space.projects_without_folder) {
            space.projects_without_folder.forEach(list => {
                lists.push({
                    id: list.id,
                    name: list.name,
                    space_id: space.id,
                    space_name: space.name,
                    workspace_id: props.activeWorkspace.id,
                    display: `${space.name} / ${list.name}`
                });
            });
        }
        // Lists in folders
        if (space.folders) {
            space.folders.forEach(folder => {
                if (folder.projects) {
                    folder.projects.forEach(list => {
                        lists.push({
                            id: list.id,
                            name: list.name,
                            space_id: space.id,
                            space_name: space.name,
                            workspace_id: props.activeWorkspace.id,
                            display: `${space.name} / ${folder.name} / ${list.name}`
                        });
                    });
                }
            });
        }
    });
    return lists;
});

// Check if workspace has spaces
const hasSpaces = computed(() => {
    return props.activeWorkspace?.spaces && props.activeWorkspace.spaces.length > 0;
});

const createQuickTask = () => {
    if (!quickTaskName.value.trim() || !quickProject.value || isProcessing.value) return;
    isProcessing.value = true;

    router.post(
        route('tasks.store', [
            quickProject.value.workspace_id,
            quickProject.value.space_id,
            quickProject.value.id
        ]),
        { name: quickTaskName.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                quickTaskName.value = '';
                quickProject.value = null;
                showQuickCreate.value = false;
            },
            onError: () => showSnackbar('Failed to create task', 'error'),
            onFinish: () => { isProcessing.value = false; }
        }
    );
};

// Subtask cards (limit to 15)
const recentSubtasks = computed(() => (props.mySubtasks || []).slice(0, 15).map(mapSubtaskToCard));
const overdueCards = computed(() => (props.overdueSubtasks || []).map(mapSubtaskToCard));

// Handle task complete — not supported from dashboard card
const handleTaskComplete = (task) => {
    showSnackbar('Open the subtask to change its status.', 'info');
};

// Handle task open
const handleTaskOpen = (cardItem) => {
    router.visit(buildSubtaskDeepLink(cardItem));
};

// Navigate to the task containing the running timer's subtask
const goToTimerTask = () => {
    const subtask = props.runningTimer?.subtask;
    const t = subtask?.task;
    if (!t) return;

    const baseUrl = route('projects.show', [
        t.project.space.workspace_id,
        t.project.space.id,
        t.project.id,
    ]);

    // Open subtask board and auto-open running subtask detail panel
    const url = `${baseUrl}?task_id=${t.id}&open_subtask_id=${subtask?.id}`;
    router.visit(url);
};

// Navigate to first space or workspaces page
const goToFirstSpaceOrWorkspaces = () => {
    if (!props.activeWorkspace) {
        router.visit(route('dashboard'));
        return;
    }

    const spaces = props.activeWorkspace?.spaces || [];
    if (spaces.length > 0) {
        // Navigate to the first space
        router.visit(route('spaces.show', [props.activeWorkspace.id, spaces[0].id]));
    } else {
        // No spaces exist, create a space
        showSnackbar('Create a space first to start organizing your work', 'info');
        // Redirect to workspace to create spaces
        router.visit(route('workspaces.show', props.activeWorkspace.id));
    }
};

// Open create space dialog (provided by MainLayout via inject)
const openCreateSpaceDialog = inject('openCreateSpaceDialog', null);
const openCreateSpace = () => {
    if (typeof openCreateSpaceDialog === 'function') {
        openCreateSpaceDialog();
    } else {
        showSnackbar('Please use the sidebar to create a space.', 'info');
    }
};

// Recent activity helpers
const activityIcon = (action) => {
    const icons = {
        created: 'mdi-plus-circle-outline',
        updated: 'mdi-pencil-outline',
        deleted: 'mdi-trash-can-outline',
        completed: 'mdi-check-circle-outline',
        reopened: 'mdi-restore',
        assigned: 'mdi-account-plus-outline',
        unassigned: 'mdi-account-minus-outline',
        commented: 'mdi-comment-outline',
        comment_resolved: 'mdi-comment-check-outline',
        time_logged: 'mdi-clock-outline',
        timer_started: 'mdi-play-circle-outline',
        timer_stopped: 'mdi-stop-circle-outline',
        status_changed: 'mdi-swap-horizontal',
        priority_changed: 'mdi-flag-outline',
        archived: 'mdi-archive-outline',
        label_added: 'mdi-tag-outline',
        label_removed: 'mdi-tag-off-outline',
        duplicated: 'mdi-content-copy',
    };
    return icons[action] ?? 'mdi-information-outline';
};

const activityColor = (action) => {
    const colors = {
        completed: 'success',
        created: 'primary',
        deleted: 'error',
        timer_started: 'teal',
        timer_stopped: 'blue-grey',
        time_logged: 'blue',
        commented: 'indigo',
        comment_resolved: 'success',
        status_changed: 'warning',
        priority_changed: 'orange',
    };
    return colors[action] ?? 'grey';
};

const fmtRelative = (ts) => {
    if (!ts) return '';
    const diff = Math.floor((Date.now() - new Date(ts).getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
};
</script>

<template>
    <MainLayout title="Home">
        <div class="dashboard-page">
            <!-- Breadcrumb / Context Header -->
            <div class="context-header">
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                    <v-icon size="16">mdi-view-dashboard</v-icon>
                    <span>Dashboard</span>
                    <v-icon size="16">mdi-chevron-right</v-icon>
                    <div v-if="activeWorkspace" class="flex items-center gap-2">
                        <div class="w-4 h-4 rounded flex items-center justify-center text-white text-xs"
                            :style="{ backgroundColor: activeWorkspace.color || '#6366F1' }">
                            {{ activeWorkspace.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <span class="font-medium text-white">{{ activeWorkspace.name }}</span>
                    </div>
                    <span v-else class="text-gray-500 italic">No workspace selected</span>
                </div>
            </div>

            <div class="dashboard-grid">
                <!-- Left Column -->
                <div class="dashboard-main">
                    <!-- Overdue Alert -->
                    <v-alert v-if="overdueCards?.length" type="warning" variant="tonal" class="mb-4" closable>
                        <v-alert-title>{{ overdueCards.length }} Overdue Subtask{{ overdueCards.length > 1 ? 's' : ''
                        }}</v-alert-title>
                        You have subtasks that need your attention.
                    </v-alert>

                    <!-- Quick Create -->
                    <v-card class="mb-4" variant="outlined" rounded="lg">
                        <v-card-text>
                            <div v-if="!showQuickCreate"
                                class="flex items-center gap-2 cursor-pointer hover:bg-[#2d2d30] rounded-lg p-2 -m-2"
                                @click="showQuickCreate = true">
                                <v-icon color="primary">mdi-plus-circle</v-icon>
                                <span class="text-gray-400">Create a task...</span>
                            </div>
                            <div v-else class="space-y-2">
                                <v-text-field v-model="quickTaskName" placeholder="What needs to be done?"
                                    variant="outlined" density="compact" hide-details autofocus
                                    @keydown.enter="createQuickTask" @keydown.escape="showQuickCreate = false" />
                                <v-alert v-if="!availableProjects.length" type="info" variant="tonal" density="compact"
                                    class="text-sm">
                                    <div class="flex items-center justify-between">
                                        <span v-if="!hasSpaces">No spaces available. Create a space first.</span>
                                        <span v-else>No projects available. Go to a space to create a project.</span>
                                        <v-btn v-if="!hasSpaces" size="small" variant="text" color="primary"
                                            @click="openCreateSpace">
                                            Create a Space
                                        </v-btn>
                                        <v-btn v-else size="small" variant="text" color="primary"
                                            @click="router.visit(route('spaces.show', [activeWorkspace.id, activeWorkspace.spaces[0].id]))">
                                            Go to Spaces
                                        </v-btn>
                                    </div>
                                </v-alert>

                                <v-select v-model="quickProject" :items="availableProjects" item-title="display"
                                    item-value="id" return-object label="Select Project" variant="outlined"
                                    density="compact" hide-details :disabled="!availableProjects.length"
                                    placeholder="Choose a project..." bg-color="#1e1e1e" base-color="white"
                                    color="primary" />
                                <div class="flex gap-2">
                                    <v-btn color="primary" size="small" @click="createQuickTask" :loading="isProcessing"
                                        :disabled="!quickTaskName.trim() || !quickProject">
                                        Create Task
                                    </v-btn>
                                    <v-btn variant="text" size="small" @click="showQuickCreate = false">
                                        Cancel
                                    </v-btn>
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>

                    <!-- My Tasks -->
                    <v-card variant="outlined" rounded="lg">
                        <v-card-title class="flex items-center justify-between">
                            <span>My Tasks</span>
                            <v-btn variant="text" size="small" :href="route('my-tasks')">
                                View All
                                <v-icon end>mdi-arrow-right</v-icon>
                            </v-btn>
                        </v-card-title>
                        <v-divider />

                        <v-tabs v-model="activeTaskTab" color="primary">
                            <v-tab value="my-tasks">Assigned to me</v-tab>
                            <v-tab value="overdue">
                                Overdue
                                <v-badge v-if="overdueCards?.length" :content="overdueCards.length" color="error" inline
                                    class="ml-1" />
                            </v-tab>
                        </v-tabs>

                        <v-tabs-window v-model="activeTaskTab">
                            <v-tabs-window-item value="my-tasks">
                                <div v-if="!recentSubtasks.length" class="pa-8 text-center text-gray-500">
                                    <v-icon size="48" class="mb-2">mdi-checkbox-marked-circle-outline</v-icon>
                                    <div>No subtasks assigned to you</div>
                                </div>
                                <div v-else class="pa-2 overflow-y-auto" style="max-height: 600px;">
                                    <div class="space-y-2">
                                        <TaskCard v-for="item in recentSubtasks" :key="item._subtask_id" :task="item" show-list
                                            :show-checkbox="false"
                                            :parent-task-name="item._task?.name"
                                            @complete="handleTaskComplete"
                                            @open-detail="handleTaskOpen" />
                                    </div>
                                </div>
                            </v-tabs-window-item>

                            <v-tabs-window-item value="overdue">
                                <div v-if="!overdueCards?.length" class="pa-8 text-center text-gray-500">
                                    <v-icon size="48" class="mb-2">mdi-check-circle</v-icon>
                                    <div>No overdue subtasks!</div>
                                </div>
                                <div v-else class="pa-2 overflow-y-auto" style="max-height: 600px;">
                                    <div class="space-y-2">
                                        <TaskCard v-for="item in overdueCards" :key="item._subtask_id" :task="item" show-list
                                            :show-checkbox="false"
                                            :parent-task-name="item._task?.name"
                                            @complete="handleTaskComplete"
                                            @open-detail="handleTaskOpen" />
                                    </div>
                                </div>
                            </v-tabs-window-item>
                        </v-tabs-window>
                    </v-card>
                </div>

                <!-- Right Sidebar -->
                <div class="dashboard-sidebar">
                    <!-- Time Tracking Widget -->
                    <v-card variant="outlined" rounded="lg" class="mb-4">
                        <v-card-title class="flex items-center gap-2">
                            <v-icon size="20">mdi-timer-outline</v-icon>
                            Time Tracked
                        </v-card-title>
                        <v-card-text>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-2xl font-bold">{{ formatDuration(timeStats?.today) }}</div>
                                    <div class="text-xs text-gray-500">Today</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold">{{ formatDuration(timeStats?.week) }}</div>
                                    <div class="text-xs text-gray-500">This Week</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-warning">{{
                                        formatDuration(timeStats?.idle_today) }}</div>
                                    <div class="text-xs text-gray-500">Idle Today</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-info">{{ timeStats?.todo_count || 0 }}</div>
                                    <div class="text-xs text-gray-500">To-do Items</div>
                                </div>
                            </div>

                            <!-- Running Timer -->
                            <div v-if="runningTimer"
                                class="mt-4 p-3 bg-success/10 rounded-lg cursor-pointer hover:bg-success/20 transition-colors"
                                @click="goToTimerTask">
                                <div class="flex items-center gap-2 mb-2">
                                    <v-icon color="success" size="16">mdi-timer-outline</v-icon>
                                    <span class="text-sm font-medium text-success">Timer Running</span>
                                </div>
                                <div class="text-sm truncate">{{ runningTimer.subtask?.name }}</div>
                                <div class="text-xs text-gray-500">{{ runningTimer.subtask?.task?.name }} &middot; {{
                                    runningTimer.subtask?.task?.project?.space?.name }}</div>
                            </div>
                        </v-card-text>
                    </v-card>

                    <!-- Recent Activity -->
                    <v-card variant="outlined" rounded="lg" class="mb-4">
                        <div class="flex items-center gap-1.5 px-3 pt-3 pb-2">
                            <v-icon size="16" color="primary">mdi-timeline-clock-outline</v-icon>
                            <span class="text-sm font-semibold">Recent Activity</span>
                        </div>

                        <div v-if="!recentActivity?.length" class="pa-6 text-center text-gray-500">
                            <v-icon size="36" class="mb-2">mdi-timeline-outline</v-icon>
                            <div class="text-sm">No activity yet</div>
                        </div>

                        <div v-else class="overflow-y-auto" style="max-height: 320px;">
                            <template v-for="(a, idx) in recentActivity" :key="a.id">
                                <div class="flex items-start gap-2 px-3 py-2">
                                    <v-avatar :color="a.user?.avatar_color || 'primary'" size="28"
                                        class="flex-shrink-0 mt-0.5">
                                        <img v-if="a.user?.profile_photo_url" :src="a.user.profile_photo_url" />
                                        <span v-else class="text-[9px] font-bold">{{ a.user?.initials ?? '?' }}</span>
                                    </v-avatar>

                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs leading-tight">
                                            <span class="font-medium text-white">{{ a.user?.name ?? 'Someone' }}</span>
                                            <span class="text-gray-400 ml-1">{{ a.description }}</span>
                                        </div>
                                        <div v-if="a.properties?.name"
                                            class="text-[10px] text-gray-500 truncate mt-0.5">
                                            {{ a.properties.name }}
                                        </div>
                                    </div>

                                    <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                        <v-icon :color="activityColor(a.action)" size="14">{{ activityIcon(a.action)
                                            }}</v-icon>
                                        <span class="text-[9px] text-gray-500 whitespace-nowrap">{{
                                            fmtRelative(a.created_at) }}</span>
                                    </div>
                                </div>
                                <v-divider v-if="idx < recentActivity.length - 1" class="opacity-30" />
                            </template>
                        </div>
                    </v-card>

                </div>
            </div>
        </div>

    </MainLayout>
</template>

<style scoped>
.dashboard-page {
    padding: 24px;
    max-width: 1400px;
    margin: 0 auto;
}

@media (max-width: 640px) {
    .dashboard-page {
        padding: 12px;
    }
}

.welcome-section {
    margin-bottom: 24px;
}

.context-header {
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #2d2d30;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 24px;
}

@media (max-width: 1024px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-sidebar {
        order: -1;
    }
}

.space-y-2>*+* {
    margin-top: 8px;
}

.grid {
    display: grid;
}

.grid-cols-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.gap-4 {
    gap: 16px;
}
</style>
