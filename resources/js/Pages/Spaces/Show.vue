<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import ColorPicker from '@/Components/ColorPicker.vue';
import DeleteConfirmDialog from '@/Components/DeleteConfirmDialog.vue';
import { useSnackbar } from '@/composables/useSnackbar';
import { normalizeHexColor } from '@/utils/color';

const { showSnackbar } = useSnackbar();

const props = defineProps({
    workspace: { type: Object, default: null },
    space: { type: Object, default: null },
    statistics: { type: Object, default: null },
    productsByStatus: { type: Object, default: null },
    canManageSpace: { type: Boolean, default: false },
    canManageWorkspace: { type: Boolean, default: false },
});

// Create folder dialog
const showCreateFolder = ref(false);
const newFolderName = ref('');

// Create project dialog
const showCreateList = ref(false);
const newListName = ref('');
const selectedFolderId = ref(null);

const createFolder = () => {
    if (!newFolderName.value.trim()) return;

    router.post(
        route('folders.store', [props.workspace.id, props.space.id]),
        { name: newFolderName.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                newFolderName.value = '';
                showCreateFolder.value = false;
            }
        }
    );
};

const createList = () => {
    if (!newListName.value.trim()) return;

    router.post(
        route('projects.store', [props.workspace.id, props.space.id]),
        {
            name: newListName.value.trim(),
            folder_id: selectedFolderId.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newListName.value = '';
                selectedFolderId.value = null;
                showCreateList.value = false;
            }
        }
    );
};

// Toggle folder collapse
const collapsedFolders = ref({});
const toggleFolder = (folderId) => {
    collapsedFolders.value[folderId] = !collapsedFolders.value[folderId];
};

// Edit space dialog
const showEditSpace = ref(false);
const editSpaceName = ref('');
const editSpaceColor = ref('');

const openEditSpace = () => {
    editSpaceName.value = props.space.name;
    editSpaceColor.value = props.space.color || '#6366F1';
    showEditSpace.value = true;
};

const updateSpace = () => {
    if (!editSpaceName.value.trim()) return;

    router.patch(
        route('spaces.update', [props.workspace.id, props.space.id]),
        {
            name: editSpaceName.value.trim(),
            color: normalizeHexColor(editSpaceColor.value),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showEditSpace.value = false;
            }
        }
    );
};

// Delete space dialog
const showDeleteSpace = ref(false);
const confirmDeleteSpace = () => {
    router.delete(
        route('spaces.destroy', [props.workspace.id, props.space.id]),
        {
            onSuccess: () => {
                router.visit(route('dashboard'));
            }
        }
    );
};

// Edit folder dialog
const showEditFolder = ref(false);
const editingFolder = ref(null);
const editFolderName = ref('');

const openEditFolder = (folder) => {
    editingFolder.value = folder;
    editFolderName.value = folder.name;
    showEditFolder.value = true;
};

const updateFolder = () => {
    if (!editFolderName.value.trim() || !editingFolder.value) return;

    router.patch(
        route('folders.update', [props.workspace.id, props.space.id, editingFolder.value.id]),
        { name: editFolderName.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                showEditFolder.value = false;
                editingFolder.value = null;
            }
        }
    );
};

// Delete folder dialog
const showDeleteFolder = ref(false);
const deletingFolder = ref(null);

const openDeleteFolder = (folder) => {
    deletingFolder.value = folder;
    showDeleteFolder.value = true;
};

const confirmDeleteFolder = () => {
    if (!deletingFolder.value) return;

    router.delete(
        route('folders.destroy', [props.workspace.id, props.space.id, deletingFolder.value.id]),
        {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteFolder.value = false;
                deletingFolder.value = null;
            }
        }
    );
};

// Move list to folder
const showMoveList = ref(false);
const movingList = ref(null);
const targetFolderId = ref(null);

const openMoveList = (list) => {
    movingList.value = list;
    targetFolderId.value = list.folder_id;
    showMoveList.value = true;
};

const moveListToFolder = () => {
    if (!movingList.value) return;

    router.post(
        route('projects.move-to-folder', [props.workspace.id, props.space.id, movingList.value.id]),
        { folder_id: targetFolderId.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showMoveList.value = false;
                movingList.value = null;
            }
        }
    );
};

// Edit list dialog
const showEditList = ref(false);
const editingList = ref(null);
const editListName = ref('');

const openEditList = (list) => {
    editingList.value = list;
    editListName.value = list.name;
    showEditList.value = true;
};

const updateList = () => {
    if (!editListName.value.trim() || !editingList.value) return;

    router.patch(
        route('projects.update', [props.workspace.id, props.space.id, editingList.value.id]),
        { name: editListName.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                showEditList.value = false;
                editingList.value = null;
            }
        }
    );
};

// Delete list dialog
const showDeleteList = ref(false);
const deletingList = ref(null);

const openDeleteList = (list) => {
    deletingList.value = list;
    showDeleteList.value = true;
};

const confirmDeleteList = () => {
    if (!deletingList.value) return;

    router.delete(
        route('projects.destroy', [props.workspace.id, props.space.id, deletingList.value.id]),
        {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteList.value = false;
                deletingList.value = null;
            }
        }
    );
};

// Duplicate list
const duplicateList = (list) => {
    router.post(
        route('projects.duplicate', [props.workspace.id, props.space.id, list.id]),
        {},
        { preserveScroll: true }
    );
};

// Hierarchy drag-and-drop (move project between folder/root)
const draggedList = ref(null);
const dragOverFolder = ref(null);

const resetDragState = () => {
    draggedList.value = null;
    dragOverFolder.value = null;
};

const handleDragStart = (event, list) => {
    draggedList.value = list;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(list.id));
};

const handleDragOver = (event, folderId = null) => {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
    dragOverFolder.value = folderId;
};

const handleDragLeave = (_event, folderId = null) => {
    if (dragOverFolder.value === folderId) {
        dragOverFolder.value = null;
    }
};

const handleDrop = (event, targetFolderId = null) => {
    event.preventDefault();

    const list = draggedList.value;
    if (!list) {
        resetDragState();
        return;
    }

    // No-op when dropped to the same folder/root
    if (list.folder_id === targetFolderId) {
        resetDragState();
        return;
    }

    router.post(
        route('projects.move-to-folder', [props.workspace.id, props.space.id, list.id]),
        { folder_id: targetFolderId },
        {
            preserveScroll: true,
            onSuccess: () => {
            },
            onError: () => {
                showSnackbar('Failed to move project', 'error');
            },
            onFinish: () => {
                resetDragState();
            },
        }
    );
};

// Available folders for moving lists
const availableFolders = computed(() => {
    return [
        { id: null, name: 'No Folder (Root)' },
        ...(props.space?.folders || [])
    ];
});

// View mode: 'hierarchy' (folders + projects) or 'board' (kanban by status)
const viewMode = ref('hierarchy');

// Hierarchy search
const hierarchySearch = ref('');

const filteredFolders = computed(() => {
    const q = hierarchySearch.value.trim().toLowerCase();
    if (!q) return props.space?.folders || [];
    return (props.space?.folders || []).map(folder => ({
        ...folder,
        lists: (folder.projects || []).filter(l => l.name.toLowerCase().includes(q)),
    })).filter(folder => folder.name.toLowerCase().includes(q) || folder.projects.length > 0);
});

const filteredListsWithoutFolder = computed(() => {
    const q = hierarchySearch.value.trim().toLowerCase();
    if (!q) return props.space?.projects_without_folder || [];
    return (props.space?.projects_without_folder || []).filter(l => l.name.toLowerCase().includes(q));
});

// Project kanban board state
const localProductsByStatus = ref({});

const initProductsByStatus = () => {
    const result = {};
    const statuses = props.space?.statuses || [];
    statuses.forEach(s => {
        result[s.id] = Array.isArray(props.productsByStatus?.[s.id])
            ? [...props.productsByStatus[s.id]]
            : [];
    });
    return result;
};

watch(() => props.productsByStatus, () => {
    localProductsByStatus.value = initProductsByStatus();
}, { deep: true, immediate: true });

// Board drag-and-drop state
const boardDraggedItem = ref(null);      // { element, fromStatusId }
const boardDragOverStatus = ref(null);   // status.id being hovered

const boardDragStart = (event, element, fromStatusId) => {
    boardDraggedItem.value = { element, fromStatusId };
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(element.id));
};

const boardDragOver = (event, statusId) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    boardDragOverStatus.value = statusId;
};

const boardDragLeave = (statusId) => {
    if (boardDragOverStatus.value === statusId) {
        boardDragOverStatus.value = null;
    }
};

const boardDrop = (event, toStatusId) => {
    event.preventDefault();
    boardDragOverStatus.value = null;

    const dragged = boardDraggedItem.value;
    boardDraggedItem.value = null;

    if (!dragged || dragged.fromStatusId === toStatusId) return;

    // Optimistic UI update
    const fromCol = localProductsByStatus.value[dragged.fromStatusId] || [];
    const toCol = localProductsByStatus.value[toStatusId] || [];
    const idx = fromCol.findIndex(p => p.id === dragged.element.id);
    if (idx === -1) return;
    const [moved] = fromCol.splice(idx, 1);
    toCol.push(moved);

    router.patch(
        route('projects.change-status', [props.workspace.id, props.space.id, dragged.element.id]),
        { status_id: toStatusId },
        { preserveScroll: true }
    );
};

</script>

<template>
    <MainLayout :title="space?.name || 'Space'">
        <div class="space-page">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <div class="flex items-center gap-2 text-sm mb-4">
                    <v-icon size="16">mdi-view-dashboard</v-icon>
                    <a :href="route('dashboard')" class="text-gray-400 hover:text-white">Dashboard</a>
                    <v-icon size="16" class="text-gray-600">mdi-chevron-right</v-icon>
                    <a :href="route('workspaces.show', workspace.id)"
                        class="flex items-center gap-2 text-gray-400 hover:text-white">
                        <div class="w-4 h-4 rounded flex items-center justify-center text-white text-xs"
                            :style="{ backgroundColor: workspace?.color || '#6366F1' }">
                            {{ workspace?.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <span>{{ workspace?.name }}</span>
                    </a>
                    <v-icon size="16" class="text-gray-600">mdi-chevron-right</v-icon>
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 rounded flex items-center justify-center text-white text-xs"
                            :style="{ backgroundColor: space?.color || '#6366F1' }">
                            <v-icon size="10" color="white">mdi-folder</v-icon>
                        </div>
                        <span class="font-medium text-white">{{ space?.name }}</span>
                    </div>
                </div>
            </div>

            <!-- Space Header -->
            <div class="space-header">
                <div class="flex items-center gap-4">
                    <div class="space-icon" :style="{ backgroundColor: space?.color || '#6366F1' }">
                        <v-icon color="white">mdi-folder-outline</v-icon>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">{{ space?.name }}</h1>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <v-btn v-if="space?.is_starred" icon variant="text"
                        @click="router.post(route('spaces.star', [workspace.id, space.id]))">
                        <v-icon color="warning">mdi-star</v-icon>
                    </v-btn>
                    <v-btn v-else icon variant="text"
                        @click="router.post(route('spaces.star', [workspace.id, space.id]))">
                        <v-icon>mdi-star-outline</v-icon>
                    </v-btn>

                    <v-menu>
                        <template v-slot:activator="{ props: menuProps }">
                            <v-btn v-bind="menuProps" icon variant="text">
                                <v-icon>mdi-dots-horizontal</v-icon>
                            </v-btn>
                        </template>
                        <v-card color="surface">
                            <v-list density="compact">
                                <v-list-item prepend-icon="mdi-shield-home-outline" title="Space Access"
                                    @click="router.visit(route('spaces.settings', [workspace.id, space.id]))"
                                    class="px-4" />
                                <v-list-item v-if="canManageSpace" prepend-icon="mdi-pencil-outline" title="Edit Space"
                                    @click="openEditSpace" class="px-4" />
                                <template v-if="canManageWorkspace">
                                    <v-divider />
                                    <v-list-item prepend-icon="mdi-delete-outline" title="Delete Space"
                                        class="text-error px-4" @click="showDeleteSpace = true" />
                                </template>
                            </v-list>
                        </v-card>
                    </v-menu>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-row">
                <v-card variant="outlined" rounded="lg" class="stat-card">
                    <v-card-text class="pa-4">
                        <div class="text-3xl font-bold">{{ statistics?.total_tasks || 0 }}</div>
                        <div class="text-sm text-gray-500">Total Tasks</div>
                    </v-card-text>
                </v-card>
                <v-card variant="outlined" rounded="lg" class="stat-card">
                    <v-card-text class="pa-4">
                        <div class="text-3xl font-bold text-success">{{ statistics?.completed_tasks || 0 }}</div>
                        <div class="text-sm text-gray-500">Completed</div>
                    </v-card-text>
                </v-card>
                <v-card variant="outlined" rounded="lg" class="stat-card">
                    <v-card-text class="pa-4">
                        <div class="text-3xl font-bold text-warning">{{ statistics?.in_progress_tasks || 0 }}</div>
                        <div class="text-sm text-gray-500">In Progress</div>
                    </v-card-text>
                </v-card>
                <v-card variant="outlined" rounded="lg" class="stat-card">
                    <v-card-text class="pa-4">
                        <div class="text-3xl font-bold text-error">{{ statistics?.overdue_tasks || 0 }}</div>
                        <div class="text-sm text-gray-500">Overdue</div>
                    </v-card-text>
                </v-card>
            </div>

            <!-- Actions -->
            <div class="actions-row">
                <v-btn-toggle v-model="viewMode" mandatory density="compact" variant="outlined" divided>
                    <v-btn value="hierarchy" size="small">
                        <v-icon start size="16">mdi-file-tree</v-icon>
                        Hierarchy
                    </v-btn>
                    <v-btn value="board" size="small">
                        <v-icon start size="16">mdi-view-column</v-icon>
                        Board
                    </v-btn>
                </v-btn-toggle>

                <v-spacer />

                <!-- Hierarchy Search -->
                <v-text-field v-if="viewMode === 'hierarchy'" v-model="hierarchySearch"
                    placeholder="Search projects..." prepend-inner-icon="mdi-magnify" variant="outlined"
                    density="compact" hide-details clearable style="max-width: 320px;" />

                <v-btn v-if="canManageSpace" color="primary" @click="showCreateList = true">
                    <v-icon start>mdi-plus</v-icon>
                    New Project
                </v-btn>
                <v-btn v-if="canManageSpace" variant="outlined" @click="showCreateFolder = true">
                    <v-icon start>mdi-folder-plus-outline</v-icon>
                    New Folder
                </v-btn>
            </div>

            <!-- Board View (Project Kanban) -->
            <div v-if="viewMode === 'board'" class="project-board">
                <div class="board-columns">
                    <div v-for="status in space?.statuses" :key="status.id" class="board-column"
                        :class="{ 'board-column--drag-over': boardDragOverStatus === status.id }"
                        @dragover="boardDragOver($event, status.id)" @dragleave="boardDragLeave(status.id)"
                        @drop="boardDrop($event, status.id)">
                        <!-- Column Header -->
                        <div class="board-column__header">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: status.color }" />
                                <span class="font-medium text-sm">{{ status.name }}</span>
                                <span class="text-xs text-gray-500 ml-1">{{ (localProductsByStatus[status.id] ||
                                    []).length
                                }}</span>
                            </div>
                        </div>

                        <!-- Draggable Products -->
                        <div class="board-column__content">
                            <div class="board-column__list">
                                <div v-for="element in (localProductsByStatus[status.id] || [])" :key="element.id"
                                    class="project-card"
                                    :class="{ 'project-card--dragging': boardDraggedItem?.element?.id === element.id }"
                                    :draggable="canManageSpace" @dragstart="boardDragStart($event, element, status.id)"
                                    @dragend="boardDraggedItem = null"
                                    @click="router.visit(route('projects.show', [workspace.id, space.id, element.id]))">
                                    <div class="project-card__status-bar" :style="{ backgroundColor: status.color }" />
                                    <div class="project-card__body">
                                        <div class="project-card__name">{{ element.name }}</div>
                                        <div class="project-card__meta">
                                            <span v-if="element.folder" class="project-card__folder">
                                                <v-icon size="12">mdi-folder-outline</v-icon>
                                                {{ element.folder.name }}
                                            </span>
                                            <span class="project-card__tasks">
                                                <v-icon size="12">mdi-checkbox-marked-outline</v-icon>
                                                {{ element.tasks_count || 0 }} tasks
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="!(localProductsByStatus[status.id] || []).length"
                                    class="board-column__empty">
                                    No projects
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hierarchy View (Content) -->
            <div v-else class="space-content">
                <!-- Folders -->
                <div v-for="folder in filteredFolders" :key="folder.id" class="folder-item">
                    <div class="folder-header" @click="toggleFolder(folder.id)">
                        <div class="flex items-center gap-2">
                            <v-icon size="20">
                                {{ collapsedFolders[folder.id] ? 'mdi-chevron-right' : 'mdi-chevron-down' }}
                            </v-icon>
                            <v-icon size="20" color="warning">mdi-folder</v-icon>
                            <span class="font-medium">{{ folder.name }}</span>
                            <span class="text-gray-500 text-sm">({{ folder.projects?.length || 0 }} projects)</span>
                        </div>
                        <div class="folder-actions">
                            <v-btn v-if="canManageSpace" icon variant="text" size="x-small"
                                @click.stop="selectedFolderId = folder.id; showCreateList = true">
                                <v-icon size="16">mdi-plus</v-icon>
                            </v-btn>
                            <v-btn v-if="canManageSpace" icon variant="text" size="x-small"
                                @click.stop="openEditFolder(folder)">
                                <v-icon size="16">mdi-pencil</v-icon>
                            </v-btn>
                            <v-btn v-if="canManageSpace" icon variant="text" size="x-small"
                                @click.stop="openDeleteFolder(folder)">
                                <v-icon size="16" color="error">mdi-delete</v-icon>
                            </v-btn>
                        </div>
                    </div>

                    <div v-if="!collapsedFolders[folder.id] || hierarchySearch.trim()" class="folder-lists" :class="{
                        'folder-lists--empty': !(folder.projects?.length),
                        'drag-over': draggedList !== null && dragOverFolder === folder.id
                    }" @dragover="handleDragOver($event, folder.id)" @dragleave="handleDragLeave($event, folder.id)"
                        @drop="handleDrop($event, folder.id)">
                        <div v-for="list in folder.projects" :key="list.id" class="list-item" :draggable="canManageSpace"
                            @dragstart="handleDragStart($event, list)"
                            @click="router.visit(route('projects.show', [workspace.id, space.id, list.id]))">
                            <div class="flex items-center gap-3">
                                <v-icon v-if="canManageSpace" size="18" class="drag-handle cursor-move"
                                    @click.stop>mdi-drag</v-icon>
                                <v-icon size="18">mdi-package-variant-closed</v-icon>
                                <span>{{ list.name }}</span>
                            </div>
                            <div class="list-meta">
                                <span class="text-gray-500 text-sm">{{ list.tasks_count || 0 }} tasks</span>
                                <v-btn v-if="canManageSpace" icon variant="text" size="x-small"
                                    @click.stop="openMoveList(list)">
                                    <v-icon size="16">mdi-folder-move-outline</v-icon>
                                </v-btn>
                                <v-btn v-if="canManageSpace" icon variant="text" size="x-small"
                                    @click.stop="openEditList(list)">
                                    <v-icon size="16">mdi-pencil-outline</v-icon>
                                </v-btn>
                                <v-btn v-if="canManageSpace" icon variant="text" size="x-small"
                                    @click.stop="openDeleteList(list)">
                                    <v-icon size="16" color="error">mdi-delete-outline</v-icon>
                                </v-btn>
                                <v-btn v-if="canManageSpace" icon variant="text" size="x-small"
                                    @click.stop="duplicateList(list)">
                                    <v-icon size="16">mdi-content-copy</v-icon>
                                </v-btn>
                                <v-btn icon variant="text" size="x-small"
                                    @click.stop="router.visit(route('projects.settings', [workspace.id, space.id, list.id]))">
                                    <v-icon size="16">mdi-account-cog-outline</v-icon>
                                </v-btn>
                            </div>
                        </div>

                        <div v-if="!folder.projects?.length" class="folder-drop-hint">
                            <span>Empty folder</span>
                        </div>
                    </div>
                </div>

                <!-- Lists without folder -->
                <div v-if="filteredListsWithoutFolder.length || filteredFolders.length" class="root-lists-zone" :class="{
                    'drag-over': draggedList !== null && dragOverFolder === null,
                    'root-lists-zone--empty': !space?.projects_without_folder?.length
                }" @dragover="handleDragOver($event, null)" @dragleave="handleDragLeave($event, null)"
                    @drop="handleDrop($event, null)">
                    <div v-for="list in filteredListsWithoutFolder" :key="list.id"
                        class="list-item list-item--standalone" :draggable="canManageSpace"
                        @dragstart="handleDragStart($event, list)"
                        @click="router.visit(route('projects.show', [workspace.id, space.id, list.id]))">
                        <div class="flex items-center gap-3">
                            <v-icon v-if="canManageSpace" size="18" class="drag-handle cursor-move"
                                @click.stop>mdi-drag</v-icon>
                            <v-icon size="18">mdi-package-variant-closed</v-icon>
                            <span class="font-medium">{{ list.name }}</span>
                        </div>
                        <div class="list-meta">
                            <span class="text-gray-500 text-sm">{{ list.tasks_count || 0 }} tasks</span>
                            <v-btn v-if="canManageSpace" icon variant="text" size="x-small"
                                @click.stop="openMoveList(list)">
                                <v-icon size="16">mdi-folder-move-outline</v-icon>
                            </v-btn>
                            <v-btn v-if="canManageSpace" icon variant="text" size="x-small"
                                @click.stop="openEditList(list)">
                                <v-icon size="16">mdi-pencil-outline</v-icon>
                            </v-btn>
                            <v-btn v-if="canManageSpace" icon variant="text" size="x-small"
                                @click.stop="openDeleteList(list)">
                                <v-icon size="16" color="error">mdi-delete-outline</v-icon>
                            </v-btn>
                            <v-btn v-if="canManageSpace" icon variant="text" size="x-small"
                                @click.stop="duplicateList(list)">
                                <v-icon size="16">mdi-content-copy</v-icon>
                            </v-btn>
                            <v-btn icon variant="text" size="x-small"
                                @click.stop="router.visit(route('projects.settings', [workspace.id, space.id, list.id]))">
                                <v-icon size="16">mdi-account-cog-outline</v-icon>
                            </v-btn>
                            <v-icon size="16" color="grey">mdi-chevron-right</v-icon>
                        </div>
                    </div>

                </div>

                <!-- Empty State -->
                <div v-if="!filteredFolders.length && !filteredListsWithoutFolder.length" class="empty-state">
                    <v-icon size="80" color="grey-darken-1" class="mb-4">mdi-folder-open-outline</v-icon>
                    <h2 class="text-xl font-semibold mb-2">This space is empty</h2>
                    <p class="text-gray-500">Get started by creating a project or folder</p>
                </div>
            </div>
        </div>

        <!-- Create Folder Dialog -->
        <v-dialog v-model="showCreateFolder" max-width="400">
            <v-card>
                <v-card-title>Create Folder</v-card-title>
                <v-card-text>
                    <v-text-field v-model="newFolderName" label="Folder Name" placeholder="e.g., Sprint 1"
                        variant="outlined" autofocus @keydown.enter="createFolder" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateFolder = false">Cancel</v-btn>
                    <v-btn color="primary" @click="createFolder">Create</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Create Project Dialog -->
        <v-dialog v-model="showCreateList" max-width="400">
            <v-card>
                <v-card-title>Create Project</v-card-title>
                <v-card-text>
                    <v-text-field v-model="newListName" label="Project Name" placeholder="e.g., Project A"
                        variant="outlined" autofocus class="mb-3" @keydown.enter="createList" />
                    <v-select v-model="selectedFolderId" :items="space?.folders || []" item-title="name" item-value="id"
                        label="Folder (optional)" variant="outlined" clearable bg-color="#1e1e1e" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateList = false">Cancel</v-btn>
                    <v-btn color="primary" @click="createList">Create</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Edit Space Dialog -->
        <v-dialog v-model="showEditSpace" max-width="500">
            <v-card>
                <v-card-title>Edit Space</v-card-title>
                <v-card-text>
                    <v-text-field v-model="editSpaceName" label="Space Name" variant="outlined" autofocus
                        class="mb-4" />
                    <ColorPicker v-model="editSpaceColor" label="Space Color" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showEditSpace = false">Cancel</v-btn>
                    <v-btn color="primary" @click="updateSpace">Update</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Space Dialog -->
        <DeleteConfirmDialog
            v-model="showDeleteSpace"
            item-type="space"
            :item-name="space?.name"
            @confirm="confirmDeleteSpace"
        />

        <!-- Edit Folder Dialog -->
        <v-dialog v-model="showEditFolder" max-width="400">
            <v-card>
                <v-card-title>Edit Folder</v-card-title>
                <v-card-text>
                    <v-text-field v-model="editFolderName" label="Folder Name" variant="outlined" autofocus />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showEditFolder = false">Cancel</v-btn>
                    <v-btn color="primary" @click="updateFolder">Update</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Folder Dialog -->
        <v-dialog v-model="showDeleteFolder" max-width="400">
            <v-card>
                <v-card-title class="text-error">Delete Folder?</v-card-title>
                <v-card-text>
                    Are you sure you want to delete "{{ deletingFolder?.name }}"? This will also delete all projects
                    within
                    this
                    folder. This action cannot be undone.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showDeleteFolder = false">Cancel</v-btn>
                    <v-btn color="error" @click="confirmDeleteFolder">Delete</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Edit Project Dialog -->
        <v-dialog v-model="showEditList" max-width="400">
            <v-card>
                <v-card-title>Edit Project</v-card-title>
                <v-card-text>
                    <v-text-field v-model="editListName" label="Project Name" variant="outlined" autofocus
                        @keydown.enter="updateList" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showEditList = false">Cancel</v-btn>
                    <v-btn color="primary" @click="updateList">Update</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Project Dialog -->
        <DeleteConfirmDialog
            v-model="showDeleteList"
            item-type="project"
            :item-name="deletingList?.name"
            warning-message="This will permanently delete the project along with all its tasks and subtasks."
            @confirm="confirmDeleteList"
        />

        <!-- Move Project to Folder Dialog -->
        <v-dialog v-model="showMoveList" max-width="400">
            <v-card>
                <v-card-title>Move Project to Folder</v-card-title>
                <v-card-text>
                    <div class="text-sm text-gray-400 mb-4">Moving: <span class="font-medium text-white">{{
                        movingList?.name
                            }}</span></div>
                    <v-select v-model="targetFolderId" :items="availableFolders" item-title="name" item-value="id"
                        label="Select Folder" variant="outlined" hide-details bg-color="#1e1e1e" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showMoveList = false">Cancel</v-btn>
                    <v-btn color="primary" @click="moveListToFolder">Move</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </MainLayout>
</template>

<style scoped>
.space-page {
    padding: 24px;
    max-width: 1400px;
    margin: 0 auto;
}

.breadcrumb a {
    transition: color 0.15s;
}

.space-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
}

.space-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}

.stat-card {
    text-align: center;
}

.actions-row {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
}

.space-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.folder-item {
    background-color: #1e1e1e;
    border-radius: 8px;
    overflow: hidden;
}

.folder-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    cursor: pointer;
    transition: background-color 0.15s;
}

.folder-header:hover {
    background-color: #2d2d30;
}

.folder-actions {
    opacity: 0;
    transition: opacity 0.15s;
}

.folder-header:hover .folder-actions {
    opacity: 1;
}

.folder-lists {
    border-top: 1px solid #2d2d30;
    transition: all 0.15s;
}

.folder-lists--empty {
    min-height: 72px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px 12px;
}

.folder-drop-hint {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #9ca3af;
    font-size: 13px;
    justify-content: center;
}

.list-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    cursor: pointer;
    transition: background-color 0.15s;
}

.list-item:hover {
    background-color: #2d2d30;
}

.list-item--standalone {
    background-color: #1e1e1e;
    border-radius: 8px;
}

.list-meta {
    display: flex;
    align-items: center;
    gap: 8px;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
}

.drag-handle {
    opacity: 0;
    transition: opacity 0.15s;
}

.list-item:hover .drag-handle {
    opacity: 0.5;
}

.list-item[draggable="true"] {
    cursor: grab;
}

.list-item[draggable="true"]:active {
    cursor: grabbing;
    opacity: 0.5;
}

.folder-lists.drag-over,
.root-lists-zone.drag-over {
    background-color: rgba(99, 102, 241, 0.1);
    border: 2px dashed rgba(99, 102, 241, 0.5);
    border-radius: 8px;
}

.cursor-move {
    cursor: move;
}

.root-lists-zone {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.15s;
}

.root-lists-zone--empty {
    min-height: 72px;
    border: 1px dashed #2d2d30;
    justify-content: center;
}

/*  Project Kanban Board  */
.project-board {
    overflow-x: auto;
    padding-bottom: 16px;
}

.board-columns {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    min-height: 300px;
}

.board-column {
    width: 300px;
    min-width: 300px;
    display: flex;
    flex-direction: column;
    background-color: #1a1a1a;
    border-radius: 8px;
    max-height: calc(100vh - 340px);
}

.board-column__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    border-bottom: 1px solid #2d2d30;
}

.board-column__content {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}

.board-column__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 50px;
}

.project-card {
    display: flex;
    background-color: #1e1e2e;
    border: 1px solid #2e2e3e;
    border-radius: 8px;
    overflow: hidden;
    cursor: grab;
    transition: background-color 0.15s, border-color 0.15s, opacity 0.15s;
}

.project-card:hover {
    background-color: #242438;
    border-color: #3e3e5e;
}

.project-card--dragging {
    opacity: 0.4;
    cursor: grabbing;
}

.project-card__status-bar {
    width: 4px;
    min-height: 100%;
    flex-shrink: 0;
}

.project-card__body {
    padding: 12px;
    flex: 1;
    min-width: 0;
}

.project-card__name {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.project-card__meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 12px;
    color: #9ca3af;
}

.project-card__folder,
.project-card__tasks {
    display: flex;
    align-items: center;
    gap: 4px;
}

.board-column__empty {
    color: #9ca3af;
    font-size: 12px;
    text-align: center;
    padding: 12px;
}

.board-column--drag-over .board-column__list {
    outline: 2px dashed #6366f1;
    outline-offset: 4px;
    border-radius: 6px;
    background-color: rgba(99, 102, 241, 0.06);
}
</style>
