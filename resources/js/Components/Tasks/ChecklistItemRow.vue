<script setup>
import { ref, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { useSnackbar } from '@/composables/useSnackbar';

const { showSnackbar } = useSnackbar();

// Allows recursive self-reference in template
defineOptions({ name: 'ChecklistItemRow' });

const props = defineProps({
    item: { type: Object, default: null },
    // [workspaceId, spaceId, listId, taskId, subtaskId]
    routeParams: { type: Array, required: true },
    canEdit: { type: Boolean, default: false },
    indentLevel: { type: Number, default: 0 },
});

const emit = defineEmits(['reloaded']);

const isEditing = ref(false);
const editName = ref('');
const editInputRef = ref(null);

const showAddChild = ref(false);
const newChildName = ref('');
const childInputRef = ref(null);

const loading = ref(false);

// Helpers 
const itemRoute = (name) => route(name, [...props.routeParams, props.item.id]);
const parentRoute = (name) => route(name, props.routeParams);

// Toggle 
const toggle = () => {
    if (!props.canEdit || loading.value) return;
    loading.value = true;
    router.post(itemRoute('tasks.subtasks.checklist.toggle'), {}, {
        preserveScroll: true,
        onSuccess: () => emit('reloaded'),
        onError: () => showSnackbar('Failed to update checklist item', 'error'),
        onFinish: () => { loading.value = false; },
    });
};

// Edit name 
const startEdit = () => {
    if (!props.canEdit) return;
    editName.value = props.item.name;
    isEditing.value = true;
    nextTick(() => editInputRef.value?.focus());
};

const cancelEdit = () => { isEditing.value = false; };

const saveEdit = () => {
    const trimmed = editName.value.trim();
    if (!trimmed || trimmed === props.item.name) { cancelEdit(); return; }
    loading.value = true;
    router.patch(itemRoute('tasks.subtasks.checklist.update'), { name: trimmed }, {
        preserveScroll: true,
        onSuccess: () => { isEditing.value = false; emit('reloaded'); },
        onError: () => showSnackbar('Failed to rename checklist item', 'error'),
        onFinish: () => { loading.value = false; },
    });
};

// Delete 
const deleteItem = () => {
    loading.value = true;
    router.delete(itemRoute('tasks.subtasks.checklist.destroy'), {
        preserveScroll: true,
        onSuccess: () => emit('reloaded'),
        onError: () => showSnackbar('Failed to delete checklist item', 'error'),
        onFinish: () => { loading.value = false; },
    });
};

// Add child 
const openAddChild = () => {
    showAddChild.value = true;
    nextTick(() => childInputRef.value?.focus());
};

const addChild = () => {
    const trimmed = newChildName.value.trim();
    if (!trimmed) return;
    loading.value = true;
    router.post(parentRoute('tasks.subtasks.checklist.store'), { name: trimmed, parent_id: props.item.id }, {
        preserveScroll: true,
        onSuccess: () => { newChildName.value = ''; showAddChild.value = false; emit('reloaded'); },
        onError: () => showSnackbar('Failed to add checklist item', 'error'),
        onFinish: () => { loading.value = false; },
    });
};
</script>

<template>
    <div>
        <!-- Main row  -->
        <div class="checklist-row" :style="{ paddingLeft: (12 + indentLevel * 20) + 'px' }">
            <!-- Checkbox -->
            <button type="button" class="checklist-checkbox"
                :class="{ 'checklist-checkbox--checked': item.is_checked, 'checklist-checkbox--disabled': !canEdit || loading }"
                :disabled="!canEdit || loading" @click="toggle">
                <v-icon :color="item.is_checked ? 'success' : '#666'" size="17">
                    {{ item.is_checked ? 'mdi-checkbox-marked' : 'mdi-checkbox-blank-outline' }}
                </v-icon>
            </button>

            <!-- Name / inline edit -->
            <div class="checklist-name-wrap" @dblclick="startEdit">
                <input v-if="isEditing" ref="editInputRef" v-model="editName" class="checklist-name-input"
                    @blur="saveEdit" @keydown.enter.prevent="saveEdit" @keydown.escape="cancelEdit" />
                <span v-else class="checklist-item-name" :class="{ 'checklist-item-done': item.is_checked }">
                    {{ item.name }}
                </span>
            </div>

            <!-- Hover actions -->
            <div class="checklist-row-actions" v-if="canEdit">
                <v-btn v-if="(item.depth ?? 0) < 6" icon size="x-small" variant="text" title="Add sub-item"
                    @click.stop="openAddChild">
                    <v-icon size="13">mdi-subdirectory-arrow-right</v-icon>
                </v-btn>
                <v-btn icon size="x-small" variant="text" title="Delete item" :disabled="loading"
                    @click.stop="deleteItem">
                    <v-icon size="13" color="error">mdi-close</v-icon>
                </v-btn>
            </div>
        </div>

        <!-- Add-child input  -->
        <div v-if="showAddChild" class="checklist-add-child"
            :style="{ paddingLeft: (12 + (indentLevel + 1) * 20) + 'px' }">
            <v-icon size="16" color="grey">mdi-checkbox-blank-outline</v-icon>
            <input ref="childInputRef" v-model="newChildName" class="checklist-name-input" placeholder="Add sub-item…"
                @keydown.enter.prevent="addChild" @keydown.escape="showAddChild = false; newChildName = ''" />
            <v-btn size="x-small" color="primary" variant="flat" :disabled="!newChildName.trim() || loading"
                @click="addChild">Add</v-btn>
        </div>

        <!-- Children (recursive) -->
        <ChecklistItemRow v-for="child in item.children" :key="child.id" :item="child" :route-params="routeParams"
            :can-edit="canEdit" :indent-level="indentLevel + 1" @reloaded="emit('reloaded')" />
    </div>
</template>

<style scoped>
.checklist-row {
    display: flex;
    align-items: center;
    gap: 6px;
    padding-top: 3px;
    padding-bottom: 3px;
    padding-right: 10px;
    min-height: 30px;
    border-radius: 4px;
    transition: background-color 0.1s;
}

.checklist-row:hover {
    background-color: rgba(255, 255, 255, 0.04);
}

.checklist-row:hover .checklist-row-actions {
    opacity: 1;
}

.checklist-checkbox {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    flex-shrink: 0;
    line-height: 1;
}

.checklist-checkbox--disabled {
    cursor: default;
    opacity: 0.5;
}

.checklist-name-wrap {
    flex: 1;
    min-width: 0;
}

.checklist-item-name {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.82);
    word-break: break-word;
    line-height: 1.4;
}

.checklist-item-done {
    text-decoration: line-through;
    color: rgba(255, 255, 255, 0.3);
}

.checklist-name-input {
    width: 100%;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.9);
    outline: none;
}

.checklist-name-input:focus {
    border-color: rgba(99, 102, 241, 0.6);
}

.checklist-row-actions {
    display: flex;
    align-items: center;
    gap: 2px;
    opacity: 0;
    transition: opacity 0.12s;
    flex-shrink: 0;
}

.checklist-add-child {
    display: flex;
    align-items: center;
    gap: 6px;
    padding-top: 3px;
    padding-bottom: 3px;
    padding-right: 10px;
}
</style>
