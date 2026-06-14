<script setup>
import { ref, computed, onMounted, onUnmounted, provide, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import { useSnackbar } from '@/composables/useSnackbar';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useIdleDetector } from '@/composables/useIdleDetector';
import ColorPicker from '@/Components/ColorPicker.vue';
import { normalizeHexColor } from '@/utils/color';
import { formatHMS as formatDuration } from '@/utils/duration';

const props = defineProps({
    title: String,
});

const page = usePage();
const { mobile, smAndDown } = useDisplay();

// Snackbar — use composable for global state
const { snackbar, snackbarText, snackbarColor, showSnackbar } = useSnackbar();
const { isOpen: confirmOpen, dialogTitle, dialogMessage, dialogColor, onConfirm, onCancel } = useConfirmDialog();

// Sidebar state
const isSidebarOpen = ref(!mobile.value);
const isSidebarMini = ref(false);
const activeSpaceId = ref(null);

// Watch for mobile changes
watch(mobile, (val) => {
    isSidebarOpen.value = !val;
});

// User menu
const userMenuOpen = ref(false);
const notificationMenuOpen = ref(false);

// Computed
const user = computed(() => page.props.auth?.user);
const workspaces = computed(() => page.props.workspaces || []);
const activeWorkspace = computed(() => page.props.activeWorkspace);
const isSuperAdmin = computed(() => page.props.isSuperAdmin || false);
const notifications = computed(() => page.props.notifications || []);
const unreadNotificationsCount = computed(() => page.props.unreadNotificationsCount || 0);
const notificationsLastReadAt = computed(() => page.props.notificationsLastReadAt || null);

const isUnread = (notification) => {
    if (!notificationsLastReadAt.value) return true;
    return new Date(notification.created_at) > new Date(notificationsLastReadAt.value);
};

// Search
const searchQuery = ref('');
const searchDialog = ref(false);
const searchResults = ref({ tasks: [], projects: [], spaces: [] });
const isSearching = ref(false);
const searchType = ref('all');
const searchWorkspaceId = ref(null);
const searchTypeOptions = [
    { title: 'All', value: 'all' },
    { title: 'Tasks', value: 'tasks' },
    { title: 'Projects', value: 'projects' },
    { title: 'Spaces', value: 'spaces' },
];
const searchWorkspaceOptions = computed(() => [
    { title: 'All Workspaces', value: null },
    ...workspaces.value.map((w) => ({ title: w.name, value: w.id })),
]);

// Debounced search function
let searchTimeout = null;
let searchAbortController = null;

const performSearch = async () => {
    if (searchQuery.value.length < 2) {
        searchResults.value = { tasks: [], projects: [], spaces: [] };
        return;
    }

    if (searchAbortController) searchAbortController.abort();
    searchAbortController = new AbortController();

    isSearching.value = true;
    try {
        const params = new URLSearchParams();
        params.set('q', searchQuery.value);
        params.set('type', searchType.value);
        if (searchWorkspaceId.value) params.set('workspace_id', String(searchWorkspaceId.value));

        const response = await fetch(route('search') + '?' + params.toString(), {
            signal: searchAbortController.signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        searchResults.value = data;
    } catch (error) {
        if (error.name === 'AbortError') return;
        showSnackbar('Search failed. Please try again.', 'error');
    } finally {
        isSearching.value = false;
    }
};

watch([searchQuery, searchType, searchWorkspaceId], () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(performSearch, 300);
});

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) showSnackbar(flash.success, 'success');
        if (flash?.error) showSnackbar(flash.error, 'error');
    },
    { deep: true }
);

watch(
    () => page.props.validationErrors,
    (errors) => {
        if (Array.isArray(errors) && errors.length > 0) {
            showSnackbar(errors[0], 'error');
        }
    }
);

const goToTask = (task) => {
    if (!task.project?.space) return;
    router.visit(route('projects.show', [
        task.project.space.workspace_id,
        task.project.space_id,
        task.project_id,
    ]));
    searchDialog.value = false;
    searchQuery.value = '';
    searchType.value = 'all';
    searchWorkspaceId.value = null;
};

const goToList = (list) => {
    if (!list.space) return;
    router.visit(route('projects.show', [
        list.space.workspace_id,
        list.space_id,
        list.id
    ]));
    searchDialog.value = false;
    searchQuery.value = '';
    searchType.value = 'all';
    searchWorkspaceId.value = null;
};

const goToSpace = (space) => {
    if (!space.workspace_id) return;
    router.visit(route('spaces.show', [
        space.workspace_id,
        space.id
    ]));
    searchDialog.value = false;
    searchQuery.value = '';
    searchType.value = 'all';
    searchWorkspaceId.value = null;
};

const markNotificationsRead = () => {
    if (!unreadNotificationsCount.value) return;

    router.post(route('notifications.read'), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['notifications', 'unreadNotificationsCount', 'flash'],
    });
};

// Running timer from shared props
const runningTimer = computed(() => page.props.runningTimer);
const timerElapsed = ref(0);
let timerInterval = null;

const startGlobalTimerInterval = () => {
    if (timerInterval) clearInterval(timerInterval);
    if (runningTimer.value?.started_at) {
        timerElapsed.value = Math.floor((Date.now() - new Date(runningTimer.value.started_at).getTime()) / 1000);
        timerInterval = setInterval(() => { timerElapsed.value++; }, 1000);
    }
};

watch(runningTimer, (val) => {
    if (val?.started_at) {
        startGlobalTimerInterval();
    } else {
        if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
        timerElapsed.value = 0;
    }
}, { immediate: true });

// Create Space dialog
const showCreateSpace = ref(false);
const newSpaceName = ref('');
const newSpaceColor = ref('#6366F1');
const isCreatingSpace = ref(false);

// Provide a way for child pages to open the Create Space dialog without
// touching window globals. Pages can `inject('openCreateSpaceDialog')`.
provide('openCreateSpaceDialog', () => {
    showCreateSpace.value = true;
});

const showCreateWorkspace = ref(false);
const newWorkspaceName = ref('');
const newWorkspaceColor = ref('#3B82F6');
const isCreatingWorkspace = ref(false);

const createSpace = () => {
    if (!newSpaceName.value.trim() || !activeWorkspace.value || isCreatingSpace.value) return;
    isCreatingSpace.value = true;

    router.post(
        route('spaces.store', activeWorkspace.value.id),
        {
            name: newSpaceName.value.trim(),
            color: normalizeHexColor(newSpaceColor.value, '#6366F1'),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newSpaceName.value = '';
                newSpaceColor.value = '#6366F1';
                showCreateSpace.value = false;
                router.reload({ only: ['activeWorkspace'] });
            },
            onFinish: () => { isCreatingSpace.value = false; }
        }
    );
};

const openCreateWorkspaceDialog = () => {
    newWorkspaceName.value = '';
    newWorkspaceColor.value = '#3B82F6';
    showCreateWorkspace.value = true;
};

const createWorkspace = () => {
    if (!newWorkspaceName.value.trim() || isCreatingWorkspace.value) return;
    isCreatingWorkspace.value = true;

    router.post(
        route('workspaces.store'),
        {
            name: newWorkspaceName.value.trim(),
            color: normalizeHexColor(newWorkspaceColor.value, '#3B82F6'),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showCreateWorkspace.value = false;
                router.reload({ only: ['workspaces', 'activeWorkspace'] });
            },
            onFinish: () => {
                isCreatingWorkspace.value = false;
            },
        }
    );
};

// Logout
const logout = () => {
    router.post(route('logout'));
};

// Idle detection via Electron (OS-level)
useIdleDetector({
    onIdle: () => {
        const timerRunning = !!runningTimer.value;
        const message = timerRunning
            ? 'You are idle but a timer is still running! Please stop it if you are not working.'
            : 'You have been inactive for 5 minutes.';
        const color = timerRunning ? 'warning' : 'info';

        // Browser notification (if permission has been granted)
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(timerRunning ? '⚠️ Timer Still Running' : 'You Are Idle', {
                body: message,
                icon: '/favicon.ico',
            });
        } else {
            showSnackbar(message, color);
        }
    },
    onActive: () => {
        showSnackbar('You are back.', 'success');
    },
});

// Expose functions to window for global access
onMounted(() => {
    startGlobalTimerInterval();

    // Request browser notification permission (for idle alerts)
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }

    // Ctrl+K / Cmd+K to open search
    window.addEventListener('keydown', handleSearchShortcut);
});

onUnmounted(() => {
    if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
    clearTimeout(searchTimeout);
    if (searchAbortController) searchAbortController.abort();
    window.removeEventListener('keydown', handleSearchShortcut);
});

const handleSearchShortcut = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        searchDialog.value = !searchDialog.value;
    }
};

watch(searchDialog, (open) => {
    if (!open) {
        searchQuery.value = '';
        searchResults.value = { tasks: [], lists: [], spaces: [] };
        searchType.value = 'all';
        searchWorkspaceId.value = null;
        isSearching.value = false;
    }
});

</script>

<template>
    <v-app class="clickup-app">

        <Head :title="title" />

        <!-- Top Navigation Bar -->
        <v-app-bar color="surface" elevation="0" height="52" class="topbar">
            <!-- Sidebar Toggle -->
            <v-btn icon variant="text" size="small" class="topbar-menu-btn" @click="isSidebarOpen = !isSidebarOpen">
                <v-icon size="20">mdi-menu</v-icon>
            </v-btn>

            <v-spacer />

            <!-- Search -->
            <button class="topbar-search" @click="searchDialog = true">
                <v-icon size="15" color="grey">mdi-magnify</v-icon>
                <span v-if="!smAndDown">Search anything...</span>
                <kbd v-if="!smAndDown">Ctrl K</kbd>
            </button>

            <v-spacer />

            <!-- Running Timer Indicator -->
            <v-chip v-if="runningTimer" color="success" size="small" class="mr-2" variant="tonal">
                <v-icon start size="13">mdi-record-circle-outline</v-icon>
                {{ formatDuration(timerElapsed) }}
            </v-chip>

            <!-- Create Button -->
            <v-menu location="bottom end">
                <template v-slot:activator="{ props: createMenuProps }">
                    <v-btn v-bind="createMenuProps" color="primary" size="small" rounded="lg"
                        class="mr-2 topbar-create-btn">
                        <v-icon size="16">mdi-plus</v-icon>
                        <span v-if="!smAndDown" class="ml-1">Create</span>
                    </v-btn>
                </template>
                <v-card rounded="xl" elevation="8" min-width="200">
                    <v-list density="compact" nav>
                        <v-list-item prepend-icon="mdi-layers-plus" title="New Space" subtitle="Create a new space"
                            @click="showCreateSpace = true" rounded="lg" />
                        <v-list-item v-if="isSuperAdmin" prepend-icon="mdi-briefcase-plus-outline" title="New Workspace"
                            subtitle="Start a new workspace" @click="openCreateWorkspaceDialog" rounded="lg" />
                    </v-list>
                </v-card>
            </v-menu>

            <!-- Notifications -->
            <!-- User Menu -->
            <v-menu v-model="userMenuOpen" :close-on-content-click="false" location="bottom end">
                <template v-slot:activator="{ props: menuProps }">
                    <v-btn v-bind="menuProps" icon variant="text" size="small" class="mr-2 topbar-avatar-btn">
                        <v-avatar size="30" :color="user?.avatar_color || 'primary'" class="user-avatar-ring">
                            <img v-if="user?.profile_photo_url" :src="user.profile_photo_url" :alt="user?.name" />
                            <span v-else class="text-[11px] font-weight-bold">{{ user?.initials }}</span>
                        </v-avatar>
                    </v-btn>
                </template>

                <v-card width="260" rounded="xl" class="user-menu-card">
                    <div class="user-menu-header">
                        <v-avatar size="38" :color="user?.avatar_color || 'primary'">
                            <img v-if="user?.profile_photo_url" :src="user.profile_photo_url" :alt="user?.name" />
                            <span v-else class="text-sm font-weight-bold">{{ user?.initials }}</span>
                        </v-avatar>
                        <div class="min-w-0">
                            <div class="text-body-2 font-weight-bold text-truncate">{{ user?.name }}</div>
                            <div class="text-caption text-medium-emphasis text-truncate">{{ user?.email }}</div>
                        </div>
                    </div>
                    <v-divider />
                    <v-list density="compact" nav class="py-1">
                        <v-list-item prepend-icon="mdi-account-circle-outline" title="Profile"
                            :href="route('profile.show')" rounded="lg" />
                        <v-list-item prepend-icon="mdi-chart-areaspline" title="Analytics"
                            :href="activeWorkspace ? route('workspaces.analytics', activeWorkspace.id) : undefined"
                            :disabled="!activeWorkspace" rounded="lg" />
                        <v-list-item prepend-icon="mdi-delete-clock-outline" title="Recycle Bin"
                            :href="activeWorkspace ? route('workspaces.recycle-bin.index', activeWorkspace.id) : undefined"
                            :disabled="!activeWorkspace" rounded="lg" />
                    </v-list>
                    <v-divider />
                    <v-list density="compact" nav class="py-1">
                        <v-list-item prepend-icon="mdi-logout-variant" title="Sign Out" class="text-error"
                            @click="logout" rounded="lg" />
                    </v-list>
                </v-card>
            </v-menu>
        </v-app-bar>

        <!-- Sidebar -->
        <v-navigation-drawer v-model="isSidebarOpen" :rail="isSidebarMini" :temporary="mobile" color="surface"
            width="264" :rail-width="60" class="sidebar-drawer">
            <div class="sidebar-inner">

                <!-- Workspace Selector -->
                <div class="sidebar-workspace-area">
                    <v-menu>
                        <template v-slot:activator="{ props: menuProps }">
                            <button v-bind="menuProps" class="workspace-selector-btn"
                                :class="{ 'is-mini': isSidebarMini }">
                                <v-tooltip v-if="isSidebarMini" location="right"
                                    :text="activeWorkspace?.name || 'Select Workspace'">
                                    <template #activator="{ props: tipProps }">
                                        <div v-bind="tipProps" class="workspace-avatar"
                                            :style="{ backgroundColor: activeWorkspace?.color || '#6366F1' }">
                                            {{ activeWorkspace?.name?.charAt(0)?.toUpperCase() || 'W' }}
                                        </div>
                                    </template>
                                </v-tooltip>
                                <template v-else>
                                    <div class="workspace-avatar"
                                        :style="{ backgroundColor: activeWorkspace?.color || '#6366F1' }">
                                        {{ activeWorkspace?.name?.charAt(0)?.toUpperCase() || 'W' }}
                                    </div>
                                    <div class="workspace-info">
                                        <div class="workspace-name">{{ activeWorkspace?.name || 'Select Workspace' }}
                                        </div>
                                        <div class="workspace-sub">Workspace</div>
                                    </div>
                                    <v-icon size="16" class="workspace-chevron">mdi-chevron-expand</v-icon>
                                </template>
                            </button>
                        </template>

                        <v-card width="280" rounded="xl" elevation="8" class="workspace-menu-card">
                            <div class="workspace-menu-header">WORKSPACES</div>
                            <v-list density="compact" nav class="workspace-menu-list">
                                <v-list-item v-for="workspace in workspaces" :key="workspace.id"
                                    :active="workspace.id === activeWorkspace?.id" rounded="lg"
                                    @click="router.post(route('workspaces.switch', workspace.id))">
                                    <template #prepend>
                                        <div class="workspace-menu-avatar"
                                            :style="{ backgroundColor: workspace.color || '#6366F1' }">
                                            {{ workspace.name?.charAt(0)?.toUpperCase() }}
                                        </div>
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ workspace.name }}</v-list-item-title>
                                </v-list-item>
                            </v-list>
                            <v-divider />
                            <v-list density="compact" nav>
                                <v-list-item prepend-icon="mdi-plus-circle-outline" title="Create Workspace"
                                    rounded="lg" @click="openCreateWorkspaceDialog" />
                            </v-list>
                        </v-card>
                    </v-menu>
                </div>

                <!-- Navigation -->
                <div class="sidebar-nav-section">
                    <div v-if="!isSidebarMini" class="sidebar-section-label">GENERAL</div>
                    <v-list density="compact" nav class="sidebar-nav-list">
                        <v-tooltip v-for="navItem in [
                            { href: route('dashboard'), active: route().current('dashboard'), icon: 'mdi-view-dashboard-outline', label: 'Home', show: true },
                            { href: route('my-tasks'), active: route().current('my-tasks'), icon: 'mdi-checkbox-marked-circle-outline', label: 'My Tasks', show: true },
                            { href: activeWorkspace ? route('calendar.index', activeWorkspace.id) : undefined, active: route().current('calendar.*'), icon: 'mdi-calendar-month-outline', label: 'Calendar', show: !!activeWorkspace },
                            { href: route('time-tracking.index'), active: route().current('time-tracking.*'), icon: 'mdi-timer-outline', label: 'Time Tracking', show: true },
                        ]" :key="navItem.label" location="right" :text="navItem.label" :disabled="!isSidebarMini">
                            <template #activator="{ props: tipProps }">
                                <v-list-item v-if="navItem.show" v-bind="tipProps" :href="navItem.href"
                                    :active="navItem.active" :prepend-icon="navItem.icon"
                                    :title="isSidebarMini ? undefined : navItem.label" rounded="lg"
                                    class="sidebar-nav-item" :class="{ 'is-mini': isSidebarMini }" />
                            </template>
                        </v-tooltip>
                    </v-list>

                    <v-divider class="sidebar-divider" />

                    <div v-if="!isSidebarMini" class="sidebar-section-label">WORKSPACE</div>
                    <v-list density="compact" nav class="sidebar-nav-list">
                        <v-tooltip location="right" text="Analytics" :disabled="!isSidebarMini">
                            <template #activator="{ props: tipProps }">
                                <v-list-item v-if="activeWorkspace" v-bind="tipProps"
                                    :href="route('workspaces.analytics', activeWorkspace.id)"
                                    :active="route().current('workspaces.analytics')"
                                    prepend-icon="mdi-chart-areaspline" :title="isSidebarMini ? undefined : 'Analytics'"
                                    rounded="lg" class="sidebar-nav-item" :class="{ 'is-mini': isSidebarMini }" />
                            </template>
                        </v-tooltip>
                        <v-tooltip location="right" text="Settings" :disabled="!isSidebarMini">
                            <template #activator="{ props: tipProps }">
                                <v-list-item v-if="activeWorkspace" v-bind="tipProps"
                                    :href="route('workspaces.settings', activeWorkspace.id)"
                                    :active="route().current('workspaces.settings')" prepend-icon="mdi-cog-outline"
                                    :title="isSidebarMini ? undefined : 'Settings'" rounded="lg"
                                    class="sidebar-nav-item" :class="{ 'is-mini': isSidebarMini }" />
                            </template>
                        </v-tooltip>
                    </v-list>
                </div>

                <v-divider class="sidebar-divider" />

                <!-- Favorites -->
                <div v-if="activeWorkspace?.starred_spaces?.length" class="sidebar-nav-section">
                    <div v-if="!isSidebarMini" class="sidebar-section-label">
                        <v-icon size="11" class="mr-1">mdi-star-outline</v-icon>
                        FAVORITES
                    </div>
                    <v-list density="compact" nav class="sidebar-nav-list">
                        <v-tooltip v-for="space in activeWorkspace?.starred_spaces || []" :key="space.id"
                            location="right" :text="space.name" :disabled="!isSidebarMini">
                            <template #activator="{ props: tipProps }">
                                <v-list-item v-bind="tipProps"
                                    :href="route('spaces.show', [activeWorkspace.id, space.id])" rounded="lg"
                                    class="sidebar-nav-item" :class="{ 'is-mini': isSidebarMini }">
                                    <template #prepend>
                                        <div class="space-color-dot"
                                            :style="{ backgroundColor: space.color || '#6366F1' }" />
                                    </template>
                                    <v-list-item-title v-if="!isSidebarMini" class="text-body-2">{{ space.name
                                    }}</v-list-item-title>
                                </v-list-item>
                            </template>
                        </v-tooltip>
                    </v-list>
                    <v-divider class="sidebar-divider" />
                </div>

                <!-- Spaces -->
                <div class="flex-1 overflow-y-auto sidebar-spaces-section">
                    <div v-if="!isSidebarMini" class="sidebar-section-label sidebar-section-label--with-action">
                        <span>SPACES</span>
                        <v-btn icon variant="text" size="x-small" class="section-add-btn"
                            @click="showCreateSpace = true">
                            <v-icon size="14">mdi-plus</v-icon>
                        </v-btn>
                    </div>

                    <!-- View All link -->
                    <v-list density="compact" nav class="sidebar-nav-list mb-1">
                        <v-tooltip location="right" text="View All Spaces" :disabled="!isSidebarMini">
                            <template #activator="{ props: tipProps }">
                                <v-list-item v-if="activeWorkspace" v-bind="tipProps"
                                    :href="route('workspaces.show', activeWorkspace.id)"
                                    prepend-icon="mdi-view-grid-outline"
                                    :title="isSidebarMini ? undefined : 'All Spaces'" rounded="lg"
                                    class="sidebar-nav-item view-all-spaces" :class="{ 'is-mini': isSidebarMini }" />
                            </template>
                        </v-tooltip>
                    </v-list>

                    <!-- Mini mode: icon-only -->
                    <v-list v-if="isSidebarMini" density="compact" nav class="sidebar-nav-list">
                        <v-tooltip v-for="space in activeWorkspace?.spaces || []" :key="space.id" location="right"
                            :text="space.name">
                            <template #activator="{ props: tipProps }">
                                <v-list-item v-bind="tipProps"
                                    :href="route('spaces.show', [activeWorkspace.id, space.id])" rounded="lg"
                                    class="sidebar-nav-item is-mini">
                                    <template #prepend>
                                        <div class="space-icon-mini"
                                            :style="{ backgroundColor: space.color || '#6366F1' }">
                                            <v-icon size="13" color="white">mdi-layers-outline</v-icon>
                                        </div>
                                    </template>
                                </v-list-item>
                            </template>
                        </v-tooltip>
                    </v-list>

                    <!-- Full mode: expandable tree -->
                    <v-list v-else density="compact" nav class="sidebar-space-tree">
                        <template v-for="space in activeWorkspace?.spaces || []" :key="space.id">
                            <v-list-group :value="space.id">
                                <template v-slot:activator="{ props: groupProps, isOpen }">
                                    <v-list-item v-bind="groupProps" rounded="lg" class="space-tree-item">
                                        <template #prepend>
                                            <div class="space-icon"
                                                :style="{ backgroundColor: space.color || '#6366F1' }">
                                                <v-icon size="13" color="white">mdi-layers-outline</v-icon>
                                            </div>
                                        </template>
                                        <v-list-item-title class="space-name-text">
                                            {{ space.name }}
                                        </v-list-item-title>
                                        <template #append>
                                            <div class="space-item-actions">
                                                <v-tooltip location="top" text="Open space">
                                                    <template #activator="{ props: tipProps }">
                                                        <v-btn v-bind="tipProps" icon variant="text" size="x-small"
                                                            class="space-open-btn"
                                                            @click.stop="router.visit(route('spaces.show', [activeWorkspace?.id, space.id]))">
                                                            <v-icon size="12">mdi-open-in-new</v-icon>
                                                        </v-btn>
                                                    </template>
                                                </v-tooltip>
                                                <v-icon size="14" class="space-expand-icon">
                                                    {{ isOpen ? 'mdi-chevron-down' : 'mdi-chevron-right' }}
                                                </v-icon>
                                            </div>
                                        </template>
                                    </v-list-item>
                                </template>

                                <!-- Folders -->
                                <template v-for="folder in space.folders || []" :key="folder.id">
                                    <v-list-group :value="'folder-' + folder.id">
                                        <template v-slot:activator="{ props: folderProps }">
                                            <v-list-item v-bind="folderProps" rounded="lg" class="folder-tree-item">
                                                <template #prepend>
                                                    <v-icon size="14" class="folder-icon">mdi-folder-outline</v-icon>
                                                </template>
                                                <v-list-item-title>{{ folder.name }}</v-list-item-title>
                                            </v-list-item>
                                        </template>
                                        <v-list-item v-for="list in folder.projects || []" :key="list.id"
                                            :href="route('projects.show', [activeWorkspace?.id, space.id, list.id])"
                                            rounded="lg" class="list-tree-item">
                                            <template #prepend>
                                                <v-icon size="12" class="list-icon">mdi-view-list-outline</v-icon>
                                            </template>
                                            <v-list-item-title>{{ list.name }}</v-list-item-title>
                                        </v-list-item>
                                    </v-list-group>
                                </template>

                                <!-- Lists without folder -->
                                <v-list-item v-for="list in space.projects_without_folder || []" :key="list.id"
                                    :href="route('projects.show', [activeWorkspace?.id, space.id, list.id])"
                                    rounded="lg" class="list-tree-item">
                                    <template #prepend>
                                        <v-icon size="12" class="list-icon">mdi-view-list-outline</v-icon>
                                    </template>
                                    <v-list-item-title>{{ list.name }}</v-list-item-title>
                                </v-list-item>
                            </v-list-group>
                        </template>
                    </v-list>
                </div>

                <!-- Sidebar Footer -->
                <div class="sidebar-footer">
                    <v-tooltip location="right" :text="isSidebarMini ? 'Expand' : 'Collapse'"
                        :disabled="!isSidebarMini">
                        <template #activator="{ props: tipProps }">
                            <button v-bind="tipProps" class="sidebar-collapse-btn" :class="{ 'is-mini': isSidebarMini }"
                                @click="isSidebarMini = !isSidebarMini">
                                <v-icon size="16">{{ isSidebarMini ? 'mdi-chevron-right' : 'mdi-chevron-left'
                                }}</v-icon>
                                <span v-if="!isSidebarMini">Collapse</span>
                            </button>
                        </template>
                    </v-tooltip>
                </div>

            </div>
        </v-navigation-drawer>

        <!-- Main Content -->
        <v-main class="bg-background">
            <slot />
        </v-main>

        <!-- Search Dialog -->
        <v-dialog v-model="searchDialog" max-width="680" scrollable rounded="xl">
            <v-card rounded="xl">
                <div class="search-header">
                    <v-icon size="18" color="grey">mdi-magnify</v-icon>
                    <input v-model="searchQuery" class="search-input" placeholder="Search tasks, projects, spaces..."
                        autofocus />
                    <v-progress-circular v-if="isSearching" size="16" width="2" indeterminate color="primary" />
                    <kbd class="search-kbd" v-if="!isSearching">ESC</kbd>
                </div>
                <div class="search-filters">
                    <v-select v-model="searchType" :items="searchTypeOptions" item-title="title" item-value="value"
                        label="Type" hide-details density="compact" variant="outlined" style="max-width: 160px;" />
                    <v-select v-model="searchWorkspaceId" :items="searchWorkspaceOptions" item-title="title"
                        item-value="value" label="Workspace" hide-details density="compact" variant="outlined" clearable
                        style="max-width: 200px;" />
                </div>
                <v-divider />
                <v-card-text class="pa-0" style="max-height: 460px; overflow-y: auto;">
                    <div v-if="!searchQuery || searchQuery.length < 2" class="search-empty">
                        <v-icon size="40" color="grey-darken-1">mdi-magnify</v-icon>
                        <div class="text-body-2 text-medium-emphasis mt-2">Type at least 2 characters to search</div>
                    </div>
                    <div v-else-if="isSearching" class="search-empty">
                        <v-progress-circular indeterminate color="primary" size="36" />
                    </div>
                    <div v-else-if="!searchResults.tasks.length && !searchResults.projects.length && !searchResults.spaces.length"
                        class="search-empty">
                        <v-icon size="40" color="grey-darken-1">mdi-text-search</v-icon>
                        <div class="text-body-2 text-medium-emphasis mt-2">No results for "<strong>{{ searchQuery
                        }}</strong>"
                        </div>
                    </div>
                    <div v-else>
                        <!-- Tasks -->
                        <v-list v-if="searchResults.tasks.length" density="compact" nav>
                            <div class="search-result-label">TASKS — {{ searchResults.tasks.length }}</div>
                            <v-list-item v-for="task in searchResults.tasks" :key="task.id" rounded="lg"
                                class="search-result-item" @click="goToTask(task)">
                                <template #prepend>
                                    <v-icon size="16" color="primary">mdi-checkbox-marked-circle-outline</v-icon>
                                </template>
                                <v-list-item-title class="text-body-2">{{ task.name }}</v-list-item-title>
                                <v-list-item-subtitle class="text-caption">
                                    {{ task.project?.space?.name }} · {{ task.project?.name }}
                                </v-list-item-subtitle>
                            </v-list-item>
                        </v-list>

                        <!-- Projects -->
                        <v-list v-if="searchResults.projects.length" density="compact" nav>
                            <div class="search-result-label">PROJECTS — {{ searchResults.projects.length }}</div>
                            <v-list-item v-for="list in searchResults.projects" :key="list.id" rounded="lg"
                                class="search-result-item" @click="goToList(list)">
                                <template #prepend>
                                    <v-icon size="16" color="warning">mdi-view-list-outline</v-icon>
                                </template>
                                <v-list-item-title class="text-body-2">{{ list.name }}</v-list-item-title>
                                <v-list-item-subtitle class="text-caption">{{ list.space?.name }}</v-list-item-subtitle>
                            </v-list-item>
                        </v-list>

                        <!-- Spaces -->
                        <v-list v-if="searchResults.spaces.length" density="compact" nav>
                            <div class="search-result-label">SPACES — {{ searchResults.spaces.length }}</div>
                            <v-list-item v-for="space in searchResults.spaces" :key="space.id" rounded="lg"
                                class="search-result-item" @click="goToSpace(space)">
                                <template #prepend>
                                    <div class="search-space-icon"
                                        :style="{ backgroundColor: space.color || '#6366F1' }">
                                        <v-icon size="12" color="white">mdi-layers</v-icon>
                                    </div>
                                </template>
                                <v-list-item-title class="text-body-2">{{ space.name }}</v-list-item-title>
                                <v-list-item-subtitle class="text-caption">{{ space.workspace?.name
                                }}</v-list-item-subtitle>
                            </v-list-item>
                        </v-list>
                    </div>
                </v-card-text>
            </v-card>
        </v-dialog>




        <!-- Create Space Dialog -->
        <v-dialog v-model="showCreateSpace" max-width="480" rounded="xl">
            <v-card rounded="xl">
                <div class="dialog-header">
                    <div class="dialog-header-icon" style="background: rgba(123, 104, 238, 0.12)">
                        <v-icon color="primary" size="20">mdi-layers-plus</v-icon>
                    </div>
                    <div>
                        <div class="text-subtitle-2 font-weight-bold">Create Space</div>
                        <div class="text-caption text-medium-emphasis">Add a new space to {{ activeWorkspace?.name }}
                        </div>
                    </div>
                    <v-spacer />
                    <v-btn icon variant="text" size="x-small" @click="showCreateSpace = false">
                        <v-icon size="18">mdi-close</v-icon>
                    </v-btn>
                </div>
                <v-divider />
                <v-card-text class="pt-4 d-flex flex-column ga-3">
                    <v-text-field v-model="newSpaceName" label="Space Name" placeholder="e.g., Marketing, Engineering"
                        variant="outlined" density="comfortable" hide-details autofocus @keydown.enter="createSpace" />
                    <ColorPicker v-model="newSpaceColor" label="Space Color" />
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-spacer />
                    <v-btn variant="text" rounded="lg" @click="showCreateSpace = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat" rounded="lg" :loading="isCreatingSpace"
                        :disabled="!newSpaceName.trim()" @click="createSpace">Create Space</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <!-- Create Workspace Dialog -->
        <v-dialog v-model="showCreateWorkspace" max-width="540" rounded="xl">
            <v-card rounded="xl">
                <div class="dialog-header">
                    <div class="dialog-header-icon" style="background: rgba(59, 130, 246, 0.12)">
                        <v-icon color="primary" size="20">mdi-briefcase-outline</v-icon>
                    </div>
                    <div>
                        <div class="text-subtitle-2 font-weight-bold">Create Workspace</div>
                        <div class="text-caption text-medium-emphasis">Start a new workspace for a team or project</div>
                    </div>
                    <v-spacer />
                    <v-btn icon variant="text" size="x-small" @click="showCreateWorkspace = false">
                        <v-icon size="18">mdi-close</v-icon>
                    </v-btn>
                </div>
                <v-divider />
                <v-card-text class="pt-4 d-flex flex-column ga-3">
                    <v-text-field v-model="newWorkspaceName" label="Workspace Name"
                        placeholder="e.g., Project Team, Acme Client" variant="outlined" density="comfortable"
                        hide-details autofocus @keydown.enter="createWorkspace" />
                    <ColorPicker v-model="newWorkspaceColor" />
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-spacer />
                    <v-btn variant="text" rounded="lg" @click="showCreateWorkspace = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat" rounded="lg" :loading="isCreatingWorkspace"
                        :disabled="!newWorkspaceName.trim()" @click="createWorkspace">Create Workspace</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Global Snackbar -->
        <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000" location="bottom right" rounded="lg">
            <div class="d-flex align-center ga-2">
                <v-icon size="16">{{ snackbarColor === 'success' ? 'mdi-check-circle-outline' : snackbarColor ===
                    'error' ?
                    'mdi-alert-circle-outline' : 'mdi-information-outline' }}</v-icon>
                {{ snackbarText }}
            </div>
            <template #actions>
                <v-btn variant="text" size="small" icon @click="snackbar = false">
                    <v-icon size="16">mdi-close</v-icon>
                </v-btn>
            </template>
        </v-snackbar>

        <!-- Global Confirm Dialog -->
        <v-dialog v-model="confirmOpen" max-width="400" persistent rounded="xl">
            <v-card rounded="xl">
                <div class="dialog-header" :class="dialogColor === 'error' ? 'dialog-header--danger' : ''">
                    <div class="dialog-header-icon"
                        :style="`background: rgba(${dialogColor === 'error' ? '255,107,107' : '123,104,238'}, 0.12)`">
                        <v-icon :color="dialogColor || 'primary'" size="20">
                            {{ dialogColor === 'error' ? 'mdi-alert-circle-outline' : 'mdi-help-circle-outline' }}
                        </v-icon>
                    </div>
                    <div>
                        <div class="text-subtitle-2 font-weight-bold"
                            :class="dialogColor === 'error' ? 'text-error' : ''">
                            {{ dialogTitle }}
                        </div>
                    </div>
                    <v-spacer />
                    <v-btn icon variant="text" size="x-small" @click="onCancel">
                        <v-icon size="18">mdi-close</v-icon>
                    </v-btn>
                </div>
                <v-divider />
                <v-card-text class="pt-4 text-body-2 text-medium-emphasis">{{ dialogMessage }}</v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-spacer />
                    <v-btn variant="text" rounded="lg" @click="onCancel">Cancel</v-btn>
                    <v-btn :color="dialogColor || 'primary'" variant="flat" rounded="lg"
                        @click="onConfirm">Confirm</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-app>
</template>

<style scoped>
/*  App  */
.clickup-app {
    --v-theme-surface: #1e1e1e;
    --v-theme-surface-light: #2d2d30;
    --v-theme-background: #121212;
}

/*  Topbar  */
.topbar {
    border-bottom: 1px solid rgba(255, 255, 255, 0.06) !important;
}

.topbar-menu-btn {
    margin-left: 4px;
}

.topbar-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 4px 0 8px;
    text-decoration: none;
}

.logo-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: linear-gradient(135deg, #7B68EE, #8B5CF6);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.logo-text {
    font-size: 15px;
    font-weight: 700;
    color: #fff;
    letter-spacing: -0.01em;
}

.topbar-search {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 10px;
    cursor: pointer;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.4);
    transition: border-color 0.2s, background 0.2s;
    min-width: 200px;
    max-width: 320px;
}

.topbar-search:hover {
    background: rgba(255, 255, 255, 0.07);
    border-color: rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.6);
}

.topbar-search kbd {
    margin-left: auto;
    padding: 1px 6px;
    background: rgba(255, 255, 255, 0.06);
    border-radius: 4px;
    font-size: 11px;
    color: rgba(255, 255, 255, 0.3);
    font-family: inherit;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.topbar-create-btn {
    font-weight: 600;
}

.topbar-icon-btn,
.topbar-avatar-btn {
    opacity: 0.8;
    transition: opacity 0.15s;
}

.topbar-icon-btn:hover,
.topbar-avatar-btn:hover {
    opacity: 1;
}

.user-avatar-ring {
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1);
}

/*  Notification panel  */
.notification-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
}

.notification-list {
    padding: 4px 8px;
}

.notification-item {
    border-radius: 8px;
    margin-bottom: 2px;
}

.notif-avatar {
    flex-shrink: 0;
}

.notification-item {
    border-radius: 8px;
    margin-bottom: 2px;
    transition: background 0.15s ease;
}

.notification-item--unread {
    background: rgba(123, 104, 238, 0.06);
}

.notif-title {
    white-space: normal !important;
    line-height: 1.4 !important;
}

.notif-dot-inline {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #7B68EE;
    flex-shrink: 0;
}

.notif-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 32px 20px;
}

/*  User Menu  */
.user-menu-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
}

/*  Sidebar  */
.sidebar-drawer {
    border-right: 1px solid rgba(255, 255, 255, 0.06) !important;
}

.sidebar-inner {
    display: flex;
    flex-direction: column;
    height: 100%;
}

:deep(.v-navigation-drawer__content) {
    display: flex;
    flex-direction: column;
}

:deep(.v-navigation-drawer .v-list) {
    background-color: transparent !important;
}

/* Workspace Selector */
.sidebar-workspace-area {
    padding: 10px 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.workspace-selector-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 8px 10px;
    border-radius: 10px;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: background 0.15s;
    text-align: left;
}

.workspace-selector-btn:hover {
    background: rgba(255, 255, 255, 0.05);
}

.workspace-selector-btn.is-mini {
    justify-content: center;
    padding: 6px;
}

.workspace-avatar {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 13px;
    flex-shrink: 0;
}

.workspace-info {
    flex: 1;
    min-width: 0;
}

.workspace-name {
    font-size: 13px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.workspace-sub {
    font-size: 10px;
    color: rgba(255, 255, 255, 0.35);
    margin-top: 1px;
}

.workspace-chevron {
    opacity: 0.4;
}

/* Workspace Menu */
.workspace-menu-header {
    padding: 10px 16px 6px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: rgba(255, 255, 255, 0.3);
}

.workspace-menu-avatar {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 11px;
    margin-right: 8px;
    flex-shrink: 0;
}

/* Navigation sections */
.sidebar-nav-section {
    padding: 8px 8px 4px;
}

.sidebar-nav-section .sidebar-nav-list {
    padding: 0 !important;
}

.sidebar-section-label {
    display: flex;
    align-items: center;
    padding: 6px 8px 5px;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.07em;
    color: rgba(255, 255, 255, 0.32);
    text-transform: uppercase;
}

.sidebar-section-label--with-action {
    padding: 4px 4px 6px 8px;
    justify-content: space-between;
}

.section-add-btn {
    opacity: 0.6;
}

.section-add-btn:hover {
    opacity: 1;
}

.sidebar-nav-list {
    padding: 0 !important;
}

.sidebar-nav-item {
    min-height: 36px !important;
    border-radius: 8px !important;
    margin-bottom: 2px !important;
}

.sidebar-nav-item :deep(.v-list-item-title) {
    font-size: 13px !important;
    font-weight: 500;
    letter-spacing: 0.01em;
}

.sidebar-nav-item :deep(.v-list-item__prepend .v-icon) {
    font-size: 18px !important;
    opacity: 0.75;
}

.sidebar-nav-item.is-mini {
    min-height: 40px !important;
    padding: 0 !important;
}

.sidebar-nav-item.is-mini :deep(.v-list-item__prepend) {
    width: 60px !important;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-inline-end: 0 !important;
}

.sidebar-nav-item.is-mini :deep(.v-list-item__prepend .v-icon) {
    margin-inline-end: 0 !important;
    opacity: 0.75;
}

.sidebar-nav-item.is-mini :deep(.v-list-item__spacer) {
    display: none !important;
}

.sidebar-nav-item.is-mini :deep(.v-list-item__content) {
    display: none !important;
}

.sidebar-nav-item.is-mini :deep(.v-list-item__append) {
    display: none !important;
}

.sidebar-divider {
    margin: 4px 8px !important;
    border-color: rgba(255, 255, 255, 0.05) !important;
}

/* Spaces section */
.sidebar-spaces-section {
    padding: 8px 8px 6px;
}

/* In mini/rail mode, remove horizontal padding so icons center correctly */
:deep(.v-navigation-drawer--rail) .sidebar-nav-section,
:deep(.v-navigation-drawer--rail) .sidebar-spaces-section {
    padding-inline: 0 !important;
}

.view-all-spaces {
    opacity: 0.7;
}

.view-all-spaces:hover {
    opacity: 1;
}

/* Space icons */
.space-color-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
    margin-right: 10px;
    margin-left: 4px;
}

.space-icon-mini {
    width: 26px;
    height: 26px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Space tree */
.sidebar-space-tree {
    padding: 0 !important;
}

.space-tree-item {
    min-height: 36px !important;
    border-radius: 8px !important;
    margin-bottom: 2px;
    padding-inline-end: 6px !important;
}

.space-tree-item :deep(.v-list-item-title) {
    font-size: 13px !important;
    font-weight: 600;
}

.space-icon {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-right: 9px;
}

.space-name-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.space-item-actions {
    display: flex;
    align-items: center;
    gap: 2px;
}

.space-open-btn {
    opacity: 0;
    transition: opacity 0.15s;
}

.space-tree-item:hover .space-open-btn,
.space-open-btn:focus-visible {
    opacity: 0.7;
}

.space-open-btn:hover {
    opacity: 1 !important;
}

.space-expand-icon {
    opacity: 0.5;
}

/* Folder & list tree items */
.folder-tree-item {
    min-height: 32px !important;
    border-radius: 7px !important;
    margin-bottom: 1px;
}

.folder-tree-item :deep(.v-list-item-title) {
    font-size: 12.5px !important;
    font-weight: 500;
}

.folder-icon {
    opacity: 0.6;
}

.list-tree-item {
    min-height: 30px !important;
    border-radius: 6px !important;
    margin-bottom: 1px;
    opacity: 0.82;
}

.list-tree-item:hover {
    opacity: 1;
}

.list-tree-item :deep(.v-list-item-title) {
    font-size: 12px !important;
}

.list-icon {
    opacity: 0.45;
}

:deep(.v-list-group__items) {
    --indent-padding: 0px !important;
}

:deep(.v-list-group__items .v-list-item) {
    padding-inline-start: 18px !important;
}

:deep(.v-list-group__items .v-list-group__items .v-list-item) {
    padding-inline-start: 34px !important;
}

:deep(.v-list-item__prepend) {
    width: auto !important;
}

.space-tree-item :deep(.v-list-item__prepend),
.folder-tree-item :deep(.v-list-item__prepend),
.list-tree-item :deep(.v-list-item__prepend) {
    margin-inline-end: 8px !important;
}

/* Hide Vuetify's spacer div that adds excess gap after icons in tree items */
.space-tree-item :deep(.v-list-item__spacer),
.folder-tree-item :deep(.v-list-item__spacer),
.list-tree-item :deep(.v-list-item__spacer) {
    display: none !important;
}

/* In mini mode, hide spacer so icon stays centered */
.sidebar-nav-item.is-mini :deep(.v-list-item__spacer) {
    display: none !important;
}

/* Sidebar footer */
.sidebar-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    padding: 6px 8px;
}

.sidebar-collapse-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    width: 100%;
    padding: 7px 10px;
    background: transparent;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.4);
    transition: background 0.15s, color 0.15s;
}

.sidebar-collapse-btn:hover {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.7);
}

.sidebar-collapse-btn.is-mini {
    justify-content: center;
    padding: 7px;
}

/*  Search Dialog  */
.search-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
}

.search-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    font-size: 15px;
    color: rgba(255, 255, 255, 0.9);
}

.search-input::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

.search-kbd {
    padding: 2px 6px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    font-size: 11px;
    color: rgba(255, 255, 255, 0.3);
    font-family: inherit;
}

.search-filters {
    display: flex;
    gap: 10px;
    padding: 8px 16px;
    flex-wrap: wrap;
}

.search-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 48px 24px;
}

.search-result-label {
    padding: 8px 12px 4px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: rgba(255, 255, 255, 0.3);
}

.search-result-item {
    margin-bottom: 2px;
}

.search-space-icon {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 8px;
}

/*  Dialogs (shared)  */
.dialog-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
}

.dialog-header--danger {
    background: rgba(255, 107, 107, 0.04);
}

.dialog-header-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
</style>
