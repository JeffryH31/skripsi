<script setup>
import { ref, computed, watch } from 'vue';
import { useDisplay } from 'vuetify';

const { smAndDown } = useDisplay();

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    itemType: { type: String, default: 'item' },
    itemName: { type: String, default: '' },
    warningMessage: { type: String, default: '' },
    confirmWord: { type: String, default: 'delete' },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const typed = ref('');

// Reset the typed confirmation whenever the dialog opens or closes.
watch(
    () => props.modelValue,
    () => { typed.value = ''; }
);

const matches = computed(
    () => typed.value.trim().toLowerCase() === props.confirmWord.trim().toLowerCase()
);

const defaultWarning = computed(() => {
    switch (props.itemType) {
        case 'workspace':
            return 'All spaces, projects, tasks, and time entries inside it will be permanently removed.';
        case 'space':
            return 'All folders, projects, and tasks inside it will be permanently removed.';
        case 'project':
            return 'All tasks and subtasks inside it will be permanently removed.';
        case 'task':
            return 'Its subtasks and time entries will be permanently removed.';
        case 'subtask':
            return 'Its time entries will be permanently removed.';
        default:
            return 'This action cannot be undone.';
    }
});

const handleConfirm = () => {
    if (!matches.value || props.loading) return;
    emit('confirm');
};

const handleClose = () => {
    emit('update:modelValue', false);
};
</script>

<template>
    <v-dialog
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
        :max-width="smAndDown ? undefined : 460"
        :fullscreen="smAndDown"
        :transition="smAndDown ? 'dialog-bottom-transition' : 'dialog-transition'"
        persistent
    >
        <v-card class="delete-dialog" rounded="xl" elevation="12">
            <!-- Header -->
            <div class="delete-dialog__header">
                <div class="delete-dialog__icon">
                    <v-icon color="error" size="22">mdi-trash-can-outline</v-icon>
                </div>
                <div class="delete-dialog__heading">
                    <h3 class="delete-dialog__title">Delete {{ itemType }}</h3>
                    <span class="delete-dialog__subtitle">This action can’t be undone</span>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    density="comfortable"
                    :disabled="loading"
                    @click="handleClose"
                />
            </div>

            <div class="delete-dialog__body">
                <!-- Target item -->
                <div v-if="itemName" class="delete-dialog__target">
                    <v-icon size="16" color="error" class="mr-2">mdi-alert-circle-outline</v-icon>
                    <span class="delete-dialog__target-name">{{ itemName }}</span>
                </div>

                <p class="delete-dialog__warning">
                    {{ warningMessage || defaultWarning }}
                </p>

                <!-- Confirmation input -->
                <label class="delete-dialog__label">
                    Type <span class="delete-dialog__word">{{ confirmWord }}</span> to confirm
                </label>
                <v-text-field
                    v-model="typed"
                    :placeholder="confirmWord"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    hide-details
                    autofocus
                    autocomplete="off"
                    spellcheck="false"
                    :append-inner-icon="matches ? 'mdi-check-circle' : undefined"
                    :base-color="matches ? 'error' : undefined"
                    :color="matches ? 'error' : undefined"
                    @keydown.enter="handleConfirm"
                />
            </div>

            <!-- Actions -->
            <div class="delete-dialog__actions">
                <v-btn
                    variant="text"
                    rounded="lg"
                    class="text-none"
                    :disabled="loading"
                    @click="handleClose"
                >
                    Cancel
                </v-btn>
                <v-btn
                    color="error"
                    variant="flat"
                    rounded="lg"
                    class="text-none px-5"
                    prepend-icon="mdi-trash-can-outline"
                    :disabled="!matches"
                    :loading="loading"
                    @click="handleConfirm"
                >
                    Delete {{ itemType }}
                </v-btn>
            </div>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.delete-dialog {
    background: rgb(var(--v-theme-surface));
    overflow: hidden;
}

.delete-dialog__header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 20px 16px;
}

.delete-dialog__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    border-radius: 12px;
    background: rgba(var(--v-theme-error), 0.12);
}

.delete-dialog__heading {
    flex: 1;
    min-width: 0;
}

.delete-dialog__title {
    font-size: 1.0625rem;
    font-weight: 700;
    line-height: 1.3;
    text-transform: capitalize;
    color: rgb(var(--v-theme-on-surface));
}

.delete-dialog__subtitle {
    font-size: 0.75rem;
    color: rgba(var(--v-theme-on-surface), 0.55);
}

.delete-dialog__body {
    padding: 4px 20px 8px;
}

.delete-dialog__target {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    margin-bottom: 12px;
    border-radius: 10px;
    background: rgba(var(--v-theme-error), 0.08);
    border: 1px solid rgba(var(--v-theme-error), 0.18);
}

.delete-dialog__target-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: rgb(var(--v-theme-on-surface));
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.delete-dialog__warning {
    font-size: 0.85rem;
    line-height: 1.5;
    color: rgba(var(--v-theme-on-surface), 0.6);
    margin-bottom: 18px;
}

.delete-dialog__label {
    display: block;
    font-size: 0.8rem;
    color: rgba(var(--v-theme-on-surface), 0.7);
    margin-bottom: 8px;
}

.delete-dialog__word {
    font-weight: 700;
    color: rgb(var(--v-theme-error));
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.delete-dialog__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding: 16px 20px 20px;
}
</style>
