<script setup>
/**
 * SidebarContent Component
 * 
 * Contains the sidebar navigation with workspaces and boards
 */
import { computed } from 'vue';

const props = defineProps({
    workspaces: {
        type: Array,
        default: () => []
    },
    activeBoard: {
        type: Object,
        default: null
    },
    teamTimeStats: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits([
    'create-workspace',
    'edit-workspace',
    'delete-workspace',
    'create-board',
    'edit-board',
    'delete-board',
    'select-board',
    'toggle-star',
    'open-activity-log'
]);

// Starred boards computed
const starredBoards = computed(() => {
    return props.workspaces.flatMap(w => w.boards?.filter(b => b.starred) || []);
});

// Get my time stats
const myTimeStats = computed(() => {
    return props.teamTimeStats.find(s => s.member?.id === 1) || { totalHours: 0, tasksCompleted: 0 };
});
</script>

<template>
    <v-list density="compact" nav class="pa-2">
        <v-list-item prepend-icon="mdi-view-dashboard-outline" title="Boards" rounded="lg" class="mb-1" />
        <v-list-item prepend-icon="mdi-file-document-outline" title="Templates" rounded="lg" class="mb-1" />
        <v-list-item prepend-icon="mdi-home-outline" title="Home" rounded="lg" class="mb-1" />

        <v-divider class="my-3" />

        <!-- Starred Boards -->
        <v-list-subheader class="text-medium-emphasis">Starred</v-list-subheader>
        <v-list-item 
            v-for="board in starredBoards" 
            :key="'starred-' + board.id" 
            rounded="lg"
            class="mb-1" 
            @click="$emit('select-board', board)"
        >
            <template #prepend>
                <div class="board-color-dot mr-3" :style="{ backgroundColor: board.color }" />
            </template>
            <v-list-item-title>{{ board.name }}</v-list-item-title>
            <template #append>
                <v-btn icon variant="text" size="x-small" @click.stop="$emit('toggle-star', board)">
                    <v-icon size="14" color="amber">mdi-star</v-icon>
                </v-btn>
            </template>
        </v-list-item>
        <v-list-item v-if="starredBoards.length === 0" class="text-medium-emphasis text-caption">
            No starred boards
        </v-list-item>

        <v-divider class="my-3" />

        <!-- Quick Actions -->
        <v-list-subheader class="text-medium-emphasis">Quick Actions</v-list-subheader>
        <v-list-item 
            rounded="lg" 
            class="mb-1" 
            prepend-icon="mdi-folder-plus-outline"
            @click="$emit('create-workspace')"
        >
            <v-list-item-title>Create Workspace</v-list-item-title>
        </v-list-item>
        <v-list-item 
            rounded="lg" 
            class="mb-1" 
            prepend-icon="mdi-plus-box-outline"
            @click="$emit('create-board', null)"
        >
            <v-list-item-title>Create Board</v-list-item-title>
        </v-list-item>
        <v-list-item 
            rounded="lg" 
            class="mb-1" 
            prepend-icon="mdi-history"
            @click="$emit('open-activity-log')"
        >
            <v-list-item-title>Activity Log</v-list-item-title>
        </v-list-item>

        <v-divider class="my-3" />

        <!-- My Time Stats -->
        <v-list-subheader class="text-medium-emphasis">My Time (This Week)</v-list-subheader>
        <v-sheet color="surface-variant" rounded="lg" class="pa-3 mx-2 mb-3">
            <div class="d-flex justify-space-between mb-2">
                <span class="text-caption text-medium-emphasis">Hours Logged</span>
                <span class="text-body-2 font-weight-bold text-primary">
                    {{ myTimeStats.totalHours || 0 }}h
                </span>
            </div>
            <div class="d-flex justify-space-between">
                <span class="text-caption text-medium-emphasis">Tasks Completed</span>
                <span class="text-body-2 font-weight-bold text-success">
                    {{ myTimeStats.tasksCompleted || 0 }}
                </span>
            </div>
        </v-sheet>

        <v-divider class="my-3" />

        <!-- Workspaces Header with Add Button -->
        <div class="d-flex align-center justify-space-between px-2 mb-2">
            <span class="text-caption text-medium-emphasis font-weight-bold">WORKSPACES</span>
            <v-tooltip text="Create Workspace" location="top">
                <template #activator="{ props: tooltipProps }">
                    <v-btn 
                        v-bind="tooltipProps" 
                        icon 
                        variant="text" 
                        size="x-small"
                        @click="$emit('create-workspace')"
                    >
                        <v-icon size="18">mdi-plus</v-icon>
                    </v-btn>
                </template>
            </v-tooltip>
        </div>

        <template v-for="workspace in workspaces" :key="workspace.id">
            <v-list-group>
                <template #activator="{ props: groupProps }">
                    <v-list-item v-bind="groupProps" rounded="lg">
                        <template #prepend>
                            <v-avatar size="24" color="surface-variant" rounded="sm">
                                <span class="text-caption">{{ workspace.name.charAt(0) }}</span>
                            </v-avatar>
                        </template>
                        <v-list-item-title>{{ workspace.name }}</v-list-item-title>
                        <template #append>
                            <v-menu>
                                <template #activator="{ props: menuProps }">
                                    <v-btn 
                                        icon 
                                        variant="text" 
                                        size="x-small" 
                                        v-bind="menuProps"
                                        @click.stop
                                    >
                                        <v-icon size="16">mdi-dots-vertical</v-icon>
                                    </v-btn>
                                </template>
                                <v-list density="compact" width="180">
                                    <v-list-item 
                                        prepend-icon="mdi-plus" 
                                        title="Add Board"
                                        @click="$emit('create-board', workspace)" 
                                    />
                                    <v-list-item 
                                        prepend-icon="mdi-pencil" 
                                        title="Edit Workspace"
                                        @click="$emit('edit-workspace', workspace)" 
                                    />
                                    <v-divider class="my-1" />
                                    <v-list-item 
                                        prepend-icon="mdi-delete" 
                                        title="Delete"
                                        class="text-error" 
                                        @click="$emit('delete-workspace', workspace)" 
                                    />
                                </v-list>
                            </v-menu>
                        </template>
                    </v-list-item>
                </template>

                <!-- Boards in Workspace -->
                <v-list-item 
                    v-for="board in workspace.boards" 
                    :key="board.id" 
                    rounded="lg"
                    :class="{ 'bg-primary-darken-1': activeBoard?.id === board.id }"
                    @click="$emit('select-board', board)"
                >
                    <template #prepend>
                        <div class="board-color-dot mr-3" :style="{ backgroundColor: board.color }" />
                    </template>
                    <v-list-item-title class="text-body-2">{{ board.name }}</v-list-item-title>
                    <template #append>
                        <v-btn 
                            icon 
                            variant="text" 
                            size="x-small" 
                            @click.stop="$emit('toggle-star', board)"
                            class="mr-1"
                        >
                            <v-icon size="14" :color="board.starred ? 'amber' : 'grey'">
                                {{ board.starred ? 'mdi-star' : 'mdi-star-outline' }}
                            </v-icon>
                        </v-btn>
                        <v-menu>
                            <template #activator="{ props: menuProps }">
                                <v-btn 
                                    icon 
                                    variant="text" 
                                    size="x-small" 
                                    v-bind="menuProps"
                                    @click.stop
                                >
                                    <v-icon size="14">mdi-dots-vertical</v-icon>
                                </v-btn>
                            </template>
                            <v-list density="compact" width="150">
                                <v-list-item 
                                    prepend-icon="mdi-pencil" 
                                    title="Edit"
                                    @click="$emit('edit-board', { board, workspace })" 
                                />
                                <v-list-item 
                                    prepend-icon="mdi-delete" 
                                    title="Delete" 
                                    class="text-error"
                                    @click="$emit('delete-board', { board, workspace })" 
                                />
                            </v-list>
                        </v-menu>
                    </template>
                </v-list-item>

                <!-- Add Board Button -->
                <v-list-item 
                    rounded="lg" 
                    class="text-medium-emphasis"
                    @click="$emit('create-board', workspace)"
                >
                    <template #prepend>
                        <v-icon size="18" class="mr-3">mdi-plus</v-icon>
                    </template>
                    <v-list-item-title class="text-body-2">Add Board</v-list-item-title>
                </v-list-item>
            </v-list-group>
        </template>
    </v-list>
</template>

<style scoped>
.board-color-dot {
    width: 20px;
    height: 16px;
    border-radius: 4px;
}

:deep(.v-list-group__items .v-list-item) {
    padding-inline-start: 20px !important;
}
</style>
