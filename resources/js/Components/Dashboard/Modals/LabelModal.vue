<script setup>
/**
 * LabelModal Component
 * 
 * Modal for managing labels
 */
import { ref, watch } from 'vue';
import { useDisplay } from 'vuetify';

const { smAndDown } = useDisplay();

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    labels: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['update:modelValue', 'create', 'update', 'delete']);

const defaultColors = [
    '#EF4444', '#F97316', '#F59E0B', '#84CC16',
    '#22C55E', '#14B8A6', '#06B6D4', '#3B82F6',
    '#6366F1', '#8B5CF6', '#A855F7', '#EC4899'
];

const editingLabel = ref(null);
const newLabel = ref({ name: '', color: '#3B82F6' });
const showNewForm = ref(false);

watch(() => props.modelValue, (isOpen) => {
    if (isOpen) {
        editingLabel.value = null;
        showNewForm.value = false;
        resetNewLabel();
    }
});

const resetNewLabel = () => {
    newLabel.value = { name: '', color: '#3B82F6' };
};

const handleStartEdit = (label) => {
    editingLabel.value = { ...label };
};

const handleCancelEdit = () => {
    editingLabel.value = null;
};

const handleSaveEdit = () => {
    if (!editingLabel.value.name.trim()) return;
    emit('update', editingLabel.value);
    editingLabel.value = null;
};

const handleDelete = (label) => {
    emit('delete', label);
};

const handleCreateLabel = () => {
    if (!newLabel.value.name.trim()) return;
    emit('create', { ...newLabel.value });
    resetNewLabel();
    showNewForm.value = false;
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
        scrollable
    >
        <v-card color="surface">
            <v-card-title class="d-flex align-center pa-4">
                <v-icon start>mdi-label-multiple</v-icon>
                Manage Labels
                <v-spacer />
                <v-btn icon variant="text" size="small" @click="handleClose">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-4">
                <!-- Labels List -->
                <div class="labels-list">
                    <div 
                        v-for="label in labels" 
                        :key="label.id" 
                        class="label-item mb-2"
                    >
                        <!-- Edit Mode -->
                        <template v-if="editingLabel?.id === label.id">
                            <div class="d-flex align-center gap-2">
                                <v-text-field
                                    v-model="editingLabel.name"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    autofocus
                                    class="flex-grow-1"
                                />
                                <v-btn icon variant="text" size="small" color="success" @click="handleSaveEdit">
                                    <v-icon>mdi-check</v-icon>
                                </v-btn>
                                <v-btn icon variant="text" size="small" @click="handleCancelEdit">
                                    <v-icon>mdi-close</v-icon>
                                </v-btn>
                            </div>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                <v-btn
                                    v-for="color in defaultColors"
                                    :key="color"
                                    icon
                                    size="24"
                                    :style="{ backgroundColor: color }"
                                    :class="{ 'border-white': editingLabel.color === color }"
                                    @click="editingLabel.color = color"
                                >
                                    <v-icon v-if="editingLabel.color === color" size="14" color="white">
                                        mdi-check
                                    </v-icon>
                                </v-btn>
                            </div>
                        </template>

                        <!-- View Mode -->
                        <template v-else>
                            <div class="d-flex align-center">
                                <v-chip
                                    :style="{ backgroundColor: label.color }"
                                    size="small"
                                    class="mr-2 flex-grow-1"
                                    text-color="white"
                                >
                                    {{ label.name }}
                                </v-chip>
                                <v-btn icon variant="text" size="x-small" @click="handleStartEdit(label)">
                                    <v-icon size="16">mdi-pencil</v-icon>
                                </v-btn>
                                <v-btn icon variant="text" size="x-small" color="error" @click="handleDelete(label)">
                                    <v-icon size="16">mdi-delete</v-icon>
                                </v-btn>
                            </div>
                        </template>
                    </div>

                    <p v-if="labels.length === 0 && !showNewForm" class="text-body-2 text-medium-emphasis text-center py-4">
                        No labels created yet
                    </p>
                </div>

                <v-divider class="my-4" />

                <!-- New Label Form -->
                <template v-if="showNewForm">
                    <div class="text-subtitle-2 text-medium-emphasis mb-2">Create New Label</div>
                    <v-text-field
                        v-model="newLabel.name"
                        placeholder="Label name"
                        variant="outlined"
                        density="compact"
                        hide-details
                        class="mb-2"
                        autofocus
                    />
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        <v-btn
                            v-for="color in defaultColors"
                            :key="color"
                            icon
                            size="24"
                            :style="{ backgroundColor: color }"
                            :class="{ 'border-white': newLabel.color === color }"
                            @click="newLabel.color = color"
                        >
                            <v-icon v-if="newLabel.color === color" size="14" color="white">
                                mdi-check
                            </v-icon>
                        </v-btn>
                    </div>
                    <div class="d-flex gap-2">
                        <v-btn variant="text" size="small" @click="showNewForm = false; resetNewLabel()">
                            Cancel
                        </v-btn>
                        <v-btn 
                            color="primary" 
                            size="small"
                            :disabled="!newLabel.name.trim()"
                            @click="handleCreateLabel"
                        >
                            Create
                        </v-btn>
                    </div>
                </template>

                <v-btn 
                    v-else
                    variant="tonal" 
                    color="primary" 
                    block
                    prepend-icon="mdi-plus"
                    @click="showNewForm = true"
                >
                    Create New Label
                </v-btn>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.border-white {
    border: 2px solid white !important;
}
</style>
