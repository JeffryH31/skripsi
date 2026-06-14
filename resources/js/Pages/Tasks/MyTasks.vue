<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import TaskCard from '@/Components/Tasks/TaskCard.vue';
import { useSnackbar } from '@/composables/useSnackbar';

const { showSnackbar } = useSnackbar();
const page = usePage();

const currentUserId = computed(() => page.props?.auth?.user?.id || null);

const props = defineProps({
    tasks: { type: [Array, Object], default: () => [] },
});

const taskItems = computed(() => {
    if (Array.isArray(props.tasks)) {
        return props.tasks;
    }

    if (Array.isArray(props.tasks?.data)) {
        return props.tasks.data;
    }

    return [];
});

// Filter and sort state
const filterStatus = ref('all'); // all, todo, in_progress, done
const sortBy = ref('due_date'); // due_date, priority, created_at
const searchQuery = ref('');

// Group tasks by status/list
const groupBy = ref('status'); // status, list, due_date

// Filtered tasks
const filteredTasks = computed(() => {
    let result = [...taskItems.value];

    // Search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(task =>
            task.name.toLowerCase().includes(query) ||
            task.description?.toLowerCase().includes(query)
        );
    }

    // Status filter
    if (filterStatus.value !== 'all') {
        result = result.filter(task => {
            if (filterStatus.value === 'todo') return task.status?.type === 'open';
            if (filterStatus.value === 'in_progress') return task.status?.type === 'in_progress';
            if (filterStatus.value === 'done') return task.status?.is_closed;
            return true;
        });
    }

    // Sort
    result.sort((a, b) => {
        if (sortBy.value === 'due_date') {
            if (!a.due_date) return 1;
            if (!b.due_date) return -1;
            return new Date(a.due_date) - new Date(b.due_date);
        }
        if (sortBy.value === 'priority') {
            const aPriority = a.priority_level || 999;
            const bPriority = b.priority_level || 999;
            return aPriority - bPriority;
        }
        return new Date(b.created_at) - new Date(a.created_at);
    });

    return result;
});

// Grouped tasks
const groupedTasks = computed(() => {
    const tasks = filteredTasks.value;
    const groups = {};

    if (groupBy.value === 'status') {
        tasks.forEach(task => {
            const key = task.status?.name || 'No Status';
            if (!groups[key]) {
                groups[key] = { name: key, color: task.status?.color || '#6b7280', tasks: [] };
            }
            groups[key].tasks.push(task);
        });
    } else if (groupBy.value === 'project') {
        tasks.forEach(task => {
            const key = task.project?.name || 'No Project';
            if (!groups[key]) {
                groups[key] = { name: key, color: '#6b7280', tasks: [] };
            }
            groups[key].tasks.push(task);
        });
    } else if (groupBy.value === 'due_date') {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        const thisWeek = new Date(today);
        thisWeek.setDate(thisWeek.getDate() + 7);

        groups['Overdue'] = { name: 'Overdue', color: '#EF4444', tasks: [] };
        groups['Today'] = { name: 'Today', color: '#F59E0B', tasks: [] };
        groups['Tomorrow'] = { name: 'Tomorrow', color: '#3B82F6', tasks: [] };
        groups['This Week'] = { name: 'This Week', color: '#6366F1', tasks: [] };
        groups['Later'] = { name: 'Later', color: '#6b7280', tasks: [] };
        groups['No Due Date'] = { name: 'No Due Date', color: '#6b7280', tasks: [] };

        tasks.forEach(task => {
            if (!task.due_date) {
                groups['No Due Date'].tasks.push(task);
            } else {
                const dueDate = new Date(task.due_date);
                dueDate.setHours(0, 0, 0, 0);

                if (dueDate < today) {
                    groups['Overdue'].tasks.push(task);
                } else if (dueDate.getTime() === today.getTime()) {
                    groups['Today'].tasks.push(task);
                } else if (dueDate.getTime() === tomorrow.getTime()) {
                    groups['Tomorrow'].tasks.push(task);
                } else if (dueDate < thisWeek) {
                    groups['This Week'].tasks.push(task);
                } else {
                    groups['Later'].tasks.push(task);
                }
            }
        });
    }

    // Filter out empty groups
    return Object.values(groups).filter(g => g.tasks.length > 0);
});

// Task count
const taskCount = computed(() => filteredTasks.value.length);

// Handle task complete — tasks don't support completion (only subtasks do)
const handleTaskComplete = (task) => {
    showSnackbar('Tasks cannot be completed directly. Complete subtasks instead.', 'info');
};

// Auto-refresh tasks when returning from task detail/edit
const handleWindowFocus = () => {
    router.reload({ only: ['tasks'] });
};

onMounted(() => {
    window.addEventListener('focus', handleWindowFocus);
});

onUnmounted(() => {
    window.removeEventListener('focus', handleWindowFocus);
});

// Handle task open
const handleTaskOpen = (task) => {
    const baseUrl = route('projects.show', [
        task.project.space.workspace_id,
        task.project.space_id,
        task.project_id,
    ]);

    const assignedSubtaskId = Array.isArray(task?.subtasks)
        ? task.subtasks.find((subtask) =>
            Array.isArray(subtask?.assignees)
            && subtask.assignees.some((assignee) => assignee.id === currentUserId.value)
        )?.id
        : null;

    const url = assignedSubtaskId
        ? `${baseUrl}?task_id=${task.id}&open_subtask_id=${assignedSubtaskId}`
        : `${baseUrl}?open_task_id=${task.id}`;

    router.visit(url);
};
</script>

<template>
    <MainLayout title="My Tasks">
        <div class="my-tasks-page">
            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="text-2xl font-bold">My Tasks</h1>
                    <p class="text-gray-500 mt-1">{{ taskCount }} tasks assigned to you</p>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="flex items-center gap-3">
                    <!-- Search -->
                    <v-text-field v-model="searchQuery" placeholder="Search tasks..." prepend-inner-icon="mdi-magnify"
                        variant="outlined" density="compact" hide-details single-line style="width: 250px;" />

                    <!-- Status Filter -->
                    <v-btn-toggle v-model="filterStatus" mandatory density="compact" variant="outlined">
                        <v-btn value="all" size="small">All</v-btn>
                        <v-btn value="todo" size="small">To Do</v-btn>
                        <v-btn value="in_progress" size="small">In Progress</v-btn>
                        <v-btn value="done" size="small">Done</v-btn>
                    </v-btn-toggle>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Group By -->
                    <v-select v-model="groupBy" :items="[
                        { title: 'Status', value: 'status' },
                        { title: 'Project', value: 'project' },
                        { title: 'Due Date', value: 'due_date' },
                    ]" label="Group by" variant="outlined" density="compact" hide-details style="width: 140px;"
                        bg-color="#1e1e1e" />

                    <!-- Sort By -->
                    <v-select v-model="sortBy" :items="[
                        { title: 'Due Date', value: 'due_date' },
                        { title: 'Priority', value: 'priority' },
                        { title: 'Created', value: 'created_at' },
                    ]" label="Sort by" variant="outlined" density="compact" hide-details style="width: 130px;"
                        bg-color="#1e1e1e" />
                </div>
            </div>

            <!-- Tasks -->
            <div class="tasks-container">
                <div v-if="!groupedTasks.length" class="empty-state">
                    <v-icon size="80" color="grey-darken-1" class="mb-4">mdi-checkbox-marked-circle-outline</v-icon>
                    <h2 class="text-xl font-semibold mb-2">No tasks found</h2>
                    <p class="text-gray-500">
                        {{ searchQuery ? 'Try a different search term' : 'Tasks assigned to you will appear here' }}
                    </p>
                </div>

                <template v-else>
                    <div v-for="group in groupedTasks" :key="group.name" class="task-group">
                        <!-- Group Header -->
                        <div class="group-header">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: group.color }" />
                                <span class="font-medium">{{ group.name }}</span>
                                <span class="text-gray-500 text-sm">({{ group.tasks.length }})</span>
                            </div>
                        </div>

                        <!-- Tasks -->
                        <div class="task-list">
                            <TaskCard v-for="task in group.tasks" :key="task.id" :task="task" show-list :show-checkbox="false"
                                @complete="handleTaskComplete" @open-detail="handleTaskOpen" />
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.my-tasks-page {
    padding: 24px;
    max-width: 1000px;
    margin: 0 auto;
}

.page-header {
    margin-bottom: 24px;
}

.toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.tasks-container {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
}

.task-group {
    background-color: #1e1e1e;
    border-radius: 12px;
    overflow: hidden;
}

.group-header {
    padding: 16px;
    border-bottom: 1px solid #2d2d30;
}

.task-list {
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
</style>
