<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    status: Number,
});

const page = usePage();

const descriptions = {
    403: {
        title: 'Access Denied',
        message: 'You don\'t have permission to access this resource.',
        icon: 'mdi-shield-lock-outline',
        color: 'warning',
    },
    404: {
        title: 'Page Not Found',
        message: 'The page you\'re looking for doesn\'t exist or has been moved.',
        icon: 'mdi-file-search-outline',
        color: 'info',
    },
    500: {
        title: 'Server Error',
        message: 'Something went wrong on our end. Please try again later.',
        icon: 'mdi-alert-circle-outline',
        color: 'error',
    },
    503: {
        title: 'Service Unavailable',
        message: 'The application is currently undergoing maintenance. Please try again shortly.',
        icon: 'mdi-wrench-outline',
        color: 'warning',
    },
};

const error = computed(() => descriptions[props.status] || {
    title: 'Error',
    message: 'An unexpected error occurred.',
    icon: 'mdi-alert-outline',
    color: 'error',
});

const goBack = () => {
    window.history.length > 1
        ? window.history.back()
        : router.visit('/dashboard');
};
</script>

<template>
    <MainLayout :title="error.title">
        <div class="error-page">
            <div class="error-content">
                <v-icon :color="error.color" size="64" class="mb-4">{{ error.icon }}</v-icon>

                <div class="text-h3 font-weight-bold mb-2" :class="`text-${error.color}`">
                    {{ status }}
                </div>

                <h1 class="text-h5 font-weight-bold mb-2">{{ error.title }}</h1>

                <p class="text-body-1 text-medium-emphasis mb-6" style="max-width: 420px;">
                    {{ error.message }}
                </p>

                <div class="d-flex ga-3">
                    <v-btn variant="tonal" rounded="lg" @click="goBack">
                        <v-icon start size="18">mdi-arrow-left</v-icon>
                        Go Back
                    </v-btn>
                    <v-btn color="primary" variant="flat" rounded="lg" @click="router.visit('/dashboard')">
                        <v-icon start size="18">mdi-view-dashboard-outline</v-icon>
                        Dashboard
                    </v-btn>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.error-page {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: calc(100vh - 64px);
    padding: 24px;
}

.error-content {
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
}
</style>
