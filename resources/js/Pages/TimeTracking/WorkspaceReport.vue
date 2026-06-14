<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { formatMinutes as formatDuration } from '@/utils/duration';

const props = defineProps({
    workspace: { type: Object, default: null },
    report: { type: Object, default: null },
});

const memberSearch = ref('');
const memberSortBy = ref('minutes_desc');
const spaceSearch = ref('');
const spaceSortBy = ref('minutes_desc');

const sortOptions = [
    { title: 'Most Time', value: 'minutes_desc' },
    { title: 'Least Time', value: 'minutes_asc' },
    { title: 'Name A-Z', value: 'name_asc' },
    { title: 'Name Z-A', value: 'name_desc' },
];


// Calculate percentage for bar chart
const maxUserMinutes = computed(() => {
    if (!props.report?.by_user?.length) return 1;
    return Math.max(...props.report.by_user.map(u => u.total_minutes), 1);
});

const maxSpaceMinutes = computed(() => {
    if (!props.report?.by_space?.length) return 1;
    return Math.max(...props.report.by_space.map(s => s.total_minutes), 1);
});

const getUserPercentage = (minutes) => {
    return (minutes / maxUserMinutes.value) * 100;
};

const getSpacePercentage = (minutes) => {
    return (minutes / maxSpaceMinutes.value) * 100;
};

const sortTimeItems = (items, sortKey) => {
    const result = [...items];
    switch (sortKey) {
        case 'minutes_asc':
            result.sort((a, b) => (a.total_minutes || 0) - (b.total_minutes || 0));
            break;
        case 'name_asc':
            result.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
            break;
        case 'name_desc':
            result.sort((a, b) => (b.name || '').localeCompare(a.name || ''));
            break;
        case 'minutes_desc':
        default:
            result.sort((a, b) => (b.total_minutes || 0) - (a.total_minutes || 0));
            break;
    }
    return result;
};

const filteredUsers = computed(() => {
    let result = [...(props.report?.by_user || [])];
    const query = memberSearch.value.trim().toLowerCase();
    if (query) {
        result = result.filter((user) => (user.name || '').toLowerCase().includes(query));
    }
    return sortTimeItems(result, memberSortBy.value);
});

const filteredSpaces = computed(() => {
    let result = [...(props.report?.by_space || [])];
    const query = spaceSearch.value.trim().toLowerCase();
    if (query) {
        result = result.filter((space) => (space.name || '').toLowerCase().includes(query));
    }
    return sortTimeItems(result, spaceSortBy.value);
});
</script>

<template>
    <Head title="Time Report" />

    <MainLayout>
        <div class="h-full bg-[#1E1E1E] overflow-auto">
            <!-- Header -->
            <div class="bg-[#252526] border-b border-gray-800 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-semibold text-white">Time Report</h1>
                        <p class="text-sm text-gray-400 mt-1">{{ workspace?.name }}</p>
                    </div>
                    <v-btn
                        variant="text"
                        prepend-icon="mdi-arrow-left"
                        @click="router.visit(route('time-tracking.index'))"
                    >
                        Back to Time Tracking
                    </v-btn>
                </div>
            </div>

            <div class="p-6 max-w-5xl mx-auto">
                <!-- Total Summary -->
                <v-card class="bg-[#252526] mb-6" rounded="lg">
                    <v-card-text class="text-center py-8">
                        <div class="text-4xl font-bold text-white mb-2">
                            {{ report?.total_formatted || '0m' }}
                        </div>
                        <div class="text-gray-400">Total Time Tracked</div>
                    </v-card-text>
                </v-card>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- By User -->
                    <v-card class="bg-[#252526]" rounded="lg">
                        <v-card-title class="text-sm text-gray-400 font-medium">
                            <v-icon start size="18">mdi-account-group</v-icon>
                            By Member
                        </v-card-title>
                        <v-card-text>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-4">
                                <v-text-field v-model="memberSearch" density="compact" variant="outlined" hide-details
                                    prepend-inner-icon="mdi-magnify" label="Filter members" />
                                <v-select v-model="memberSortBy" :items="sortOptions" item-title="title" item-value="value"
                                    density="compact" variant="outlined" hide-details label="Sort members" />
                            </div>
                            <div v-if="filteredUsers.length" class="space-y-4">
                                <div v-for="user in filteredUsers" :key="user.id">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-white">{{ user.name }}</span>
                                        <span class="text-gray-400 font-mono">{{ formatDuration(user.total_minutes) }}</span>
                                    </div>
                                    <div class="w-full bg-[#1E1E1E] rounded-full h-2">
                                        <div
                                            class="bg-primary h-2 rounded-full transition-all"
                                            :style="{ width: getUserPercentage(user.total_minutes) + '%' }"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center text-gray-500 py-8">
                                No members match your filter
                            </div>
                        </v-card-text>
                    </v-card>

                    <!-- By Space -->
                    <v-card class="bg-[#252526]" rounded="lg">
                        <v-card-title class="text-sm text-gray-400 font-medium">
                            <v-icon start size="18">mdi-folder-multiple</v-icon>
                            By Space
                        </v-card-title>
                        <v-card-text>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-4">
                                <v-text-field v-model="spaceSearch" density="compact" variant="outlined" hide-details
                                    prepend-inner-icon="mdi-magnify" label="Filter spaces" />
                                <v-select v-model="spaceSortBy" :items="sortOptions" item-title="title" item-value="value"
                                    density="compact" variant="outlined" hide-details label="Sort spaces" />
                            </div>
                            <div v-if="filteredSpaces.length" class="space-y-4">
                                <div v-for="space in filteredSpaces" :key="space.id">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-white">{{ space.name }}</span>
                                        <span class="text-gray-400 font-mono">{{ formatDuration(space.total_minutes) }}</span>
                                    </div>
                                    <div class="w-full bg-[#1E1E1E] rounded-full h-2">
                                        <div
                                            class="bg-info h-2 rounded-full transition-all"
                                            :style="{ width: getSpacePercentage(space.total_minutes) + '%' }"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center text-gray-500 py-8">
                                No spaces match your filter
                            </div>
                        </v-card-text>
                    </v-card>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
