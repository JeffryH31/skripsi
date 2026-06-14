<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useSnackbar } from '@/composables/useSnackbar';
import { formatSeconds as formatDuration } from '@/utils/duration';

const { confirm: confirmDialog } = useConfirmDialog();
const { showSnackbar } = useSnackbar();

const props = defineProps({
    task: { type: Object, default: null },       // localTask (time_entries, time_estimate, time_spent)
    workspace: { type: Object, default: null },
    space: { type: Object, default: null },
    list: { type: Object, default: null },
    parentTask: { type: Object, default: null },
});


const formatTimeEstimate = (minutes) => {
    if (!minutes) return 'Not set';
    const h = minutes / 60;
    return h % 1 === 0 ? `${h}h` : `${h.toFixed(1)}h`;
};

const getSpentPercentage = (spentMinutes, estimateMinutes) => {
    if (!estimateMinutes) return null;
    return Math.round(((spentMinutes || 0) / estimateMinutes) * 100);
};

const newEntry = ref({
    startDate: '',
    startTime: '',
    endDate: '',
    endTime: '',
});

const pad2 = (n) => String(n).padStart(2, '0');

// Normalize picker output across adapters (string, Date, array, or date-like objects)
const normalizeDateValue = (value) => {
    const raw = Array.isArray(value) ? value[0] : value;
    if (!raw) return '';

    if (typeof raw === 'string') {
        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
        const d = new Date(raw);
        if (Number.isNaN(d.getTime())) return '';
        return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
    }

    if (raw instanceof Date) {
        if (Number.isNaN(raw.getTime())) return '';
        return `${raw.getFullYear()}-${pad2(raw.getMonth() + 1)}-${pad2(raw.getDate())}`;
    }

    if (raw && typeof raw.toISODate === 'function') {
        return raw.toISODate() || '';
    }

    return '';
};

const normalizeTimeValue = (value) => {
    const raw = Array.isArray(value) ? value[0] : value;
    if (!raw) return '';

    if (typeof raw === 'string') {
        const m = raw.match(/^(\d{1,2}):(\d{2})/);
        if (!m) return '';
        return `${pad2(m[1])}:${m[2]}`;
    }

    if (raw instanceof Date) {
        if (Number.isNaN(raw.getTime())) return '';
        return `${pad2(raw.getHours())}:${pad2(raw.getMinutes())}`;
    }

    if (raw && typeof raw === 'object' && Number.isFinite(raw.hours) && Number.isFinite(raw.minutes)) {
        return `${pad2(raw.hours)}:${pad2(raw.minutes)}`;
    }

    if (raw && typeof raw.toFormat === 'function') {
        return raw.toFormat('HH:mm');
    }

    return '';
};

const sortedTimeEntries = computed(() => {
    const entries = Array.isArray(props.task?.time_entries) ? [...props.task.time_entries] : [];
    return entries.sort((a, b) => {
        const aTime = new Date(a.started_at || a.created_at || 0).getTime();
        const bTime = new Date(b.started_at || b.created_at || 0).getTime();
        return bTime - aTime;
    });
});

const buildDateTime = (dateValue, timeValue) => {
    const normalizedDate = normalizeDateValue(dateValue);
    const normalizedTime = normalizeTimeValue(timeValue);
    if (!normalizedDate || !normalizedTime) return null;
    const dt = new Date(`${normalizedDate}T${normalizedTime}:00`);
    return Number.isNaN(dt.getTime()) ? null : dt;
};

const normalizedStartDate = computed(() => normalizeDateValue(newEntry.value.startDate));
const normalizedEndDate = computed(() => normalizeDateValue(newEntry.value.endDate));
const normalizedStartTime = computed(() => normalizeTimeValue(newEntry.value.startTime));
const normalizedEndTime = computed(() => normalizeTimeValue(newEntry.value.endTime));

const canLogEntry = computed(() => {
    return !!(normalizedStartDate.value && normalizedStartTime.value && normalizedEndDate.value && normalizedEndTime.value);
});

const startDateTime = computed(() => buildDateTime(newEntry.value.startDate, newEntry.value.startTime));
const endDateTime = computed(() => buildDateTime(newEntry.value.endDate, newEntry.value.endTime));

const durationMinutes = computed(() => {
    if (!startDateTime.value || !endDateTime.value) return null;
    return Math.round((endDateTime.value.getTime() - startDateTime.value.getTime()) / 60000);
});

const durationPreview = computed(() => {
    if (!durationMinutes.value || durationMinutes.value <= 0) return null;
    const h = Math.floor(durationMinutes.value / 60);
    const m = durationMinutes.value % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
});

const addEntry = () => {
    if (!startDateTime.value || !endDateTime.value) return;

    const start = startDateTime.value;
    const end = endDateTime.value;

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        showSnackbar('Start and end time are required.', 'error');
        return;
    }

    if (end <= start) {
        showSnackbar('End time must be after start time.', 'error');
        return;
    }

    if ((durationMinutes.value || 0) <= 0) {
        showSnackbar('End time must be after start time.', 'error');
        return;
    }

    if ((durationMinutes.value || 0) > 1440) {
        showSnackbar('Duration cannot exceed 24 hours.', 'error');
        return;
    }

    router.post(
        route('tasks.subtasks.time-entries.store', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, props.task.id]),
        {
            started_at: `${normalizedStartDate.value} ${normalizedStartTime.value}:00`,
            ended_at: `${normalizedEndDate.value} ${normalizedEndTime.value}:00`,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newEntry.value = {
                    startDate: '',
                    startTime: '',
                    endDate: '',
                    endTime: '',
                };
                router.reload({ only: ['task', 'tasksByStatus'] });
            },
            onError: () => showSnackbar('Failed to log time entry', 'error'),
        }
    );
};

const deleteEntry = async (entryId) => {
    if (!await confirmDialog('Delete this time entry?', 'Delete Time Entry')) return;
    router.delete(
        route('tasks.subtasks.time-entries.destroy', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, props.task.id, entryId]),
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['task', 'tasksByStatus'] });
            },
            onError: () => showSnackbar('Failed to delete time entry', 'error'),
        }
    );
};
</script>

<template>
    <div class="time-tab pa-5">
        <!-- Summary -->
        <div class="time-summary mb-5">
            <div class="time-summary-card">
                <div class="time-summary-icon"
                    :class="(task.time_spent || 0) > task.time_estimate ? 'bg-error-subtle' : 'bg-success-subtle'">
                    <v-icon size="18"
                        :color="(task.time_spent || 0) > task.time_estimate ? 'error' : 'success'">mdi-clock-check-outline</v-icon>
                </div>
                <div>
                    <div class="text-caption text-medium-emphasis mb-1">Spent</div>
                    <div class="text-subtitle-1 font-weight-bold"
                        :class="(task.time_spent || 0) > task.time_estimate ? 'text-error' : ''">
                        {{ formatDuration((task.time_spent || 0) * 60) }}
                    </div>
                </div>
            </div>

            <div class="time-summary-card">
                <div class="time-summary-icon bg-warning-subtle">
                    <v-icon size="18" color="warning">mdi-clock-outline</v-icon>
                </div>
                <div>
                    <div class="text-caption text-medium-emphasis mb-1">Estimate</div>
                    <div class="text-subtitle-1 font-weight-bold">
                        {{ formatTimeEstimate(task.time_estimate) }}
                    </div>
                </div>
            </div>

            <div class="time-summary-card time-summary-card--progress">
                <div class="d-flex align-center justify-space-between mb-2 w-100">
                    <div class="d-flex align-center ga-2">
                        <div class="time-summary-icon bg-primary-subtle">
                            <v-icon size="18" color="primary">mdi-chart-arc</v-icon>
                        </div>
                        <span class="text-caption text-medium-emphasis">Progress</span>
                    </div>
                    <v-chip v-if="task.time_estimate" size="small" variant="tonal"
                        :color="(task.time_spent || 0) > task.time_estimate ? 'error' : 'primary'"
                        class="font-weight-bold">
                        {{ getSpentPercentage(task.time_spent, task.time_estimate) }}%
                    </v-chip>
                </div>
                <v-progress-linear v-if="task.time_estimate"
                    :model-value="((task.time_spent || 0) / task.time_estimate) * 100"
                    :color="(task.time_spent || 0) > task.time_estimate ? 'error' : 'primary'" bg-color="surface-light"
                    height="6" rounded />
                <span v-else class="text-body-2 text-medium-emphasis">No estimate</span>
            </div>
        </div>

        <!-- Log Time -->
        <div class="section-card mb-5">
            <div class="section-card-header">
                <div class="d-flex align-center ga-2">
                    <v-icon size="18" color="primary">mdi-clock-plus-outline</v-icon>
                    <span class="text-body-2 font-weight-bold">Log Time</span>
                </div>
                <v-chip v-if="durationPreview" size="small" color="primary" variant="tonal" class="font-weight-bold">
                    <v-icon start size="14">mdi-timer-outline</v-icon>
                    {{ durationPreview }}
                </v-chip>
                <span v-else class="text-caption text-medium-emphasis">Duration: --</span>
            </div>

            <div class="log-time-body">
                <div class="log-time-grid">
                    <!-- Start row -->
                    <div class="time-row">
                        <div class="time-row-indicator">
                            <div class="indicator-dot indicator-dot--start" />
                            <div class="indicator-line" />
                        </div>
                        <div class="time-row-content">
                            <div class="time-row-label text-caption font-weight-bold text-success">Start</div>
                            <div class="time-row-fields">
                                <v-text-field v-model="newEntry.startDate" label="Date" type="date"
                                    prepend-inner-icon="mdi-calendar" variant="outlined" density="compact" hide-details
                                    class="picker-field"
                                    @blur="newEntry.startDate = normalizeDateValue(newEntry.startDate)" />
                                <v-text-field v-model="newEntry.startTime" label="Time" type="time"
                                    prepend-inner-icon="mdi-clock-outline" variant="outlined" density="compact"
                                    hide-details class="picker-field"
                                    @blur="newEntry.startTime = normalizeTimeValue(newEntry.startTime)" />
                            </div>
                        </div>
                    </div>

                    <!-- End row -->
                    <div class="time-row">
                        <div class="time-row-indicator">
                            <div class="indicator-dot indicator-dot--end" />
                        </div>
                        <div class="time-row-content">
                            <div class="time-row-label text-caption font-weight-bold text-error">End</div>
                            <div class="time-row-fields">
                                <v-text-field v-model="newEntry.endDate" label="Date" type="date"
                                    prepend-inner-icon="mdi-calendar" variant="outlined" density="compact" hide-details
                                    class="picker-field"
                                    @blur="newEntry.endDate = normalizeDateValue(newEntry.endDate)" />
                                <v-text-field v-model="newEntry.endTime" label="Time" type="time"
                                    prepend-inner-icon="mdi-clock-outline" variant="outlined" density="compact"
                                    hide-details class="picker-field"
                                    @blur="newEntry.endTime = normalizeTimeValue(newEntry.endTime)" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="log-time-actions">
                    <v-btn color="primary" variant="flat" size="small" :disabled="!canLogEntry" @click="addEntry"
                        class="px-4">
                        <v-icon start size="16">mdi-plus</v-icon>
                        Log Time
                    </v-btn>
                </div>
            </div>
        </div>

        <!-- Entries header -->
        <div class="d-flex align-center justify-space-between mb-3" v-if="sortedTimeEntries.length">
            <div class="d-flex align-center ga-2">
                <v-icon size="18" color="primary">mdi-history</v-icon>
                <span class="text-body-2 font-weight-bold">Time Entries</span>
            </div>
            <v-chip size="x-small" variant="tonal" color="primary">{{ sortedTimeEntries.length }}</v-chip>
        </div>

        <!-- Empty state -->
        <div v-if="!sortedTimeEntries.length" class="empty-state">
            <div class="empty-state-icon">
                <v-icon size="32" color="grey-darken-1">mdi-clock-outline</v-icon>
            </div>
            <div class="text-body-2 font-weight-medium text-medium-emphasis mt-3">No time entries yet</div>
            <div class="text-caption text-medium-emphasis mt-1">Use the form above to log your first entry</div>
        </div>

        <!-- Time Entries list -->
        <TransitionGroup v-else name="entry-list" tag="div" class="time-entry-list">
            <div v-for="entry in sortedTimeEntries" :key="entry.id" class="time-entry-item">
                <v-avatar :color="entry.user?.avatar_color" size="30" class="time-entry-avatar">
                    <span class="text-[10px] font-weight-medium">{{ entry.user?.initials }}</span>
                </v-avatar>
                <div class="flex-1 min-w-0">
                    <div class="d-flex align-center ga-2">
                        <span class="text-body-2 font-weight-medium">{{ entry.user?.name }}</span>
                        <v-chip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                            {{ formatDuration(entry.duration * 60) }}
                        </v-chip>
                        <v-chip v-if="entry.is_running" size="x-small" color="success" variant="tonal"
                            class="running-chip">
                            <v-icon start size="8">mdi-circle</v-icon>
                            Running
                        </v-chip>
                    </div>
                    <div class="d-flex align-center ga-3 mt-1">
                        <span v-if="entry.started_at" class="time-entry-timestamp">
                            <v-icon size="11" color="success">mdi-play</v-icon>
                            {{ new Date(entry.started_at).toLocaleString() }}
                        </span>
                        <v-icon v-if="entry.started_at && entry.ended_at" size="10"
                            color="grey-darken-1">mdi-arrow-right</v-icon>
                        <span v-if="entry.ended_at" class="time-entry-timestamp">
                            <v-icon size="11" color="error">mdi-stop</v-icon>
                            {{ new Date(entry.ended_at).toLocaleString() }}
                        </span>
                        <span v-if="!entry.started_at && !entry.ended_at" class="time-entry-timestamp">
                            {{ new Date(entry.created_at).toLocaleString() }}
                        </span>
                    </div>
                </div>
                <v-btn icon size="x-small" variant="text" color="grey" class="delete-btn"
                    @click="deleteEntry(entry.id)">
                    <v-icon size="14" color="red">mdi-delete-outline</v-icon>
                </v-btn>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
/*  Summary Cards  */
.time-summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.time-summary-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 12px;
    transition: border-color 0.2s ease;
}

.time-summary-card:hover {
    border-color: rgba(255, 255, 255, 0.1);
}

.time-summary-card--progress {
    grid-column: 1 / -1;
    flex-direction: column;
    align-items: stretch;
}

.time-summary-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    flex-shrink: 0;
}

.bg-primary-subtle {
    background: rgba(123, 104, 238, 0.12);
}

.bg-success-subtle {
    background: rgba(107, 201, 80, 0.12);
}

.bg-error-subtle {
    background: rgba(255, 107, 107, 0.12);
}

.bg-warning-subtle {
    background: rgba(255, 183, 77, 0.12);
}

/*  Section Card  */
.section-card {
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 12px;
    overflow: hidden;
}

.section-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

.log-time-body {
    padding: 16px;
}

/*  Log Time Grid  */
.log-time-grid {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.time-row {
    display: flex;
    gap: 12px;
}

.time-row-indicator {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-top: 6px;
    width: 16px;
    flex-shrink: 0;
}

.indicator-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
    z-index: 1;
}

.indicator-dot--start {
    background: #6BC950;
    box-shadow: 0 0 0 3px rgba(107, 201, 80, 0.2);
}

.indicator-dot--end {
    background: #FF6B6B;
    box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
}

.indicator-line {
    width: 2px;
    flex: 1;
    background: rgba(255, 255, 255, 0.08);
    margin: 4px 0;
    border-radius: 1px;
}

.time-row-content {
    flex: 1;
    min-width: 0;
}

.time-row-label {
    margin-bottom: 6px;
}

.time-row-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.log-time-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid rgba(255, 255, 255, 0.04);
}

/*  Picker Fields  */
.picker-field {
    min-width: 0;
}

.picker-field :deep(.v-label) {
    font-size: 11px !important;
}

.picker-field :deep(input) {
    font-size: 12px !important;
}

/*  Empty State  */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 40px 20px;
}

.empty-state-icon {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.03);
    border: 1px dashed rgba(255, 255, 255, 0.08);
}

/*  Time Entry List  */
.time-entry-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.time-entry-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 14px;
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 10px;
    transition: border-color 0.2s ease, background 0.2s ease;
}

.time-entry-item:hover {
    border-color: rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.035);
}

.time-entry-item:hover .delete-btn {
    opacity: 1;
}

.time-entry-avatar {
    margin-top: 1px;
}

.time-entry-timestamp {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: rgba(255, 255, 255, 0.45);
}

.delete-btn {
    opacity: 0;
    transition: opacity 0.15s ease;
}

.running-chip :deep(.v-icon) {
    animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.3;
    }
}

/*  List Transitions  */
.entry-list-enter-active {
    transition: all 0.25s ease-out;
}

.entry-list-leave-active {
    transition: all 0.2s ease-in;
}

.entry-list-enter-from {
    opacity: 0;
    transform: translateY(-8px);
}

.entry-list-leave-to {
    opacity: 0;
    transform: translateX(12px);
}

.entry-list-move {
    transition: transform 0.25s ease;
}

/*  Responsive  */
@media (max-width: 480px) {
    .time-summary {
        grid-template-columns: 1fr;
    }

    .time-row-fields {
        grid-template-columns: 1fr;
    }
}
</style>
