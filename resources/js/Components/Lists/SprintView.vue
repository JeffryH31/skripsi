<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useSnackbar } from '@/composables/useSnackbar';

const props = defineProps({
    workspace: { type: Object, required: true },
    space: { type: Object, required: true },
    list: { type: Object, required: true },
    sprints: { type: Array, default: () => [] },
    canManageTaskStructure: { type: Boolean, default: false },
    selectedSprintId: { type: Number, default: null },
});

const emit = defineEmits(['sprint-open', 'sprint-saved']);

const { confirm: confirmDialog } = useConfirmDialog();
const { showSnackbar } = useSnackbar();

// Filters / sort
const sprintSearchQuery = ref('');
const sprintFilterState = ref('all');
const sprintSortBy = ref('start_desc');

const sprintStateOptions = [
    { title: 'All States', value: 'all' },
    { title: 'Active', value: 'active' },
    { title: 'Planned', value: 'planned' },
    { title: 'Completed', value: 'completed' },
];

const sprintSortOptions = [
    { title: 'Start Date (Newest)', value: 'start_desc' },
    { title: 'Start Date (Oldest)', value: 'start_asc' },
    { title: 'End Date (Soonest)', value: 'end_asc' },
    { title: 'End Date (Latest)', value: 'end_desc' },
    { title: 'Most Tasks', value: 'tasks_desc' },
    { title: 'Name A-Z', value: 'name_asc' },
];

// Form state
const showCreateSprint = ref(false);
const editingSprintId = ref(null);
const isSavingSprint = ref(false);
const sprintForm = ref({
    list_id: props.list?.id || null,
    name: '',
    goal: '',
    start_date: '',
    end_date: '',
});
const sprintFormErrors = ref({ name: '', start_date: '', end_date: '' });

watch(
    () => props.list?.id,
    (id) => {
        if (!editingSprintId.value) sprintForm.value.list_id = id || null;
    },
    { immediate: true }
);

// Sprint state helpers
const isSprintActive = (sprint) => !!sprint?.is_active;

const isSprintCompleted = (sprint) => {
    if (!sprint?.end_date) return false;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const end = new Date(sprint.end_date);
    return !sprint?.is_active && today > end;
};

const formatSprintDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const getSprintRemainingDays = (sprint) => {
    if (!sprint?.end_date) return 0;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const end = new Date(sprint.end_date);
    end.setHours(0, 0, 0, 0);
    const diff = end.getTime() - today.getTime();
    return diff < 0 ? 0 : Math.ceil(diff / (1000 * 60 * 60 * 24));
};

const getSprintDurationDays = (sprint) => {
    if (!sprint?.start_date || !sprint?.end_date) return 0;
    const start = new Date(sprint.start_date);
    const end = new Date(sprint.end_date);
    start.setHours(0, 0, 0, 0);
    end.setHours(0, 0, 0, 0);
    const diff = end.getTime() - start.getTime();
    if (diff <= 0) return 1;
    return Math.ceil(diff / (1000 * 60 * 60 * 24));
};

const getSprintProgressPercent = (sprint) => {
    if (isSprintCompleted(sprint)) return 100;
    if (!sprint?.start_date || !sprint?.end_date) return 0;
    const start = new Date(sprint.start_date);
    const end = new Date(sprint.end_date);
    const today = new Date();
    start.setHours(0, 0, 0, 0);
    end.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);
    if (today <= start) return isSprintActive(sprint) ? 1 : 0;
    if (today >= end) return 100;
    const total = Math.max(end.getTime() - start.getTime(), 1);
    const elapsed = today.getTime() - start.getTime();
    return Math.min(100, Math.max(0, Math.round((elapsed / total) * 100)));
};

const getSprintStateMeta = (sprint) => {
    if (isSprintActive(sprint)) {
        return { label: 'Active', color: 'success', icon: 'mdi-rocket-launch-outline' };
    }
    if (isSprintCompleted(sprint)) {
        return { label: 'Completed', color: 'secondary', icon: 'mdi-check-circle-outline' };
    }
    return { label: 'Planned', color: 'info', icon: 'mdi-calendar-clock-outline' };
};

// Derived
const activeSprint = computed(() => {
    const sprints = props.sprints || [];
    const explicit = sprints.find(isSprintActive);
    if (explicit) return explicit;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return (
        sprints.find((s) => {
            if (!s.start_date || !s.end_date) return false;
            const start = new Date(s.start_date);
            const end = new Date(s.end_date);
            start.setHours(0, 0, 0, 0);
            end.setHours(23, 59, 59, 999);
            return today >= start && today <= end;
        }) || null
    );
});

const sprintSummary = computed(() => {
    const all = props.sprints || [];
    const active = all.filter(isSprintActive).length;
    const completed = all.filter(isSprintCompleted).length;
    const planned = all.length - active - completed;
    const totalTasks = all.reduce((sum, s) => sum + (s.subtasks_count || 0), 0);
    return { total: all.length, active, planned, completed, totalTasks };
});

const filteredSprints = computed(() => {
    let result = [...(props.sprints || [])];
    const q = sprintSearchQuery.value.trim().toLowerCase();

    if (q) {
        result = result.filter((s) =>
            `${s.name || ''} ${s.goal || ''}`.toLowerCase().includes(q)
        );
    }
    if (sprintFilterState.value !== 'all') {
        result = result.filter((s) => {
            if (sprintFilterState.value === 'active') return isSprintActive(s);
            if (sprintFilterState.value === 'completed') return isSprintCompleted(s);
            if (sprintFilterState.value === 'planned')
                return !isSprintActive(s) && !isSprintCompleted(s);
            return true;
        });
    }

    const byStart = (a, b) => new Date(a.start_date) - new Date(b.start_date);
    const byEnd = (a, b) => new Date(a.end_date) - new Date(b.end_date);

    switch (sprintSortBy.value) {
        case 'start_asc': result.sort(byStart); break;
        case 'end_asc': result.sort(byEnd); break;
        case 'end_desc': result.sort((a, b) => byEnd(b, a)); break;
        case 'tasks_desc': result.sort((a, b) => (b.subtasks_count || 0) - (a.subtasks_count || 0)); break;
        case 'name_asc': result.sort((a, b) => (a.name || '').localeCompare(b.name || '')); break;
        default: result.sort((a, b) => byStart(b, a));
    }
    return result;
});

defineExpose({ filteredSprints });

// Form actions
const normalizeDateInput = (value) => {
    if (!value) return '';
    if (typeof value === 'string') return value.split('T')[0];
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const resetSprintForm = () => {
    sprintForm.value = {
        list_id: props.list?.id || null,
        name: '',
        goal: '',
        start_date: '',
        end_date: '',
    };
    sprintFormErrors.value = { name: '', start_date: '', end_date: '' };
    editingSprintId.value = null;
};

const openCreateSprintDialog = () => {
    resetSprintForm();
    showCreateSprint.value = true;
};

const editSprint = (sprint) => {
    editingSprintId.value = sprint.id;
    sprintForm.value = {
        list_id: sprint.project_id || props.list?.id || null,
        name: sprint.name || '',
        goal: sprint.goal || '',
        start_date: normalizeDateInput(sprint.start_date),
        end_date: normalizeDateInput(sprint.end_date),
    };
    sprintFormErrors.value = { name: '', start_date: '', end_date: '' };
    showCreateSprint.value = true;
};

const validateSprintForm = () => {
    let isValid = true;
    sprintFormErrors.value = { name: '', start_date: '', end_date: '' };

    if (!sprintForm.value.name?.trim()) {
        sprintFormErrors.value.name = 'Sprint name is required';
        isValid = false;
    }
    if (!sprintForm.value.start_date) {
        sprintFormErrors.value.start_date = 'Start date is required';
        isValid = false;
    }
    if (!sprintForm.value.end_date) {
        sprintFormErrors.value.end_date = 'End date is required';
        isValid = false;
    }
    if (
        sprintForm.value.start_date &&
        sprintForm.value.end_date &&
        new Date(sprintForm.value.end_date) <= new Date(sprintForm.value.start_date)
    ) {
        sprintFormErrors.value.end_date = 'End date must be after start date';
        isValid = false;
    }
    return isValid;
};

const applySprintErrors = (errors) => {
    sprintFormErrors.value = {
        name: errors.name || '',
        start_date: errors.start_date || '',
        end_date: errors.end_date || '',
    };
};

const saveSprint = () => {
    if (isSavingSprint.value) return;

    if (!validateSprintForm()) {
        showSnackbar('Please fill in required fields correctly.', 'error');
        return;
    }
    if (!sprintForm.value.list_id) {
        showSnackbar('No active project selected.', 'error');
        return;
    }

    isSavingSprint.value = true;
    const payload = {
        list_id: sprintForm.value.list_id,
        name: sprintForm.value.name.trim(),
        goal: sprintForm.value.goal?.trim() || '',
        start_date: sprintForm.value.start_date,
        end_date: sprintForm.value.end_date,
    };

    const onSuccess = () => {
        showCreateSprint.value = false;
        emit('sprint-saved');
    };
    const onError = (errors) => applySprintErrors(errors);
    const onFinish = () => {
        isSavingSprint.value = false;
    };

    if (editingSprintId.value) {
        router.patch(
            route('sprints.update', [props.workspace.id, props.space.id, editingSprintId.value]),
            payload,
            { preserveScroll: true, onSuccess, onError, onFinish }
        );
        return;
    }

    router.post(route('sprints.store', [props.workspace.id, props.space.id]), payload, {
        preserveScroll: true,
        onSuccess,
        onError,
        onFinish,
    });
};

const startSprint = (sprint) => {
    router.post(
        route('sprints.start', [props.workspace.id, props.space.id, sprint.id]),
        {},
        { preserveScroll: true, onSuccess: () => emit('sprint-saved') }
    );
};

const completeSprint = async (sprint) => {
    const ok = await confirmDialog(
        'Mark this sprint as complete? Incomplete tasks will remain in the sprint.',
        'Complete Sprint'
    );
    if (!ok) return;
    router.post(
        route('sprints.complete', [props.workspace.id, props.space.id, sprint.id]),
        {},
        { preserveScroll: true, onSuccess: () => emit('sprint-saved') }
    );
};

const deleteSprint = async (sprint) => {
    const ok = await confirmDialog(
        'Delete this sprint? All attached subtasks will be moved back to backlog.',
        'Delete Sprint'
    );
    if (!ok) return;
    router.delete(route('sprints.destroy', [props.workspace.id, props.space.id, sprint.id]), {
        preserveScroll: true,
        onSuccess: () => emit('sprint-saved'),
    });
};

const openSprint = (sprint) => emit('sprint-open', sprint);
</script>

<template>
    <div class="sprint-view">
        <div class="sprint-summary-grid">
            <v-card class="sprint-summary-card" variant="outlined">
                <div class="sprint-summary-label">Active Sprint</div>
                <div class="sprint-summary-value">{{ activeSprint?.name || 'None' }}</div>
            </v-card>
            <v-card class="sprint-summary-card" variant="outlined">
                <div class="sprint-summary-label">Total Sprints</div>
                <div class="sprint-summary-value">{{ sprintSummary.total }}</div>
            </v-card>
            <v-card class="sprint-summary-card" variant="outlined">
                <div class="sprint-summary-label">Completed</div>
                <div class="sprint-summary-value">{{ sprintSummary.completed }}</div>
            </v-card>
            <v-card class="sprint-summary-card" variant="outlined">
                <div class="sprint-summary-label">Total Tasks</div>
                <div class="sprint-summary-value">{{ sprintSummary.totalTasks }}</div>
            </v-card>
        </div>

        <div class="sprint-header-row">
            <v-text-field v-model="sprintSearchQuery" placeholder="Search sprint..."
                prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details
                class="sprint-search" />

            <v-select v-model="sprintFilterState" :items="sprintStateOptions" item-title="title"
                item-value="value" label="Filter status" density="compact" variant="outlined" hide-details
                bg-color="#1e1e1e" class="sprint-select" />

            <v-select v-model="sprintSortBy" :items="sprintSortOptions" item-title="title" item-value="value"
                label="Sort by" density="compact" variant="outlined" hide-details bg-color="#1e1e1e"
                class="sprint-select" />

            <v-btn v-if="canManageTaskStructure" color="primary" prepend-icon="mdi-plus"
                @click="openCreateSprintDialog">
                Create Sprint
            </v-btn>
        </div>

        <div v-if="!filteredSprints.length" class="sprint-empty-state">
            <v-icon size="44" class="mb-2">mdi-calendar-clock-outline</v-icon>
            <div class="text-subtitle-1">No sprint found</div>
            <div class="text-sm text-gray-500">Try changing filter/search or create a new sprint.</div>
        </div>

        <div v-else class="sprint-grid">
            <v-card v-for="sprint in filteredSprints" :key="sprint.id" class="sprint-card" variant="outlined"
                rounded="lg" :class="{ 'sprint-card--selected': selectedSprintId === sprint.id }"
                @click="openSprint(sprint)">
                <div class="sprint-card-accent"
                    :class="`sprint-card-accent--${getSprintStateMeta(sprint).label.toLowerCase()}`" />

                <v-card-title class="sprint-card-title-row">
                    <div class="sprint-card-title-main">
                        <v-icon size="16" class="sprint-card-title-icon">mdi-run-fast</v-icon>
                        <span class="truncate">{{ sprint.name }}</span>
                    </div>
                    <div class="flex items-center gap-2" @click.stop>
                        <v-chip :color="getSprintStateMeta(sprint).color" size="small" class="sprint-state-chip">
                            <v-icon start size="14">{{ getSprintStateMeta(sprint).icon }}</v-icon>
                            {{ getSprintStateMeta(sprint).label }}
                        </v-chip>
                        <v-menu v-if="canManageTaskStructure">
                            <template #activator="{ props: menuProps }">
                                <v-btn v-bind="menuProps" icon="mdi-dots-vertical" size="x-small" variant="text" />
                            </template>
                            <v-card color="surface">
                                <v-list density="compact">
                                    <v-list-item v-if="!isSprintActive(sprint) && !isSprintCompleted(sprint)"
                                        prepend-icon="mdi-rocket-launch-outline" title="Start Sprint"
                                        class="text-success" @click="startSprint(sprint)" />
                                    <v-list-item v-if="isSprintActive(sprint)"
                                        prepend-icon="mdi-check-circle-outline" title="Complete Sprint"
                                        class="text-info" @click="completeSprint(sprint)" />
                                    <v-list-item prepend-icon="mdi-pencil" title="Edit Sprint"
                                        @click="editSprint(sprint)" />
                                    <v-list-item prepend-icon="mdi-delete" title="Delete Sprint" class="text-error"
                                        @click="deleteSprint(sprint)" />
                                </v-list>
                            </v-card>
                        </v-menu>
                    </div>
                </v-card-title>

                <v-card-text class="sprint-card-content">
                    <div class="sprint-goal-text">
                        {{ sprint.goal || 'No sprint goal provided.' }}
                    </div>

                    <div class="sprint-date-pill">
                        <v-icon size="14">mdi-calendar-range</v-icon>
                        <span>{{ formatSprintDate(sprint.start_date) }}</span>
                        <span class="sprint-date-separator">to</span>
                        <span>{{ formatSprintDate(sprint.end_date) }}</span>
                    </div>

                    <div class="sprint-metrics-grid">
                        <div class="sprint-metric-item">
                            <div class="sprint-metric-label">Tasks</div>
                            <div class="sprint-metric-value">{{ sprint.subtasks_count || 0 }}</div>
                        </div>
                        <div class="sprint-metric-item">
                            <div class="sprint-metric-label">Days Left</div>
                            <div class="sprint-metric-value">{{ getSprintRemainingDays(sprint) }}</div>
                        </div>
                        <div class="sprint-metric-item">
                            <div class="sprint-metric-label">Duration</div>
                            <div class="sprint-metric-value">{{ getSprintDurationDays(sprint) }}d</div>
                        </div>
                        <div class="sprint-metric-item">
                            <div class="sprint-metric-label">Progress</div>
                            <div class="sprint-metric-value">{{ getSprintProgressPercent(sprint) }}%</div>
                        </div>
                    </div>

                    <div class="sprint-progress-block">
                        <v-progress-linear :model-value="getSprintProgressPercent(sprint)" height="8" rounded
                            color="primary" bg-color="#2b3140" />
                    </div>
                </v-card-text>
            </v-card>
        </div>

        <!-- Create / Edit dialog -->
        <v-dialog v-model="showCreateSprint" max-width="620">
            <v-card color="surface">
                <v-card-title>{{ editingSprintId ? 'Edit Sprint' : 'Create Sprint' }}</v-card-title>
                <v-card-text>
                    <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                        Project: {{ list?.name || 'Unknown' }}
                    </v-alert>

                    <v-text-field v-model="sprintForm.name" label="Sprint Name" variant="outlined" density="compact"
                        class="mb-3" bg-color="#1e1e1e" :error-messages="sprintFormErrors.name" required />

                    <v-textarea v-model="sprintForm.goal" label="Sprint Goal (Optional)" variant="outlined"
                        density="compact" rows="3" class="mb-3" bg-color="#1e1e1e" />

                    <div class="grid grid-cols-2 gap-3">
                        <v-text-field v-model="sprintForm.start_date" type="date" label="Start Date"
                            variant="outlined" density="compact" bg-color="#1e1e1e"
                            :error-messages="sprintFormErrors.start_date" required />
                        <v-text-field v-model="sprintForm.end_date" type="date" label="End Date" variant="outlined"
                            density="compact" bg-color="#1e1e1e" :error-messages="sprintFormErrors.end_date"
                            required />
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateSprint = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="isSavingSprint" @click="saveSprint">
                        {{ editingSprintId ? 'Update' : 'Create' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<style scoped>
.sprint-view {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 24px;
    height: 100%;
    overflow: auto;
}

.sprint-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.sprint-summary-card {
    padding: 14px;
    background: #1f232b;
    border-color: #2f3542;
}

.sprint-summary-label {
    color: #95a1b3;
    font-size: 12px;
}

.sprint-summary-value {
    color: #f5f7fb;
    font-size: 22px;
    font-weight: 700;
    margin-top: 4px;
}

.sprint-header-row {
    display: grid;
    grid-template-columns: minmax(180px, 1.3fr) minmax(150px, 0.9fr) minmax(150px, 0.9fr) auto;
    align-items: center;
    gap: 12px;
}

.sprint-search,
.sprint-select {
    min-width: 0;
}

.sprint-select :deep(.v-field) {
    background-color: #1e1e1e !important;
}

.sprint-select :deep(.v-field__overlay) {
    background-color: #1e1e1e !important;
    opacity: 1 !important;
}

.sprint-select :deep(.v-field__input),
.sprint-select :deep(.v-field__append-inner) {
    color: #d7dce5 !important;
}

.sprint-empty-state {
    border: 1px dashed #3b3f46;
    border-radius: 12px;
    min-height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    color: #9aa4b2;
    gap: 4px;
}

.sprint-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.sprint-card {
    position: relative;
    overflow: hidden;
    background: linear-gradient(165deg, #212735 0%, #1b212d 100%);
    border-color: #313a4c;
    cursor: pointer;
    transition: border-color 0.18s, box-shadow 0.18s, transform 0.18s;
}

.sprint-card:hover {
    border-color: #45516a;
    box-shadow: 0 8px 22px rgba(9, 12, 20, 0.42);
    transform: translateY(-2px);
}

.sprint-card--selected {
    border-color: #5e9dff;
    box-shadow: 0 0 0 1px rgba(94, 157, 255, 0.45), 0 8px 26px rgba(10, 40, 90, 0.35);
}

.sprint-card-accent {
    height: 4px;
    width: 100%;
}

.sprint-card-accent--active {
    background: linear-gradient(90deg, #22c55e, #4ade80);
}

.sprint-card-accent--planned {
    background: linear-gradient(90deg, #38bdf8, #60a5fa);
}

.sprint-card-accent--completed {
    background: linear-gradient(90deg, #a78bfa, #818cf8);
}

.sprint-card-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-bottom: 8px;
}

.sprint-card-title-main {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: #f5f7fb;
}

.sprint-card-title-icon {
    color: #8fb7ff;
}

.sprint-state-chip {
    font-weight: 600;
}

.sprint-goal-text {
    color: #b6c0cf;
    font-size: 13px;
    line-height: 1.45;
    min-height: 40px;
    margin-bottom: 12px;
    display: -webkit-box;
    line-clamp: 2;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.sprint-card-content {
    padding-top: 0;
}

.sprint-date-pill {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #c4ccda;
    font-size: 12px;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    padding: 6px 8px;
    margin-bottom: 12px;
}

.sprint-date-separator {
    color: #8f99aa;
    font-weight: 500;
}

.sprint-metrics-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.sprint-metric-item {
    border: 1px solid #364157;
    background: rgba(28, 34, 46, 0.72);
    border-radius: 10px;
    padding: 8px 10px;
}

.sprint-metric-label {
    font-size: 11px;
    color: #8f9ab0;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.sprint-metric-value {
    font-size: 18px;
    line-height: 1.15;
    color: #ecf2ff;
    font-weight: 700;
    margin-top: 2px;
}

.sprint-progress-block {
    margin-top: 12px;
}

@media (max-width: 980px) {
    .sprint-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sprint-header-row {
        grid-template-columns: 1fr;
    }
}
</style>
