<script setup>
/**
 * EmptyStateNoBoard Component
 * 
 * Displayed when user has workspaces but no board selected
 */
defineEmits(['create-board']);
</script>

<template>
    <div class="d-flex flex-column align-center justify-center fill-height pa-8">
        <v-icon size="120" color="grey-darken-1" class="mb-6">mdi-view-dashboard-outline</v-icon>
        <h2 class="text-h5 font-weight-bold text-white mb-2">No Board Selected</h2>
        <p class="text-body-1 text-medium-emphasis text-center mb-6" style="max-width: 400px;">
            Select an existing board from the sidebar or create a new one to get started.
        </p>
        <v-btn color="primary" size="large" class="text-none" prepend-icon="mdi-plus" @click="$emit('create-board')">
            Create Board
        </v-btn>
    </div>
</template>
