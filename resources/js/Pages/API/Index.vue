<script setup>
import ApiTokenManager from '@/Pages/API/Partials/ApiTokenManager.vue';
import MainLayout from '@/Layouts/MainLayout.vue';

defineProps({
    tokens: { type: Array, default: () => [] },
    availablePermissions: { type: Array, default: () => [] },
    defaultPermissions: { type: Array, default: () => [] },
});
</script>

<template>
    <MainLayout title="API Tokens">
        <div class="max-w-4xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
            <ApiTokenManager
                :tokens="tokens"
                :available-permissions="availablePermissions"
                :default-permissions="defaultPermissions"
            />
        </div>
    </MainLayout>
</template>
