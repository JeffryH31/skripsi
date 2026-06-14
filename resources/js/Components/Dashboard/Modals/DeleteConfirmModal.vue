<script setup>
/**
 * DeleteConfirmModal Component
 * 
 * Generic delete confirmation modal
 */
import { useDisplay } from 'vuetify';

const { smAndDown } = useDisplay();

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    itemType: {
        type: String,
        default: 'item'
    },
    itemName: {
        type: String,
        default: ''
    },
    warningMessage: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const getDefaultWarning = () => {
    switch (props.itemType) {
        case 'workspace':
            return 'This will permanently delete the workspace and all its boards, features, and tasks.';
        case 'board':
            return 'This will permanently delete the board and all its features and tasks.';
        case 'list':
            return 'This will permanently delete the list and all features in it.';
        case 'feature':
            return 'This will permanently delete the feature and all its tasks.';
        case 'task':
            return 'This will permanently delete the task and its time entries.';
        default:
            return 'This action cannot be undone.';
    }
};

const handleConfirm = () => {
    emit('confirm');
    emit('update:modelValue', false);
};

const handleClose = () => {
    emit('update:modelValue', false);
};
</script>

<template>
    <v-dialog 
        :model-value="modelValue" 
        @update:model-value="$emit('update:modelValue', $event)" 
        :max-width="smAndDown ? undefined : 400"
        :fullscreen="smAndDown"
        :transition="smAndDown ? 'dialog-bottom-transition' : 'dialog-transition'"
    >
        <v-card color="surface">
            <v-card-title class="d-flex align-center pa-4 text-error">
                <v-icon start color="error">mdi-alert-circle</v-icon>
                Confirm Delete
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-4">
                <p class="text-body-1">
                    Are you sure you want to delete this {{ itemType }}?
                </p>
                <p v-if="itemName" class="text-body-1 font-weight-bold mt-2">
                    "{{ itemName }}"
                </p>
                <p class="text-body-2 text-medium-emphasis mt-2">
                    {{ warningMessage || getDefaultWarning() }}
                </p>
            </v-card-text>
            <v-divider />
            <v-card-actions class="pa-4">
                <v-spacer />
                <v-btn variant="text" @click="handleClose">Cancel</v-btn>
                <v-btn color="error" variant="flat" @click="handleConfirm">Delete</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
