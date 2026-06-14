<script setup>
/**
 * EmptyStateNoWorkspace Component
 * 
 * Displayed when user has no workspaces
 */
defineEmits(['create-workspace']);
</script>

<template>
    <div class="d-flex flex-column align-center justify-center fill-height pa-8">
        <v-icon size="120" color="grey-darken-1" class="mb-6">mdi-folder-multiple-outline</v-icon>
        <h2 class="text-h5 font-weight-bold text-white mb-2">Welcome to Your Workspace</h2>
        <p class="text-body-1 text-medium-emphasis text-center mb-6" style="max-width: 400px;">
            Get started by creating your first workspace to organize your projects and teams.
        </p>
        <v-btn color="primary" size="large" class="text-none" prepend-icon="mdi-plus" @click="$emit('create-workspace')">
            Create Workspace
        </v-btn>
    </div>
</template>
