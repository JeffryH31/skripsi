<script setup>
/**
 * AddTimeModal Component
 * 
 * Modal for manually adding time to a task
 */
import { ref, watch } from 'vue';
import { useDisplay } from 'vuetify';

const { smAndDown } = useDisplay();

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    task: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['update:modelValue', 'save']);

const form = ref({
    hours: 0,
    minutes: 0,
    description: ''
});

watch(() => props.modelValue, (isOpen) => {
    if (isOpen) {
        form.value = {
            hours: 0,
            minutes: 30,
            description: ''
        };
    }
});

const totalHours = () => {
    return form.value.hours + (form.value.minutes / 60);
};

const handleSave = () => {
    const hours = totalHours();
    if (hours <= 0) return;
    emit('save', {
        taskId: props.task?.id,
        hours: hours,
        description: form.value.description
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
        :max-width="smAndDown ? undefined : 400"
        :fullscreen="smAndDown"
        :transition="smAndDown ? 'dialog-bottom-transition' : 'dialog-transition'"
    >
        <v-card color="surface">
            <v-card-title class="d-flex align-center pa-4">
                <v-icon start color="primary">mdi-clock-plus</v-icon>
                Add Time Entry
                <v-spacer />
                <v-btn icon variant="text" size="small" @click="handleClose">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-4">
                <p v-if="task" class="text-body-2 text-medium-emphasis mb-4">
                    Adding time to: <strong>{{ task.title }}</strong>
                </p>

                <div class="d-flex gap-3 mb-4">
                    <v-text-field
                        v-model.number="form.hours"
                        label="Hours"
                        type="number"
                        min="0"
                        variant="outlined"
                        bg-color="surface-variant"
                        hide-details
                    />
                    <v-text-field
                        v-model.number="form.minutes"
                        label="Minutes"
                        type="number"
                        min="0"
                        max="59"
                        variant="outlined"
                        bg-color="surface-variant"
                        hide-details
                    />
                </div>

                <v-textarea
                    v-model="form.description"
                    label="Description (optional)"
                    placeholder="What did you work on?"
                    variant="outlined"
                    bg-color="surface-variant"
                    rows="3"
                    hide-details
                />
            </v-card-text>
            <v-divider />
            <v-card-actions class="pa-4">
                <v-spacer />
                <v-btn variant="text" @click="handleClose">Cancel</v-btn>
                <v-btn 
                    color="primary" 
                    variant="flat" 
                    :disabled="totalHours() <= 0"
                    @click="handleSave"
                >
                    Add Time
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
