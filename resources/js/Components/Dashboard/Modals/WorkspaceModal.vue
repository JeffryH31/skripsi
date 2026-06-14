<script setup>
/**
 * WorkspaceModal Component
 * 
 * Modal for creating/editing workspaces
 */
import { ref, watch } from 'vue';
import { useDisplay } from 'vuetify';

const { smAndDown } = useDisplay();

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    workspace: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['update:modelValue', 'save']);

const form = ref({ name: '' });

// Reset form when modal opens
watch(() => props.modelValue, (isOpen) => {
    if (isOpen) {
        form.value = {
            name: props.workspace?.name || ''
        };
    }
});

const isEditing = () => props.workspace !== null;

const handleSave = () => {
    if (!form.value.name.trim()) return;
    emit('save', {
        ...form.value,
        id: props.workspace?.id
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
                <v-icon start>mdi-folder-multiple-outline</v-icon>
                {{ isEditing() ? 'Edit Workspace' : 'Create Workspace' }}
                <v-spacer />
                <v-btn icon variant="text" size="small" @click="handleClose">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-4">
                <v-text-field 
                    v-model="form.name" 
                    label="Workspace Name"
                    placeholder="Enter workspace name" 
                    variant="outlined" 
                    bg-color="surface-variant"
                    autofocus
                    @keydown.enter="handleSave"
                />
            </v-card-text>
            <v-divider />
            <v-card-actions class="pa-4">
                <v-spacer />
                <v-btn variant="text" @click="handleClose">Cancel</v-btn>
                <v-btn 
                    color="primary" 
                    variant="flat" 
                    :disabled="!form.name.trim()" 
                    @click="handleSave"
                >
                    {{ isEditing() ? 'Save' : 'Create' }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
