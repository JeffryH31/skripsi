<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    workspace: { type: Object, default: null },
    member: { type: Object, default: null },
    report: { type: Object, default: null },
});

// Helpers
const fmtMinutes = (m) => {
    if (!m) return '0h 0m';
    const h = Math.floor(m / 60);
    const min = m % 60;
    return h > 0 ? `${h}h ${min}m` : `${min}m`;
};

const fmtDuration = (m) => {
    const h = Math.floor(m / 60);
    const min = m % 60;
    return [h ? `${h}h` : null, min ? `${min}m` : null].filter(Boolean).join(' ') || '0m';
};

const fmtTs = (ts) => {
    if (!ts) return '-';
    const d = new Date(ts);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const fmtDate = (d) => {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const priorityLabel = (v) => ['', 'Urgent', 'High', 'Normal', 'Low'][v] ?? '-';
const priorityColor = (v) => ['', 'error', 'orange', 'primary', 'grey'][v] ?? 'grey';

const roleColor = (r) => ({ admin: 'primary', manager: 'teal', member: 'default', guest: 'grey' }[r] ?? 'default');

const stats = computed(() => props.report?.stats ?? {});
const runningTimer = computed(() => props.report?.runningTimer);
const dailyData = computed(() => props.report?.dailyData ?? []);
const weeklyData = computed(() => props.report?.weeklyData ?? []);
const activeSubtasks = computed(() => props.report?.activeSubtasks ?? []);
const recentlyCompleted = computed(() => props.report?.recentlyCompleted ?? []);
const recentEntries = computed(() => props.report?.recentEntries ?? []);
const recentActivity = computed(() => props.report?.recentActivity ?? []);

// Elapsed timer display — ticks every minute
const timerExtraMinutes = ref(0);
let timerInterval = null;

const elapsedMs = computed(() => {
    if (!runningTimer.value) return 0;
    return (runningTimer.value.elapsed_minutes || 0) + timerExtraMinutes.value;
});

onMounted(() => {
    if (runningTimer.value) {
        timerInterval = setInterval(() => {
            timerExtraMinutes.value += 1;
        }, 60000);
    }
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

// Tabs
const tab = ref('overview');

const activityIcon = (action) => {
    const icons = {
        created: 'mdi-plus-circle-outline',
        updated: 'mdi-pencil-outline',
        deleted: 'mdi-trash-can-outline',
        completed: 'mdi-check-circle-outline',
        reopened: 'mdi-restore',
        assigned: 'mdi-account-plus-outline',
        commented: 'mdi-comment-outline',
        time_logged: 'mdi-clock-outline',
        timer_started: 'mdi-play-circle-outline',
        timer_stopped: 'mdi-stop-circle-outline',
        status_changed: 'mdi-swap-horizontal',
        priority_changed: 'mdi-flag-outline',
        archived: 'mdi-archive-outline',
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
    };
    return colors[action] ?? 'grey';
};

// Billable pct
const billablePct = computed(() => {
    const m = stats.value.month_minutes;
    const b = stats.value.billable_minutes;
    if (!m) return 0;
    return Math.round((b / m) * 100);
});
</script>

<template>
    <MainLayout :title="`${member.name} — Member Report`">
        <div class="p-4 md:p-6">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center gap-4 mb-6">
                <v-btn :href="route('workspaces.settings', workspace.id)" variant="text" size="small"
                    prepend-icon="mdi-arrow-left" class="-ml-2">
                    Back to Settings
                </v-btn>
            </div>

            <!-- Member Card -->
            <v-card class="mb-5 pa-5">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <v-avatar size="64" :color="member.avatar_color" class="text-white text-xl font-bold flex-shrink-0">
                        <v-img v-if="member.profile_photo_url" :src="member.profile_photo_url" />
                        <span v-else>{{ member.initials }}</span>
                    </v-avatar>
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xl font-bold">{{ member.name }}</span>
                            <v-chip :color="roleColor(member.role)" size="small" class="capitalize">
                                {{ member.role }}
                            </v-chip>
                            <v-chip v-if="runningTimer" color="success" size="small" variant="tonal">
                                <v-icon start size="14">mdi-circle</v-icon>
                                Working now
                            </v-chip>
                        </div>
                        <div class="text-sm text-gray-400 mt-1">{{ member.email }}</div>
                        <div class="text-sm text-gray-500 mt-1">
                            Rate: <span class="font-medium">Rp {{ Number(member.hourly_rate).toLocaleString('id-ID') }}/hr</span>
                        </div>
                    </div>

                    <!-- Running timer banner -->
                    <div v-if="runningTimer" class="bg-green-900/20 border border-green-700/40 rounded-lg px-4 py-3 min-w-[220px]">
                        <div class="text-xs text-green-400 font-medium flex items-center gap-1 mb-1">
                            <v-icon size="12" color="success">mdi-circle</v-icon>
                            Timer running
                        </div>
                        <div class="text-sm font-semibold text-white truncate max-w-[200px]">
                            {{ runningTimer.subtask }}
                        </div>
                        <div class="text-xs text-gray-400 truncate">{{ runningTimer.list }} · {{ runningTimer.space }}</div>
                        <div class="text-lg font-bold text-green-400 mt-1">{{ fmtMinutes(elapsedMs) }}</div>
                    </div>
                </div>
            </v-card>

            <!-- Summary Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
                <v-card class="pa-4">
                    <div class="text-xs text-gray-400 mb-1">Today</div>
                    <div class="text-2xl font-bold">{{ fmtMinutes(stats.today_minutes) }}</div>
                    <div class="text-xs text-gray-500 mt-1">time tracked</div>
                </v-card>
                <v-card class="pa-4">
                    <div class="text-xs text-gray-400 mb-1">This Week</div>
                    <div class="text-2xl font-bold">{{ fmtMinutes(stats.week_minutes) }}</div>
                    <div class="text-xs text-gray-500 mt-1">time tracked</div>
                </v-card>
                <v-card class="pa-4">
                    <div class="text-xs text-gray-400 mb-1">This Month</div>
                    <div class="text-2xl font-bold">{{ fmtMinutes(stats.month_minutes) }}</div>
                    <div class="text-xs text-gray-500 mt-1">time tracked</div>
                </v-card>
                <v-card class="pa-4">
                    <div class="text-xs text-gray-400 mb-1">Billable (this month)</div>
                    <div class="text-2xl font-bold">{{ fmtMinutes(stats.billable_minutes) }}</div>
                    <div class="flex items-center gap-2 mt-1">
                        <v-progress-linear :model-value="billablePct" color="success" rounded height="4" class="flex-1" />
                        <span class="text-xs text-gray-400">{{ billablePct }}%</span>
                    </div>
                </v-card>
            </div>

            <!-- Tabs -->
            <v-tabs v-model="tab" class="mb-4">
                <v-tab value="overview">Overview</v-tab>
                <v-tab value="work">Active Work ({{ activeSubtasks.length }})</v-tab>
                <v-tab value="time">Time Log</v-tab>
                <v-tab value="activity">Activity</v-tab>
            </v-tabs>

            <v-window v-model="tab">

                <!-- OVERVIEW TAB -->
                <v-window-item value="overview">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                        <!-- Daily Activity Chart (14 days) -->
                        <v-card>
                            <v-card-title class="text-sm font-semibold">Daily Activity (last 14 days)</v-card-title>
                            <v-divider />
                            <v-card-text>
                                <div class="flex items-end gap-1 h-32">
                                    <div
                                        v-for="d in dailyData"
                                        :key="d.date"
                                        class="flex flex-col items-center flex-1 gap-1 h-full justify-end"
                                        :title="`${d.date}: ${fmtMinutes(d.minutes)}`"
                                    >
                                        <div
                                            class="w-full rounded-t transition-all"
                                            :class="d.minutes > 0 ? 'bg-primary' : 'bg-gray-700'"
                                            :style="`height: ${d.pct}%; min-height: ${d.minutes > 0 ? 4 : 2}px`"
                                        />
                                        <span class="text-[9px] text-gray-500 whitespace-nowrap">{{ d.label }}</span>
                                    </div>
                                </div>
                            </v-card-text>
                        </v-card>

                        <!-- Weekly Breakdown -->
                        <v-card>
                            <v-card-title class="text-sm font-semibold">This Week (per day)</v-card-title>
                            <v-divider />
                            <v-card-text class="d-flex flex-column ga-2">
                                <div v-for="d in weeklyData" :key="d.date" class="flex items-center gap-3">
                                    <span class="text-xs text-gray-400 w-8 shrink-0">{{ d.day }}</span>
                                    <v-progress-linear
                                        :model-value="d.minutes > 0 ? Math.min((d.minutes / 480) * 100, 100) : 0"
                                        :color="d.date === new Date().toISOString().slice(0, 10) ? 'success' : 'primary'"
                                        rounded
                                        height="12"
                                        class="flex-1"
                                        bg-color="surface-variant"
                                    />
                                    <span class="text-xs text-gray-300 w-14 text-right shrink-0">
                                        {{ d.minutes > 0 ? fmtDuration(d.minutes) : '-' }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 text-right mt-1">Baseline 8h/day</div>
                            </v-card-text>
                        </v-card>

                        <!-- Recently Completed -->
                        <v-card class="lg:col-span-2">
                            <v-card-title class="text-sm font-semibold">Recently Completed (last 30 days)</v-card-title>
                            <v-divider />
                            <div class="overflow-x-auto">
                            <v-table density="compact">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Subtask</th>
                                        <th>Task / List</th>
                                        <th>Completed At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="recentlyCompleted.length === 0">
                                        <td colspan="4" class="text-center text-gray-400 py-4">No completed subtasks yet</td>
                                    </tr>
                                    <tr v-for="s in recentlyCompleted" :key="s.id">
                                        <td class="text-xs text-gray-400">{{ s.subtask_id }}</td>
                                        <td class="text-sm">{{ s.name }}</td>
                                        <td class="text-xs text-gray-400">{{ s.task }} · {{ s.list }}</td>
                                        <td class="text-xs text-gray-400">{{ fmtTs(s.completed_at) }}</td>
                                    </tr>
                                </tbody>
                            </v-table>
                            </div>
                        </v-card>

                    </div>
                </v-window-item>

                <!-- ACTIVE WORK TAB -->
                <v-window-item value="work">
                    <v-card>
                        <v-card-title class="text-sm font-semibold">Active Work in Progress</v-card-title>
                        <v-divider />
                        <div v-if="activeSubtasks.length === 0" class="pa-6 text-center text-gray-400">
                            No active work assigned.
                        </div>
                        <div v-else class="overflow-x-auto">
                        <v-table density="compact">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Subtask</th>
                                    <th>Task / Project</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Progress</th>
                                    <th>Deadline</th>
                                    <th>Sprint</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in activeSubtasks" :key="s.id" :class="s.is_overdue ? 'bg-red-950/20' : ''">
                                    <td class="text-xs text-gray-400">{{ s.subtask_id }}</td>
                                    <td>
                                        <div class="text-sm font-medium">{{ s.name }}</div>
                                        <div class="text-xs text-gray-400">{{ s.space.name }}</div>
                                    </td>
                                    <td class="text-xs text-gray-400">{{ s.task.name }} · {{ s.list.name }}</td>
                                    <td>
                                        <v-chip
                                            size="x-small"
                                            :style="`background: ${s.status.color}22; color: ${s.status.color}`"
                                        >{{ s.status.name }}</v-chip>
                                    </td>
                                    <td>
                                        <v-chip size="x-small" :color="priorityColor(s.priority)">
                                            {{ priorityLabel(s.priority) }}
                                        </v-chip>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-1 min-w-[80px]">
                                            <v-progress-linear
                                                :model-value="s.progress"
                                                color="primary"
                                                rounded
                                                height="6"
                                                class="flex-1"
                                            />
                                            <span class="text-xs text-gray-400">{{ s.progress }}%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span
                                            class="text-xs"
                                            :class="s.is_overdue ? 'text-red-400 font-semibold' : 'text-gray-400'"
                                        >
                                            {{ s.due_date ? fmtDate(s.due_date) : '-' }}
                                            <span v-if="s.is_overdue"> ⚠</span>
                                        </span>
                                    </td>
                                    <td class="text-xs text-gray-400">{{ s.sprint?.name ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </v-table>
                        </div>
                    </v-card>
                </v-window-item>

                <!-- TIME LOG TAB -->
                <v-window-item value="time">
                    <v-card>
                        <v-card-title class="text-sm font-semibold">Last 30 Time Entries</v-card-title>
                        <v-divider />
                        <div v-if="recentEntries.length === 0" class="pa-6 text-center text-gray-400">
                            No time entries yet.
                        </div>
                        <div v-else class="overflow-x-auto">
                        <v-table density="compact">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subtask</th>
                                    <th>Task / Project</th>
                                    <th>Duration</th>
                                    <th>Billable</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="e in recentEntries" :key="e.id">
                                    <td class="text-xs text-gray-400 whitespace-nowrap">
                                        {{ fmtTs(e.started_at) }}
                                    </td>
                                    <td>
                                        <div class="text-sm">{{ e.subtask.name }}</div>
                                        <div class="text-xs text-gray-500">{{ e.subtask.subtask_id }}</div>
                                    </td>
                                    <td class="text-xs text-gray-400">{{ e.task }} · {{ e.list }}</td>
                                    <td class="text-sm font-medium">{{ fmtDuration(e.duration) }}</td>
                                    <td>
                                        <v-icon v-if="e.is_billable" size="16" color="success">mdi-check-circle</v-icon>
                                        <v-icon v-else size="16" color="grey">mdi-minus-circle-outline</v-icon>
                                    </td>
                                    <td>
                                        <v-chip v-if="e.is_running" color="success" size="x-small">Running</v-chip>
                                        <v-chip v-else color="grey" size="x-small" variant="tonal">Done</v-chip>
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>
                        </div>
                    </v-card>
                </v-window-item>

                <!-- ACTIVITY TAB -->
                <v-window-item value="activity">
                    <v-card>
                        <v-card-title class="text-sm font-semibold">Activity Log (last 30)</v-card-title>
                        <v-divider />
                        <div v-if="recentActivity.length === 0" class="pa-6 text-center text-gray-400">
                            No activity yet.
                        </div>
                        <v-list v-else lines="two">
                            <template v-for="(a, idx) in recentActivity" :key="a.id">
                                <v-list-item>
                                    <template #prepend>
                                        <v-avatar :color="activityColor(a.action)" size="32">
                                            <v-icon size="16" color="white">{{ activityIcon(a.action) }}</v-icon>
                                        </v-avatar>
                                    </template>
                                    <v-list-item-title class="text-sm">
                                        <span class="font-medium">{{ a.description }}</span>
                                    </v-list-item-title>
                                    <v-list-item-subtitle class="text-xs text-gray-400">
                                        <span>{{ a.subject_type }}</span>
                                        <span v-if="a.properties?.name" class="text-gray-300"> · {{ a.properties.name }}</span>
                                    </v-list-item-subtitle>
                                    <template #append>
                                        <span class="text-xs text-gray-500 whitespace-nowrap">{{ fmtTs(a.created_at) }}</span>
                                    </template>
                                </v-list-item>
                                <v-divider v-if="idx < recentActivity.length - 1" inset />
                            </template>
                        </v-list>
                    </v-card>
                </v-window-item>

            </v-window>

        </div>
    </MainLayout>
</template>
