<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { safeFetch } from '@/utils/safeFetch';
import { useSnackbar } from '@/composables/useSnackbar';
import { formatMinutes as formatDuration } from '@/utils/duration';
import { formatDateTime } from '@/utils/date';

const { showSnackbar } = useSnackbar();

const props = defineProps({
    activeWorkspace: { type: Object, default: null },
    entries: { type: Array, default: () => [] },
    runningTimer: { type: Object, default: null },
    subtasks: { type: Array, default: () => [] },
});

// Date filter
const dateRange = ref('today');
const customStart = ref(new Date().toISOString().split('T')[0]);
const customEnd = ref(new Date().toISOString().split('T')[0]);

// Running timer
const elapsedSeconds = ref(0);
const timerInterval = ref(null);

// Table state
const search = ref('');
const sortBy = ref([{ key: 'started_at', order: 'desc' }]);

const tableHeaders = [
    { title: 'Subtask', key: 'subtask_name', sortable: true },
    { title: 'Task', key: 'task_name', sortable: true },
    { title: 'Start', key: 'started_at', sortable: true },
    { title: 'End', key: 'ended_at', sortable: true },
    { title: 'Duration', key: 'duration', sortable: true, align: 'end' },
    { title: 'Billable', key: 'is_billable', sortable: true, align: 'center' },
    { title: '', key: 'actions', sortable: false, align: 'end', width: 80 },
];

// Dialog state
const showAddEntry = ref(false);
const editingEntry = ref(null);
const selectedSubtask = ref(null);
const subtaskSearch = ref('');
const entryForm = ref({ started_at: '', ended_at: '', is_billable: false });

// Computed
const formatRunningTime = computed(() => {
    const h = Math.floor(elapsedSeconds.value / 3600);
    const m = Math.floor((elapsedSeconds.value % 3600) / 60);
    const s = elapsedSeconds.value % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

const periodLabel = computed(() => {
    if (dateRange.value === 'all') return 'All Time';
    if (dateRange.value === 'today') return 'Today';
    if (dateRange.value === 'week') return 'This Week';
    if (dateRange.value === 'month') return 'This Month';
    if (dateRange.value === 'custom') {
        if (!customStart.value && !customEnd.value) return 'Custom Range';
        if (customStart.value === customEnd.value) return customStart.value;
        return `${customStart.value} ? ${customEnd.value}`;
    }
    return 'All Time';
});

// Stats computed from entries so they automatically reflect the date filter
const periodStats = computed(() => {
    const completed = props.entries.filter(e => !e.is_running);
    const total = completed.reduce((sum, e) => sum + (e.duration || 0), 0);
    const billable = completed.filter(e => e.is_billable).reduce((sum, e) => sum + (e.duration || 0), 0);
    return { total, billable, count: completed.length };
});

// Flat table rows with searchable fields
const tableItems = computed(() => {
    return props.entries.map(e => ({
        ...e,
        subtask_name: e.subtask?.name ?? '?',
        task_name: e.subtask?.task?.name ?? '?',
        project_name: e.subtask?.task?.project?.name ?? '',
    }));
});

const canSave = computed(() => {
    const hasTime = entryForm.value.started_at && entryForm.value.ended_at;
    return editingEntry.value ? hasTime : (selectedSubtask.value && hasTime);
});



// Navigation
const goToTask = (entry) => {
    const subtask = entry?.subtask;
    const task = subtask?.task;
    if (!task) return;
    const baseUrl = route('projects.show', [
        props.activeWorkspace.id,
        task.project?.space_id,
        task.project_id,
    ]);
    const url = `${baseUrl}?task_id=${task.id}&open_subtask_id=${subtask.id}`;
    router.visit(url);
};

// Timer (uses fetch to avoid Inertia redirect issues)
const stopTimer = async (entry) => {
    const task = entry?.subtask?.task;
    if (!task) return;
    try {
        const url = route('tasks.timer.stop', [
            props.activeWorkspace.id,
            task.project?.space_id,
            task.project_id,
            task.id,
            entry.id,
        ]);
        const res = await safeFetch(url, { method: 'POST' });
        if (res.ok || res.status === 302 || res.status === 303) {
            stopTimerInterval();
            elapsedSeconds.value = 0;
            router.reload({ preserveScroll: true });
        } else if (res.status === 419) {
            // CSRF token expired ? fall back to a full reload to refresh it
            router.reload();
        }
    } catch {
        showSnackbar('Failed to stop timer', 'error');
    }
};

const startTimerInterval = () => {
    if (timerInterval.value) clearInterval(timerInterval.value);
    timerInterval.value = setInterval(() => { elapsedSeconds.value++; }, 1000);
};

const stopTimerInterval = () => {
    if (timerInterval.value) { clearInterval(timerInterval.value); timerInterval.value = null; }
};

// Entry CRUD
const openAddDialog = () => {
    editingEntry.value = null;
    selectedSubtask.value = null;
    subtaskSearch.value = '';
    resetForm();
    showAddEntry.value = true;
};

const editEntry = (entry) => {
    editingEntry.value = entry;
    selectedSubtask.value = null;
    entryForm.value = {
        started_at: entry.started_at ? new Date(entry.started_at).toISOString().slice(0, 16) : '',
        ended_at: entry.ended_at ? new Date(entry.ended_at).toISOString().slice(0, 16) : '',
        is_billable: entry.is_billable,
    };
    showAddEntry.value = true;
};

const saveEntry = () => {
    if (editingEntry.value) {
        router.patch(
            route('time-entries.update', editingEntry.value.id),
            entryForm.value,
            {
                preserveScroll: true,
                onSuccess: () => { showAddEntry.value = false; editingEntry.value = null; resetForm(); },
                onError: () => showSnackbar('Failed to update time entry', 'error'),
            },
        );
    } else {
        const s = selectedSubtask.value;
        router.post(
            route('tasks.subtasks.time-entries.store', [
                props.activeWorkspace.id,
                s.space_id,
                s.project_id,
                s.task_id,
                s.id,
            ]),
            {
                started_at: entryForm.value.started_at,
                ended_at: entryForm.value.ended_at,
                is_billable: entryForm.value.is_billable,
            },
            {
                preserveScroll: true,
                onSuccess: () => { showAddEntry.value = false; resetForm(); },
                onError: () => showSnackbar('Failed to log time entry', 'error'),
            },
        );
    }
};

const resetForm = () => {
    entryForm.value = { started_at: '', ended_at: '', is_billable: false };
    selectedSubtask.value = null;
    subtaskSearch.value = '';
};

// Lifecycle
onMounted(() => {
    if (props.runningTimer) {
        elapsedSeconds.value = Math.floor(
            (Date.now() - new Date(props.runningTimer.started_at).getTime()) / 1000,
        );
        startTimerInterval();
    }
});

onUnmounted(stopTimerInterval);

const applyCustom = () => {
    if (!customStart.value || !customEnd.value) return;
    router.reload({ preserveScroll: true, data: { start_date: customStart.value, end_date: customEnd.value }, only: ['entries'] });
};

watch(dateRange, (range) => {
    if (range === 'custom') return; // user applies manually
    let startDate = null;
    let endDate = null;
    const now = new Date();
    if (range === 'today') {
        startDate = endDate = now.toISOString().split('T')[0];
    } else if (range === 'week') {
        const diff = now.getDay() === 0 ? 6 : now.getDay() - 1;
        const mon = new Date(now);
        mon.setDate(now.getDate() - diff);
        startDate = mon.toISOString().split('T')[0];
        endDate = now.toISOString().split('T')[0];
    } else if (range === 'month') {
        startDate = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
        endDate = now.toISOString().split('T')[0];
    }
    // range === 'all': startDate and endDate remain null ? no filter
    router.reload({ preserveScroll: true, data: { start_date: startDate, end_date: endDate }, only: ['entries'] });
});

watch(() => props.runningTimer, (newTimer) => {
    stopTimerInterval();
    if (newTimer?.is_running) {
        elapsedSeconds.value = Math.floor(
            (Date.now() - new Date(newTimer.started_at).getTime()) / 1000,
        );
        startTimerInterval();
    } else {
        elapsedSeconds.value = 0;
    }
});
</script>

<template>

    <Head title="Time Tracking" />

    <MainLayout :workspace="activeWorkspace">
        <div class="h-full bg-[#1E1E1E] overflow-auto">

            <!-- Header -->
            <div class="bg-[#252526] border-b border-gray-800 px-6 py-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-semibold text-white">Time Tracking</h1>
                        <p class="text-sm text-gray-400 mt-1">Track time spent on your tasks</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <v-btn prepend-icon="mdi-chart-bar" variant="tonal" size="small"
                            @click="router.visit(route('workspaces.time-report', activeWorkspace.id))">
                            Report
                        </v-btn>
                        <v-btn prepend-icon="mdi-plus" color="primary" size="small" @click="openAddDialog">
                            Log Time
                        </v-btn>
                    </div>
                </div>
            </div>

            <div class="p-4 md:p-6">
                <v-card v-if="runningTimer" class="mb-5 border border-red-500/30" rounded="lg" variant="tonal"
                    color="error">
                    <v-card-text class="py-3 px-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <v-icon size="20" color="error" class="animate-pulse shrink-0">mdi-timer-sand</v-icon>
                                <div class="min-w-0">
                                    <div class="text-sm font-medium truncate">
                                        {{ runningTimer.subtask?.name ?? 'Unknown task' }}
                                    </div>
                                    <div class="text-xs opacity-70 truncate">
                                        {{ runningTimer.subtask?.task?.name }}
                                        <span v-if="runningTimer.subtask?.task?.project">
                                            &middot; {{ runningTimer.subtask.task.project.name }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="text-xl font-mono tabular-nums tracking-tight">{{ formatRunningTime
                                }}</span>
                                <v-btn color="error" size="small" variant="flat" prepend-icon="mdi-stop"
                                    @click="stopTimer(runningTimer)">
                                    Stop
                                </v-btn>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Stats -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                    <v-card class="pa-4" rounded="lg">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-xs text-gray-400">{{ periodLabel }}</div>
                                <div class="text-2xl font-semibold mt-1">{{ formatDuration(periodStats.total) }}</div>
                            </div>
                            <v-avatar size="36" color="primary" variant="tonal" rounded="lg">
                                <v-icon size="18">mdi-clock-outline</v-icon>
                            </v-avatar>
                        </div>
                    </v-card>
                    <v-card class="pa-4" rounded="lg">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-xs text-gray-400">Billable</div>
                                <div class="text-2xl font-semibold mt-1 text-green-400">{{
                                    formatDuration(periodStats.billable) }}
                                </div>
                            </div>
                            <v-avatar size="36" color="success" variant="tonal" rounded="lg">
                                <v-icon size="18">mdi-cash</v-icon>
                            </v-avatar>
                        </div>
                    </v-card>
                    <v-card class="pa-4" rounded="lg">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-xs text-gray-400">Entries</div>
                                <div class="text-2xl font-semibold mt-1">{{ periodStats.count }}</div>
                            </div>
                            <v-avatar size="36" color="info" variant="tonal" rounded="lg">
                                <v-icon size="18">mdi-playlist-check</v-icon>
                            </v-avatar>
                        </div>
                    </v-card>
                </div>

                <!-- Table card -->
                <v-card rounded="lg" variant="flat">
                    <!-- Search & filter toolbar -->
                    <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <v-text-field v-model="search" prepend-inner-icon="mdi-magnify" label="Search entries?"
                            density="compact" variant="outlined" hide-details clearable class="max-w-xs" />
                        <v-btn-toggle v-model="dateRange" mandatory color="primary" density="compact"
                            variant="outlined">
                            <v-btn value="all" size="small">All</v-btn>
                            <v-btn value="today" size="small">Today</v-btn>
                            <v-btn value="week" size="small">Week</v-btn>
                            <v-btn value="month" size="small">Month</v-btn>
                            <v-btn value="custom" size="small" prepend-icon="mdi-calendar-range">Custom</v-btn>
                        </v-btn-toggle>
                        <template v-if="dateRange === 'custom'">
                            <v-text-field v-model="customStart" type="date" label="From" density="compact"
                                variant="outlined" hide-details style="max-width: 150px" />
                            <span class="text-gray-500 text-xs">?</span>
                            <v-text-field v-model="customEnd" type="date" label="To" density="compact"
                                variant="outlined" hide-details :min="customStart" style="max-width: 150px" />
                            <v-btn color="primary" size="small" variant="flat" @click="applyCustom">Apply</v-btn>
                        </template>
                    </div>

                    <v-divider />

                    <v-data-table :headers="tableHeaders" :items="tableItems" :search="search" :sort-by="sortBy"
                        :items-per-page="15" :no-data-text="`No time entries for ${periodLabel.toLowerCase()}`" hover
                        density="comfortable" class="bg-transparent">
                        <!-- Subtask name -->
                        <template #item.subtask_name="{ item }">
                            <div class="flex items-center gap-2 py-1">
                                <span v-if="item.is_running"
                                    class="w-2 h-2 bg-red-500 rounded-full animate-pulse shrink-0"></span>
                                <button
                                    class="text-sm font-medium hover:text-blue-400 hover:underline text-left truncate transition-colors"
                                    @click="goToTask(item)">
                                    {{ item.subtask_name }}
                                </button>
                            </div>
                        </template>

                        <!-- Task + list -->
                        <template #item.task_name="{ item }">
                            <div class="text-sm truncate max-w-[200px]">
                                {{ item.task_name }}
                                <span v-if="item.project_name" class="text-gray-500">
                                    &middot; {{ item.project_name }}
                                </span>
                            </div>
                        </template>

                        <!-- Start time -->
                        <template #item.started_at="{ item }">
                            <span class="text-sm text-gray-300">{{ formatDateTime(item.started_at) }}</span>
                        </template>

                        <!-- End time -->
                        <template #item.ended_at="{ item }">
                            <span class="text-sm" :class="item.is_running ? 'text-red-400 italic' : 'text-gray-300'">
                                {{ item.is_running ? 'running?' : formatDateTime(item.ended_at) }}
                            </span>
                        </template>

                        <!-- Duration -->
                        <template #item.duration="{ item }">
                            <span class="text-sm font-mono tabular-nums"
                                :class="item.is_running ? 'text-red-400' : 'text-gray-200'">
                                {{ item.is_running ? formatRunningTime : formatDuration(item.duration) }}
                            </span>
                        </template>

                        <!-- Billable -->
                        <template #item.is_billable="{ item }">
                            <v-chip :color="item.is_billable ? 'success' : 'default'"
                                :variant="item.is_billable ? 'tonal' : 'text'" size="small">
                                {{ item.is_billable ? 'Yes' : 'No' }}
                            </v-chip>
                        </template>

                        <!-- Actions -->
                        <template #item.actions="{ item }">
                            <v-btn v-if="item.is_running" icon="mdi-stop" size="x-small" color="error" variant="text"
                                @click="stopTimer(item)" />
                            <v-btn v-else icon="mdi-pencil-outline" size="x-small" variant="text"
                                @click="editEntry(item)" />
                        </template>
                    </v-data-table>
                </v-card>
            </div>

            <!-- Log / Edit Time Dialog -->
            <v-dialog v-model="showAddEntry" max-width="480" :scrim="true">
                <v-card rounded="lg">
                    <v-card-title class="text-base pt-4 px-5 flex items-center gap-2">
                        <v-icon size="20" :color="editingEntry ? 'warning' : 'primary'">
                            {{ editingEntry ? 'mdi-pencil' : 'mdi-clock-plus-outline' }}
                        </v-icon>
                        {{ editingEntry ? 'Edit Time Entry' : 'Log Time' }}
                    </v-card-title>
                    <v-divider />

                    <v-card-text class="px-5 pt-5 pb-3 space-y-4">
                        <!-- Subtask picker ? new entries only -->
                        <v-autocomplete v-if="!editingEntry" v-model="selectedSubtask" v-model:search="subtaskSearch"
                            :items="subtasks" item-title="name" return-object label="Subtask"
                            placeholder="Search for a subtask?" variant="outlined" density="compact" hide-details
                            clearable no-data-text="No active subtasks found" prepend-inner-icon="mdi-magnify">
                            <template #item="{ item, props: iProps }">
                                <v-list-item v-bind="iProps"
                                    :subtitle="`${item.raw.task_name} · ${item.raw.project_name}`" />
                            </template>
                        </v-autocomplete>

                        <!-- Read-only context for edits -->
                        <v-card v-else variant="tonal" rounded="lg" class="pa-3">
                            <div class="flex items-center gap-2">
                                <v-icon size="16" color="grey">mdi-checkbox-marked-circle-outline</v-icon>
                                <div class="min-w-0">
                                    <div class="text-sm font-medium truncate">
                                        {{ editingEntry.subtask?.name ?? 'Unknown subtask' }}
                                    </div>
                                    <div class="text-xs text-gray-500 mt-0.5 truncate">{{
                                        editingEntry.subtask?.task?.name }}</div>
                                </div>
                            </div>
                        </v-card>

                        <!-- Time range -->
                        <div class="grid grid-cols-2 gap-3">
                            <v-text-field v-model="entryForm.started_at" label="Start" type="datetime-local"
                                variant="outlined" density="compact" hide-details />
                            <v-text-field v-model="entryForm.ended_at" label="End" type="datetime-local"
                                variant="outlined" density="compact" hide-details />
                        </div>

                        <v-checkbox v-model="entryForm.is_billable" label="Billable" hide-details density="compact"
                            color="success" />
                    </v-card-text>

                    <v-divider />
                    <v-card-actions class="px-5 py-3">
                        <v-spacer />
                        <v-btn variant="text" @click="showAddEntry = false; editingEntry = null; resetForm()">
                            Cancel
                        </v-btn>
                        <v-btn color="primary" :disabled="!canSave" @click="saveEntry">
                            {{ editingEntry ? 'Save Changes' : 'Log Time' }}
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

        </div>
    </MainLayout>
</template>
