<script setup>
/**
 * BoardModal Component
 * 
 * Modal for creating/editing boards
 */
import { ref, watch } from 'vue';
import { useDisplay } from 'vuetify';

const { smAndDown } = useDisplay();

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    board: {
        type: Object,
        default: null
    },
    workspaces: {
        type: Array,
        default: () => []
    },
    defaultWorkspaceId: {
        type: Number,
        default: null
    }
});

const emit = defineEmits(['update:modelValue', 'save']);

const boardColors = [
    '#6366F1', '#8B5CF6', '#EC4899', '#EF4444',
    '#F59E0B', '#10B981', '#0EA5E9', '#6B7280'
];

const form = ref({ 
    name: '', 
    color: '#6366F1',
    workspaceId: null 
});

// Reset form when modal opens
watch(() => props.modelValue, (isOpen) => {
    if (isOpen) {
        form.value = {
            name: props.board?.name || '',
            color: props.board?.color || '#6366F1',
            workspaceId: props.defaultWorkspaceId || props.workspaces[0]?.id || null
        };
    }
});

const isEditing = () => props.board !== null;

const handleSave = () => {
    if (!form.value.name.trim()) return;
    emit('save', {
        ...form.value,
        id: props.board?.id
    });
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
        :max-width="smAndDown ? undefined : 450"
        :fullscreen="smAndDown"
        :transition="smAndDown ? 'dialog-bottom-transition' : 'dialog-transition'"
        persistent
    >
        <v-card color="surface">
            <v-card-title class="d-flex align-center pa-4">
                <v-icon start>mdi-view-dashboard-outline</v-icon>
                {{ isEditing() ? 'Edit Board' : 'Create Board' }}
                <v-spacer />
                <v-btn icon variant="text" size="small" @click="handleClose">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-4">
                <!-- Workspace Selector (only when creating new board) -->
                <v-select 
                    v-if="!isEditing()" 
                    v-model="form.workspaceId" 
                    :items="workspaces" 
                    item-title="name"
                    item-value="id" 
                    label="Workspace" 
                    variant="outlined"
                    bg-color="surface-variant" 
                    class="mb-4"
                    :rules="[v => !!v || 'Please select a workspace']"
                />

                <v-text-field 
                    v-model="form.name" 
                    label="Board Name"
                    placeholder="Enter board name" 
                    variant="outlined"
                    bg-color="surface-variant" 
                    class="mb-4" 
                    autofocus 
                />
                
                <div class="text-body-2 text-medium-emphasis mb-2">Board Color</div>
                <div class="d-flex flex-wrap ga-2">
                    <v-btn 
                        v-for="color in boardColors" 
                        :key="color" 
                        icon 
                        size="32"
                        :style="{ backgroundColor: color }"
                        :class="{ 'border-primary': form.color === color }"
                        @click="form.color = color"
                    >
                        <v-icon v-if="form.color === color" size="18" color="white">
                            mdi-check
                        </v-icon>
                    </v-btn>
                </div>
            </v-card-text>
            <v-divider />
            <v-card-actions class="pa-4">
                <v-spacer />
                <v-btn variant="text" @click="handleClose">Cancel</v-btn>
                <v-btn 
                    color="primary" 
                    variant="flat"
                    :disabled="!form.name.trim() || (!isEditing() && !form.workspaceId)"
                    @click="handleSave"
                >
                    {{ isEditing() ? 'Save' : 'Create' }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.border-primary {
    border: 3px solid rgb(var(--v-theme-primary)) !important;
}
</style>
