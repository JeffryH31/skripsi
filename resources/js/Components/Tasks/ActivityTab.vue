<script setup>
defineProps({
    activities: { type: Array, default: () => [] },
});

const formatTime = (dateStr) => {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffMin = Math.floor(diffMs / 60000);
    const diffHr = Math.floor(diffMs / 3600000);
    const diffDay = Math.floor(diffMs / 86400000);

    if (diffMin < 1) return 'just now';
    if (diffMin < 60) return `${diffMin}m ago`;
    if (diffHr < 24) return `${diffHr}h ago`;
    if (diffDay < 7) return `${diffDay}d ago`;
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};
</script>

<template>
    <div class="pa-5">
        <div v-if="!activities.length" class="d-flex flex-column align-center py-10 text-grey">
            <v-icon size="48" class="mb-2" color="grey-darken-1">mdi-history</v-icon>
            <div class="text-body-2">No activity yet</div>
        </div>
        <div v-else class="activity-list">
            <div v-for="activity in activities" :key="activity.id" class="activity-item">
                <v-avatar :color="activity.user?.avatar_color" size="28">
                    <span class="text-[10px]">{{ activity.user?.initials }}</span>
                </v-avatar>
                <div class="flex-1 min-w-0">
                    <div class="text-body-2">
                        <span class="font-weight-medium">{{ activity.user?.name }}</span> <span class="text-grey">{{
                            activity.description }}</span>
                    </div>
                    <div class="text-caption text-grey-darken-1 mt-0.5">
                        {{ formatTime(activity.created_at) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
</style>
