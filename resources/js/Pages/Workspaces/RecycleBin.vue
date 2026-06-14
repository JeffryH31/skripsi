<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { formatDateLong as formatDate } from '@/utils/date';
import { useSnackbar } from '@/composables/useSnackbar';

const { showSnackbar } = useSnackbar();

const props = defineProps({
    workspace: { type: Object, default: null },
    trash: { type: Object, default: null },
    canRestore: { type: Boolean, default: false },
});

const activeTab = ref('tasks');
const restoringIds = ref(new Set());
const searchQuery = ref('');
const sortBy = ref('deleted_desc');

const sortOptions = [
    { title: 'Newest Deleted', value: 'deleted_desc' },
    { title: 'Oldest Deleted', value: 'deleted_asc' },
    { title: 'Name A-Z', value: 'name_asc' },
    { title: 'Name Z-A', value: 'name_desc' },
];

const totals = computed(() => ({
    projects: props.trash?.projects?.length || 0,
    tasks: props.trash?.tasks?.length || 0,
    subtasks: props.trash?.subtasks?.length || 0,
    timeEntries: props.trash?.time_entries?.length || 0,
}));

const restoreItem = (type, id) => {
    if (restoringIds.value.has(`${type}-${id}`)) return;
    restoringIds.value.add(`${type}-${id}`);

    router.post(route('workspaces.recycle-bin.restore', props.workspace.id), { type, id }, {
        preserveScroll: true,
        onError: () => showSnackbar('Failed to restore item', 'error'),
        onFinish: () => { restoringIds.value.delete(`${type}-${id}`); },
    });
};

const isRestoring = (type, id) => restoringIds.value.has(`${type}-${id}`);


const getItemName = (item, type) => {
    if (type === 'time_entries') {
        return item.subtask?.name || item.user?.name || '';
    }
    return item.name || '';
};

const getItemContext = (item, type) => {
    if (type === 'tasks') {
        return `${item.project?.name || ''} ${item.project?.space?.name || ''}`.trim();
    }
    if (type === 'subtasks') {
        return item.task?.name || '';
    }
    if (type === 'projects') {
        return item.space?.name || '';
    }
    if (type === 'time_entries') {
        return `${item.user?.name || ''} ${item.subtask?.name || ''}`.trim();
    }
    return '';
};

const normalizeDeletedAt = (item) => {
    const ts = Date.parse(item.deleted_at || '');
    return Number.isNaN(ts) ? 0 : ts;
};

const sortedFilteredItems = computed(() => {
    const type = activeTab.value;
    const source = props.trash?.[type] || [];
    const query = searchQuery.value.trim().toLowerCase();

    let result = [...source];

    if (query) {
        result = result.filter((item) => {
            const haystack = `${getItemName(item, type)} ${getItemContext(item, type)}`.toLowerCase();
            return haystack.includes(query);
        });
    }

    const byName = (a, b) => getItemName(a, type).localeCompare(getItemName(b, type));
    const byDeletedAt = (a, b) => normalizeDeletedAt(a) - normalizeDeletedAt(b);

    switch (sortBy.value) {
        case 'deleted_asc':
            result.sort(byDeletedAt);
            break;
        case 'name_asc':
            result.sort(byName);
            break;
        case 'name_desc':
            result.sort((a, b) => byName(b, a));
            break;
        case 'deleted_desc':
        default:
            result.sort((a, b) => byDeletedAt(b, a));
            break;
    }

    return result;
});
</script>

<template>
    <MainLayout :title="`${workspace?.name} Recycle Bin`">
        <div class="p-4 md:p-6">
            <h1 class="text-2xl font-bold mb-1">Recycle Bin</h1>
            <p class="text-gray-400 text-sm mb-4">Restore accidentally deleted items.</p>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
                <v-card class="pa-3">
                    <div class="text-xs text-gray-400">Projects</div>
                    <div class="text-xl font-semibold">{{ totals.projects }}</div>
                </v-card>
                <v-card class="pa-3">
                    <div class="text-xs text-gray-400">Tasks</div>
                    <div class="text-xl font-semibold">{{ totals.tasks }}</div>
                </v-card>
                <v-card class="pa-3">
                    <div class="text-xs text-gray-400">Subtasks</div>
                    <div class="text-xl font-semibold">{{ totals.subtasks }}</div>
                </v-card>
                <v-card class="pa-3">
                    <div class="text-xs text-gray-400">Time Entries</div>
                    <div class="text-xl font-semibold">{{ totals.timeEntries }}</div>
                </v-card>
            </div>

            <v-tabs v-model="activeTab" class="mb-3">
                <v-tab value="tasks">Tasks</v-tab>
                <v-tab value="subtasks">Subtasks</v-tab>
                <v-tab value="projects">Projects</v-tab>
                <v-tab value="time_entries">Time Entries</v-tab>
            </v-tabs>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                <v-text-field v-model="searchQuery" density="compact" variant="outlined" hide-details
                    prepend-inner-icon="mdi-magnify" label="Search deleted items" />
                <v-select v-model="sortBy" :items="sortOptions" item-title="title" item-value="value" density="compact"
                    variant="outlined" hide-details label="Sort by" />
                <v-chip variant="outlined" class="justify-self-start md:justify-self-end self-center">
                    {{ sortedFilteredItems.length }} item(s)
                </v-chip>
            </div>

            <v-window v-model="activeTab">
                <v-window-item value="tasks">
                    <v-card>
                        <v-table>
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>List / Space</th>
                                    <th>Deleted At</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="task in sortedFilteredItems" :key="task.id">
                                    <td>{{ task.name }}</td>
                                    <td>{{ task.project?.name }} / {{ task.project?.space?.name }}</td>
                                    <td>{{ formatDate(task.deleted_at) }}</td>
                                    <td><v-btn v-if="canRestore" size="small" color="primary" :loading="isRestoring('task', task.id)"
                                            @click="restoreItem('task', task.id)">Restore</v-btn></td>
                                </tr>
                                <tr v-if="sortedFilteredItems.length === 0">
                                    <td colspan="4" class="text-center text-gray-500 py-6">No deleted tasks match your
                                        filter.</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>
                </v-window-item>

                <v-window-item value="subtasks">
                    <v-card>
                        <v-table>
                            <thead>
                                <tr>
                                    <th>Subtask</th>
                                    <th>Task</th>
                                    <th>Deleted At</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="subtask in sortedFilteredItems" :key="subtask.id">
                                    <td>{{ subtask.name }}</td>
                                    <td>{{ subtask.task?.name }}</td>
                                    <td>{{ formatDate(subtask.deleted_at) }}</td>
                                    <td><v-btn v-if="canRestore" size="small" color="primary" :loading="isRestoring('subtask', subtask.id)"
                                            @click="restoreItem('subtask', subtask.id)">Restore</v-btn></td>
                                </tr>
                                <tr v-if="sortedFilteredItems.length === 0">
                                    <td colspan="4" class="text-center text-gray-500 py-6">No deleted subtasks match
                                        your filter.</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>
                </v-window-item>

                <v-window-item value="projects">
                    <v-card>
                        <v-table>
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Space</th>
                                    <th>Deleted At</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="list in sortedFilteredItems" :key="list.id">
                                    <td>{{ list.name }}</td>
                                    <td>{{ list.space?.name }}</td>
                                    <td>{{ formatDate(list.deleted_at) }}</td>
                                    <td><v-btn v-if="canRestore" size="small" color="primary" :loading="isRestoring('list', list.id)"
                                            @click="restoreItem('list', list.id)">Restore</v-btn></td>
                                </tr>
                                <tr v-if="sortedFilteredItems.length === 0">
                                        <td colspan="4" class="text-center text-gray-500 py-6">No deleted projects match your
                                        filter.</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>
                </v-window-item>

                <v-window-item value="time_entries">
                    <v-card>
                        <v-table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Subtask</th>
                                    <th>Minutes</th>
                                    <th>Deleted At</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="entry in sortedFilteredItems" :key="entry.id">
                                    <td>{{ entry.user?.name }}</td>
                                    <td>{{ entry.subtask?.name }}</td>
                                    <td>{{ entry.duration }}</td>
                                    <td>{{ formatDate(entry.deleted_at) }}</td>
                                    <td><v-btn v-if="canRestore" size="small" color="primary" :loading="isRestoring('time_entry', entry.id)"
                                            @click="restoreItem('time_entry', entry.id)">Restore</v-btn></td>
                                </tr>
                                <tr v-if="sortedFilteredItems.length === 0">
                                    <td colspan="5" class="text-center text-gray-500 py-6">No deleted time entries match
                                        your filter.</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>
                </v-window-item>
            </v-window>
        </div>
    </MainLayout>
</template>
