<script setup>
/**
 * TaskEditModal Component
 * 
 * Modal for editing task details
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
    },
    teamMembers: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['update:modelValue', 'save']);

const form = ref({
    title: '',
    description: '',
    priority: null,
    estimatedHours: null,
    assigneeId: null
});

const priorities = [
    { title: 'High', value: 'high', color: 'error' },
    { title: 'Medium', value: 'medium', color: 'warning' },
    { title: 'Low', value: 'low', color: 'success' }
];

watch(() => props.modelValue, (isOpen) => {
    if (isOpen && props.task) {
        form.value = {
            title: props.task.title || '',
            description: props.task.description || '',
            priority: props.task.priority || null,
            estimatedHours: props.task.estimatedHours || null,
            assigneeId: props.task.assigneeId || null
        };
    }
});

const handleSave = () => {
    if (!form.value.title.trim()) return;
    emit('save', {
        ...props.task,
        ...form.value
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
        :max-width="smAndDown ? undefined : 500"
        :fullscreen="smAndDown"
        :transition="smAndDown ? 'dialog-bottom-transition' : 'dialog-transition'"
    >
        <v-card color="surface">
            <v-card-title class="d-flex align-center pa-4">
                <v-icon start>mdi-pencil</v-icon>
                Edit Task
                <v-spacer />
                <v-btn icon variant="text" size="small" @click="handleClose">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-4">
                <v-text-field
                    v-model="form.title"
                    label="Task Title"
                    variant="outlined"
                    bg-color="surface-variant"
                    class="mb-4"
                    autofocus
                />

                <v-textarea
                    v-model="form.description"
                    label="Description"
                    variant="outlined"
                    bg-color="surface-variant"
                    rows="3"
                    class="mb-4"
                />

                <v-row>
                    <v-col cols="12" sm="6">
                        <v-select
                            v-model="form.priority"
                            :items="priorities"
                            item-title="title"
                            item-value="value"
                            label="Priority"
                            variant="outlined"
                            bg-color="surface-variant"
                            clearable
                        >
                            <template #item="{ item, props: itemProps }">
                                <v-list-item v-bind="itemProps">
                                    <template #prepend>
                                        <v-icon :color="item.raw.color" size="18">mdi-circle</v-icon>
                                    </template>
                                </v-list-item>
                            </template>
                        </v-select>
                    </v-col>
                    <v-col cols="12" sm="6">
                        <v-text-field
                            v-model.number="form.estimatedHours"
                            label="Estimated Hours"
                            type="number"
                            min="0"
                            step="0.5"
                            variant="outlined"
                            bg-color="surface-variant"
                        />
                    </v-col>
                </v-row>

                <v-select
                    v-model="form.assigneeId"
                    :items="teamMembers"
                    item-title="name"
                    item-value="id"
                    label="Assignee"
                    variant="outlined"
                    bg-color="surface-variant"
                    clearable
                >
                    <template #item="{ item, props: itemProps }">
                        <v-list-item v-bind="itemProps">
                            <template #prepend>
                                <v-avatar size="24" :color="item.raw.avatarColor || 'primary'">
                                    <span style="font-size: 10px;">
                                        {{ item.raw.name?.charAt(0).toUpperCase() }}
                                    </span>
                                </v-avatar>
                            </template>
                        </v-list-item>
                    </template>
                </v-select>
            </v-card-text>
            <v-divider />
            <v-card-actions class="pa-4">
                <v-spacer />
                <v-btn variant="text" @click="handleClose">Cancel</v-btn>
                <v-btn 
                    color="primary" 
                    variant="flat"
                    :disabled="!form.title.trim()"
                    @click="handleSave"
                >
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
