<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    workspace: { type: Object, default: null },
    analytics: { type: Object, default: null },
    filters: { type: Object, default: null },
    members: { type: Array, default: () => [] },
    spaces: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const startDate = ref(props.filters?.start_date || props.analytics?.range?.start || '');
const endDate = ref(props.filters?.end_date || props.analytics?.range?.end || '');

const cards = computed(() => [
    { label: 'Total Tasks', value: props.analytics?.kpi?.tasks_total ?? 0 },
    { label: 'Completed Subtasks', value: props.analytics?.kpi?.subtasks_completed ?? 0 },
    { label: 'Overdue Subtasks', value: props.analytics?.kpi?.subtasks_overdue ?? 0 },
    { label: 'Active Sprints', value: props.analytics?.kpi?.active_sprints ?? 0 },
]);

const fmtRp = (val) => Number(val ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const evmCards = computed(() => [
    { label: 'Planned Value (PV)', value: fmtRp(props.analytics?.evm?.pv), hint: 'Budgeted cost of work scheduled' },
    { label: 'Earned Value (EV)', value: fmtRp(props.analytics?.evm?.ev), hint: 'Budgeted cost of work performed' },
    { label: 'Actual Cost (AC)', value: fmtRp(props.analytics?.evm?.ac), hint: 'Actual cost of work performed' },
]);

const evm = computed(() => props.analytics?.evm ?? {});

const cpiColor = computed(() => (evm.value.cpi ?? 1) >= 1 ? 'success' : 'error');
const spiColor = computed(() => (evm.value.spi ?? 1) >= 1 ? 'success' : 'error');

const roleColor = (r) => ({ admin: 'primary', manager: 'teal', member: 'default', guest: 'grey' }[r] ?? 'default');

const memberSearch = ref('');
const memberSpaceFilter = ref(null);

const memberHeaders = [
    { title: 'Member', key: 'name', sortable: true },
    { title: 'Working On', key: 'running_on', sortable: false },
    { title: 'Hourly Rate', key: 'hourly_rate', sortable: true },
    { title: '', key: 'actions', sortable: false, align: 'end' },
];

const filteredMembers = computed(() => {
    let list = props.members;
    if (memberSpaceFilter.value != null) {
        const sid = Number(memberSpaceFilter.value);
        list = list.filter(m => (m.space_ids ?? []).map(Number).includes(sid));
    }
    if (memberSearch.value) {
        const q = memberSearch.value.toLowerCase();
        list = list.filter(m => m.name.toLowerCase().includes(q) || m.email.toLowerCase().includes(q));
    }
    return list;
});

const applyFilter = () => {
    router.get(route('workspaces.analytics', props.workspace.id), {
        start_date: startDate.value || null,
        end_date: endDate.value || null,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const exportCsvUrl = computed(() => {
    const params = new URLSearchParams();
    if (startDate.value) params.set('start_date', startDate.value);
    if (endDate.value) params.set('end_date', endDate.value);
    return `${route('workspaces.analytics.export', props.workspace.id)}?${params.toString()}`;
});
</script>

<template>
    <MainLayout :title="`${workspace?.name} Analytics`">
        <div class="p-4 md:p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-5 gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Workspace Analytics</h1>
                    <p class="text-gray-400 text-sm">Insights and throughput trends for this workspace.</p>
                </div>
                <div class="flex gap-2">
                    <v-text-field v-model="startDate" type="date" label="Start" density="compact" hide-details
                        variant="outlined" />
                    <v-text-field v-model="endDate" type="date" label="End" density="compact" hide-details
                        variant="outlined" />
                    <v-btn color="primary" @click="applyFilter">Apply</v-btn>
                    <v-btn variant="outlined" :href="exportCsvUrl" download>Export CSV</v-btn>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <v-card v-for="card in cards" :key="card.label" class="pa-4">
                    <div class="text-xs text-gray-400">{{ card.label }}</div>
                    <div class="text-2xl font-semibold mt-1">{{ card.value }}</div>
                </v-card>

            </div>
            <!-- Team Section — only for owner/admin -->
            <div v-if="canManage && members.length" class="my-5">
                <div class="text-lg font-semibold mb-4">
                    <v-icon size="20" class="mr-2">mdi-account-group-outline</v-icon>
                    Team Member Reports
                </div>
                <v-card>
                    <div class="flex items-center gap-3 px-4 py-3 border-b border-white/8">
                        <v-text-field v-model="memberSearch" placeholder="Search name or email..."
                            prepend-inner-icon="mdi-magnify" density="compact" variant="outlined" hide-details clearable
                            style="max-width: 280px" />
                        <v-autocomplete v-model="memberSpaceFilter" :items="spaces" item-title="name" item-value="id"
                            placeholder="All Spaces" density="compact" variant="outlined" hide-details clearable
                            style="max-width: 220px" />
                        <v-chip v-if="memberSpaceFilter || memberSearch" size="small" variant="tonal" color="primary"
                            class="ml-auto shrink-0">
                            {{ filteredMembers.length }} of {{ members.length }} members
                        </v-chip>
                    </div>
                    <v-data-table :headers="memberHeaders" :items="filteredMembers" items-per-page="10"
                        class="elevation-0">
                        <template #item.name="{ item }">
                            <div class="flex items-center gap-2 py-1">
                                <v-avatar size="32" :color="item.avatar_color" class="text-white font-bold shrink-0">
                                    <v-img v-if="item.profile_photo_url" :src="item.profile_photo_url" />
                                    <span v-else class="text-xs">{{ item.initials }}</span>
                                </v-avatar>
                                <div>
                                    <div class="text-sm font-medium">{{ item.name }}</div>
                                    <div class="text-xs text-gray-400">{{ item.email }}</div>
                                </div>
                            </div>
                        </template>
                        <template #item.running_on="{ item }">
                            <div v-if="item.running_on" class="py-1">
                                <div class="flex items-center gap-1 text-xs">
                                    <v-icon size="12" color="success">mdi-circle</v-icon>
                                    <span class="font-medium truncate max-w-48">{{ item.running_on.subtask }}</span>
                                </div>
                                <div class="text-xs text-gray-400 truncate max-w-48 mt-0.5">
                                    {{ [item.running_on.space, item.running_on.task].filter(Boolean).join(' › ') }}
                                </div>
                            </div>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </template>
                        <template #item.hourly_rate="{ item }">
                            Rp {{ Number(item.hourly_rate).toLocaleString('id-ID') }}
                        </template>
                        <template #item.actions="{ item }">
                            <v-btn size="small" color="primary" variant="tonal"
                                :href="route('workspaces.members.report', [workspace.id, item.id])"
                                prepend-icon="mdi-chart-box-outline">
                                View Report
                            </v-btn>
                        </template>
                    </v-data-table>
                </v-card>
            </div>

            <!-- EVM KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                <v-card v-for="card in evmCards" :key="card.label" class="pa-4">
                    <div class="text-xs text-gray-400">{{ card.label }}</div>
                    <div class="text-2xl font-semibold mt-1">Rp {{ card.value }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ card.hint }}</div>
                </v-card>
            </div>

            <!-- EVM Summary -->
            <v-card class="mb-5">
                <v-card-title>Earned Value Management Summary</v-card-title>
                <v-divider />
                <v-card-text>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <div class="text-xs text-gray-400">Cost Variance (CV)</div>
                            <div class="text-lg font-semibold"
                                :class="(evm.cv ?? 0) >= 0 ? 'text-green-500' : 'text-red-500'">
                                Rp {{ fmtRp(evm.cv ?? 0) }}
                            </div>
                            <div class="text-xs text-gray-500">{{ (evm.cv ?? 0) >= 0 ? 'Under budget' : 'Over budget' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">Schedule Variance (SV)</div>
                            <div class="text-lg font-semibold"
                                :class="(evm.sv ?? 0) >= 0 ? 'text-green-500' : 'text-red-500'">
                                Rp {{ fmtRp(evm.sv ?? 0) }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ (evm.sv ?? 0) >= 0 ? 'Ahead of schedule' : 'Behind schedule' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">Cost Performance Index (CPI)</div>
                            <v-chip :color="cpiColor" size="small" class="mt-1">{{ (evm.cpi ?? 1).toFixed(2) }}</v-chip>
                            <div class="text-xs text-gray-500 mt-1">{{ (evm.cpi ?? 1) >= 1 ? 'Efficient' : 'Inefficient'
                                }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">Schedule Performance Index (SPI)</div>
                            <v-chip :color="spiColor" size="small" class="mt-1">{{ (evm.spi ?? 1).toFixed(2) }}</v-chip>
                            <div class="text-xs text-gray-500 mt-1">{{ (evm.spi ?? 1) >= 1 ? 'On schedule' : 'Delayed'
                                }}</div>
                        </div>
                    </div>
                </v-card-text>
            </v-card>


        </div>
    </MainLayout>
</template>
