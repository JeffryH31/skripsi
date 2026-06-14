<script setup>
/**
 * KanbanBoard Component
 * 
 * Main kanban board with draggable lists and feature cards
 */
import { ref, computed } from 'vue';
import draggable from 'vuedraggable';
import FeatureCard from './FeatureCard.vue';

const props = defineProps({
    featureLists: {
        type: Array,
        required: true
    },
    teamMembers: {
        type: Array,
        default: () => []
    },
    hasActiveFilters: {
        type: Boolean,
        default: false
    },
    searchQuery: {
        type: String,
        default: ''
    }
});

const emit = defineEmits([
    'update:featureLists',
    'add-list',
    'rename-list',
    'delete-list',
    'add-feature',
    'open-feature',
    'move-feature'
]);

// Internal state for UI interactions
const editingListId = ref(null);
const editingListTitle = ref('');
const addingFeatureToList = ref(null);
const newFeatureTitle = ref('');
const isAddingList = ref(false);
const newListTitle = ref('');

// Local computed with v-model pattern
const localFeatureLists = computed({
    get: () => props.featureLists,
    set: (value) => emit('update:featureLists', value)
});

// Helper functions
const getVisibleFeaturesCount = (list) => {
    if (!props.hasActiveFilters && !props.searchQuery.trim()) {
        return list.features?.length || 0;
    }
    return list.features?.length || 0;
};

// List editing handlers
const startEditingList = (list) => {
    editingListId.value = list.id;
    editingListTitle.value = list.title;
};

const saveListTitle = (list) => {
    if (editingListTitle.value.trim() && editingListTitle.value !== list.title) {
        emit('rename-list', { listId: list.id, name: editingListTitle.value.trim() });
    }
    editingListId.value = null;
    editingListTitle.value = '';
};

const cancelEditingList = () => {
    editingListId.value = null;
    editingListTitle.value = '';
};

// Feature adding handlers
const startAddingFeature = (listId) => {
    addingFeatureToList.value = listId;
    newFeatureTitle.value = '';
};

const addFeature = (listId) => {
    if (newFeatureTitle.value.trim()) {
        emit('add-feature', { listId, title: newFeatureTitle.value.trim() });
        newFeatureTitle.value = '';
        addingFeatureToList.value = null;
    }
};

const cancelAddingFeature = () => {
    addingFeatureToList.value = null;
    newFeatureTitle.value = '';
};

// List adding handlers
const startAddingList = () => {
    isAddingList.value = true;
    newListTitle.value = '';
};

const addList = () => {
    if (newListTitle.value.trim()) {
        emit('add-list', newListTitle.value.trim());
        newListTitle.value = '';
        isAddingList.value = false;
    }
};

const cancelAddingList = () => {
    isAddingList.value = false;
    newListTitle.value = '';
};

// Delete list handler
const confirmDeleteList = (list) => {
    emit('delete-list', list);
};

// Open feature handler
const openFeature = (feature, list) => {
    emit('open-feature', feature, list);
};
</script>

<template>
    <div class="kanban-board pa-3">
        <div class="d-flex ga-3 lists-container">
            <!-- Feature Lists -->
            <draggable 
                v-model="localFeatureLists" 
                group="lists" 
                item-key="id"
                class="d-flex ga-3 align-start" 
                ghost-class="ghost-list" 
                :animation="150"
            >
                <template #item="{ element: list }">
                    <v-sheet color="surface" rounded="xl" class="kanban-list flex-shrink-0">
                        <!-- List Header -->
                        <div class="d-flex align-center justify-space-between pa-3 list-header">
                            <!-- Editable Title -->
                            <div v-if="editingListId === list.id" class="flex-grow-1 mr-2">
                                <v-text-field 
                                    v-model="editingListTitle"
                                    variant="outlined"
                                    density="compact" 
                                    hide-details 
                                    autofocus 
                                    bg-color="surface-variant"
                                    @blur="saveListTitle(list)"
                                    @keydown.enter="saveListTitle(list)"
                                    @keydown.esc="cancelEditingList" 
                                />
                            </div>
                            <h3 
                                v-else 
                                class="text-subtitle-1 font-weight-bold"
                                @dblclick="startEditingList(list)"
                            >
                                {{ list.title }}
                                <v-chip size="x-small" class="ml-2" variant="tonal">
                                    <template v-if="hasActiveFilters || searchQuery.trim()">
                                        {{ getVisibleFeaturesCount(list) }}/{{ list.features?.length || 0 }}
                                    </template>
                                    <template v-else>
                                        {{ list.features?.length || 0 }}
                                    </template>
                                </v-chip>
                            </h3>
                            <v-menu>
                                <template #activator="{ props: menuProps }">
                                    <v-btn 
                                        icon 
                                        variant="text" 
                                        size="x-small" 
                                        v-bind="menuProps"
                                    >
                                        <v-icon size="18">mdi-dots-horizontal</v-icon>
                                    </v-btn>
                                </template>
                                <v-list density="compact" width="180">
                                    <v-list-item 
                                        prepend-icon="mdi-plus" 
                                        title="Add Feature"
                                        @click="startAddingFeature(list.id)" 
                                    />
                                    <v-list-item 
                                        prepend-icon="mdi-pencil" 
                                        title="Rename List"
                                        @click="startEditingList(list)" 
                                    />
                                    <v-divider class="my-1" />
                                    <v-list-item 
                                        prepend-icon="mdi-delete" 
                                        title="Delete List"
                                        class="text-error" 
                                        @click="confirmDeleteList(list)" 
                                    />
                                </v-list>
                            </v-menu>
                        </div>

                        <!-- Features Container -->
                        <div class="features-container px-2 pb-2">
                            <draggable 
                                :list="list.features" 
                                group="features" 
                                item-key="id"
                                ghost-class="ghost-card" 
                                :animation="150"
                            >
                                <template #item="{ element: feature }">
                                    <FeatureCard 
                                        :feature="feature"
                                        :team-members="teamMembers"
                                        @click="openFeature(feature, list)"
                                    />
                                </template>
                            </draggable>

                            <!-- Add Feature Form -->
                            <div v-if="addingFeatureToList === list.id" class="pa-2">
                                <v-textarea 
                                    v-model="newFeatureTitle"
                                    placeholder="Enter feature name..."
                                    variant="outlined" 
                                    density="compact" 
                                    rows="2" 
                                    hide-details 
                                    autofocus
                                    bg-color="surface-variant" 
                                    class="mb-2"
                                    @keydown.enter.prevent="addFeature(list.id)"
                                    @keydown.esc="cancelAddingFeature" 
                                />
                                <div class="d-flex align-center ga-2">
                                    <v-btn 
                                        color="primary" 
                                        size="small" 
                                        class="text-none"
                                        @click="addFeature(list.id)"
                                    >
                                        Add feature
                                    </v-btn>
                                    <v-btn 
                                        icon 
                                        variant="text" 
                                        size="small" 
                                        @click="cancelAddingFeature"
                                    >
                                        <v-icon>mdi-close</v-icon>
                                    </v-btn>
                                </div>
                            </div>

                            <!-- Add Feature Button -->
                            <v-btn 
                                v-if="addingFeatureToList !== list.id" 
                                variant="text" 
                                block
                                class="justify-start text-none text-medium-emphasis pa-2"
                                @click="startAddingFeature(list.id)"
                            >
                                <v-icon size="18" class="mr-1">mdi-plus</v-icon>
                                Add a feature
                            </v-btn>
                        </div>
                    </v-sheet>
                </template>
            </draggable>

            <!-- Add List -->
            <div class="add-list-container">
                <v-btn 
                    v-if="!isAddingList" 
                    variant="tonal" 
                    class="text-none pa-3 add-list-btn"
                    @click="startAddingList"
                >
                    <v-icon size="18" class="mr-1">mdi-plus</v-icon>
                    Add another list
                </v-btn>
                <v-sheet v-else color="surface" rounded="xl" class="pa-2 add-list-form">
                    <v-text-field 
                        v-model="newListTitle"
                        placeholder="Enter list title..."
                        variant="outlined" 
                        density="compact" 
                        hide-details 
                        autofocus
                        bg-color="surface-variant" 
                        class="mb-2" 
                        @keydown.enter.prevent="addList"
                        @keydown.esc="cancelAddingList" 
                    />
                    <div class="d-flex align-center ga-2">
                        <v-btn 
                            color="primary" 
                            size="small" 
                            class="text-none" 
                            @click="addList"
                        >
                            Add list
                        </v-btn>
                        <v-btn 
                            icon 
                            variant="text" 
                            size="small" 
                            @click="cancelAddingList"
                        >
                            <v-icon>mdi-close</v-icon>
                        </v-btn>
                    </div>
                </v-sheet>
            </div>
        </div>
    </div>
</template>

<style scoped>
.kanban-board {
    flex: 1;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.kanban-board::-webkit-scrollbar {
    height: 8px;
}

.kanban-board::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
}

.lists-container {
    height: 100%;
    align-items: flex-start;
    min-width: max-content;
}

.kanban-list {
    width: 300px;
    min-width: 300px;
    max-height: calc(100vh - 180px);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

@media (max-width: 600px) {
    .kanban-list {
        width: 280px;
        min-width: 280px;
    }
}

.list-header {
    flex-shrink: 0;
}

.features-container {
    flex: 1;
    overflow-y: auto;
    min-height: 20px;
    -webkit-overflow-scrolling: touch;
}

.add-list-container {
    min-width: 300px;
    flex-shrink: 0;
    align-self: flex-start;
}

@media (max-width: 600px) {
    .add-list-container {
        min-width: 280px;
    }
}

.add-list-btn {
    min-height: 48px;
}

.add-list-form {
    width: 300px;
}

/* Drag Ghost Styles */
.ghost-list,
.ghost-card {
    opacity: 0.5;
    border: 2px dashed rgb(var(--v-theme-primary));
}

:deep(.sortable-chosen) {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3) !important;
}
</style>
