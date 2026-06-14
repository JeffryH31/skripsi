<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import DeleteConfirmDialog from '@/Components/DeleteConfirmDialog.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useSnackbar } from '@/composables/useSnackbar';

const { confirm: confirmDialog } = useConfirmDialog();
const { showSnackbar } = useSnackbar();

const props = defineProps({
    workspace: { type: Object, default: null },
    members: { type: Array, default: () => [] },
    availableUsers: { type: Array, default: () => [] },
    projectLists: { type: Array, default: () => [] },
    spaces: { type: Array, default: () => [] },
});

const page = usePage();

const isAdmin = computed(() => {
    const currentUserId = page.props?.auth?.user?.id;
    const currentMember = props.members?.find((member) => member.id === currentUserId);
    const role = currentMember?.pivot?.role || currentMember?.role;

    return role === 'admin' || role === 'owner';
});

const isOwner = computed(() => {
    const currentUserId = page.props?.auth?.user?.id;
    const currentMember = props.members?.find((member) => member.id === currentUserId);
    const role = currentMember?.pivot?.role || currentMember?.role;

    return role === 'owner';
});

// Add member dialog
const showAddMember = ref(false);
const selectedUser = ref(null);
const selectedRole = ref('member');
const inviteEmail = ref('');
const showCreateUser = ref(false);
const showEditUser = ref(false);
const editingUser = ref(null);

const createUserForm = ref({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    hourly_rate: 150000,
    role: 'member',
});

const editUserForm = ref({
    user_id: null,
    name: '',
    email: '',
    hourly_rate: 150000,
});

const addMember = () => {
    // Support both: pick from dropdown (user_id) OR type an email directly
    const payload = selectedUser.value
        ? { user_id: selectedUser.value, role: selectedRole.value }
        : { email: inviteEmail.value.trim(), role: selectedRole.value };

    if (!payload.user_id && !payload.email) return;

    router.post(
        route('workspaces.members.add', props.workspace.id),
        payload,
        {
            preserveScroll: true,
            onSuccess: () => {
                showAddMember.value = false;
                selectedUser.value = null;
                inviteEmail.value = '';
                selectedRole.value = 'member';
            },
        }
    );
};

const openCreateUserDialog = () => {
    createUserForm.value = {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        hourly_rate: 150000,
        role: 'member',
    };
    showCreateUser.value = true;
};

const createUser = () => {
    if (!createUserForm.value.name.trim() || !createUserForm.value.email.trim()) {
        return;
    }

    router.post(
        route('workspaces.members.users.store', props.workspace.id),
        createUserForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                showCreateUser.value = false;
                createUserForm.value = {
                    name: '',
                    email: '',
                    password: '',
                    password_confirmation: '',
                    hourly_rate: 150000,
                    role: 'member',
                };
            },
        }
    );
};

const openEditUserDialog = (member) => {
    editingUser.value = member;
    editUserForm.value = {
        user_id: member.id,
        name: member.name || '',
        email: member.email || '',
        hourly_rate: Number(member.hourly_rate ?? 150000),
    };
    showEditUser.value = true;
};

const updateUser = () => {
    if (!editUserForm.value.user_id || !editUserForm.value.name.trim() || !editUserForm.value.email.trim()) {
        return;
    }

    router.patch(
        route('workspaces.members.users.update', props.workspace.id),
        editUserForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                showEditUser.value = false;
                editingUser.value = null;
            },
        }
    );
};

// Remove member
const removeMember = async (member) => {
    if (!await confirmDialog(`Remove ${member.name} from workspace?`, 'Remove Member')) return;

    router.delete(
        route('workspaces.members.remove', props.workspace.id),
        {
            data: { user_id: member.id },
            preserveScroll: true,
            onSuccess: () => {
            }
        }
    );
};

// Change role
const changeRole = (member, newRole) => {
    router.patch(
        route('workspaces.members.role', props.workspace.id),
        {
            user_id: member.id,
            role: newRole,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
            }
        }
    );
};

const getRoleBadgeColor = (role) => {
    switch (role) {
        case 'admin': return 'error';
        case 'member': return 'primary';
        default: return 'grey';
    }
};

const spaceRoleItems = [
    { title: 'Admin', value: 'admin' },
    { title: 'Member', value: 'member' },
    { title: 'Guest', value: 'guest' },
];

const selectedSpaceId = ref(props.spaces?.[0]?.id ?? null);
const showAddSpaceMember = ref(false);
const selectedSpaceUser = ref(null);
const selectedSpaceRole = ref('member');

const spaceOptions = computed(() => {
    return (props.spaces || []).map((space) => ({
        title: space.name,
        value: space.id,
    }));
});

const selectedSpace = computed(() => {
    const selectedId = Number(selectedSpaceId.value);
    return (props.spaces || []).find((space) => space.id === selectedId) || null;
});

const canManageSelectedSpace = computed(() => {
    if (isAdmin.value) {
        return true;
    }

    const currentUserId = page.props?.auth?.user?.id;
    const spaceMember = selectedSpace.value?.members?.find((member) => member.id === currentUserId);

    return ['admin', 'manager'].includes(spaceMember?.role || '');
});

const availableSpaceUsers = computed(() => {
    if (!selectedSpace.value) {
        return [];
    }

    const assignedIds = new Set((selectedSpace.value.members || []).map((member) => member.id));
    return (props.members || []).filter((member) => !assignedIds.has(member.id));
});

const getSpaceRoleBadgeColor = (role) => {
    switch (role) {
        case 'admin': return 'warning';
        case 'member': return 'primary';
        case 'guest': return 'grey';
        default: return 'grey';
    }
};

const openAddSpaceMemberDialog = () => {
    selectedSpaceUser.value = null;
    selectedSpaceRole.value = 'member';
    showAddSpaceMember.value = true;
};

const addSpaceMember = () => {
    if (!selectedSpace.value || !selectedSpaceUser.value) {
        return;
    }

    router.post(
        route('spaces.members.add', [props.workspace.id, selectedSpace.value.id]),
        {
            user_id: selectedSpaceUser.value,
            role: selectedSpaceRole.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showAddSpaceMember.value = false;
                selectedSpaceUser.value = null;
                selectedSpaceRole.value = 'member';
            },
        }
    );
};

const changeSpaceMemberRole = (space, member, role) => {
    router.patch(
        route('spaces.members.role', [props.workspace.id, space.id]),
        {
            user_id: member.id,
            role,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
            },
        }
    );
};

const removeSpaceMember = async (space, member) => {
    if (!await confirmDialog(`Remove ${member.name} from ${space.name}?`, 'Remove Space Member')) {
        return;
    }

    router.delete(
        route('spaces.members.remove', [props.workspace.id, space.id]),
        {
            data: { user_id: member.id },
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
            },
        }
    );
};

const canModifyWorkspaceMember = (member) => {
    const currentUserId = page.props?.auth?.user?.id;

    return member?.id !== currentUserId;
};

const projectRoleItems = [
    { title: 'Project Owner', value: 'project_owner' },
    { title: 'Project Manager', value: 'project_manager' },
    { title: 'Development Team', value: 'development_team' },
    { title: 'Guest', value: 'guest' },
];

const selectedProjectId = ref(props.projectLists?.[0]?.id ?? null);
const showAddProjectMember = ref(false);
const selectedProjectUser = ref(null);
const selectedProjectRole = ref('development_team');

const projectOptions = computed(() => {
    return (props.projectLists || []).map((project) => ({
        title: `${project.name} - ${project.space?.name || 'Unknown Space'}`,

        value: project.id,
    }));
});

const selectedProject = computed(() => {
    const selectedId = Number(selectedProjectId.value);
    return (props.projectLists || []).find((project) => project.id === selectedId) || null;
});

const canManageSelectedProject = computed(() => {
    if (isAdmin.value) {
        return true;
    }

    const currentUserId = page.props?.auth?.user?.id;
    const projectMember = selectedProject.value?.members?.find((member) => member.id === currentUserId);

    return projectMember?.role === 'project_owner';
});

const availableProjectUsers = computed(() => {
    if (!selectedProject.value) {
        return [];
    }

    const assignedIds = new Set((selectedProject.value.members || []).map((member) => member.id));
    return (props.members || []).filter((member) => !assignedIds.has(member.id));
});

const getProjectRoleBadgeColor = (role) => {
    switch (role) {
        case 'project_owner': return 'error';
        case 'project_manager': return 'warning';
        case 'development_team': return 'primary';
        case 'guest': return 'grey';
        default: return 'grey';
    }
};

const getProjectRoleLabel = (role) => {
    const match = projectRoleItems.find((item) => item.value === role);
    return match?.title || role || 'Unknown';
};

const openAddProjectMemberDialog = () => {
    selectedProjectUser.value = null;
    selectedProjectRole.value = 'development_team';
    showAddProjectMember.value = true;
};

const addProjectMember = () => {
    if (!selectedProject.value || !selectedProjectUser.value) {
        return;
    }

    router.post(
        route('projects.members.add', [props.workspace.id, selectedProject.value.space.id, selectedProject.value.id]),
        {
            user_id: selectedProjectUser.value,
            role: selectedProjectRole.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showAddProjectMember.value = false;
                selectedProjectUser.value = null;
                selectedProjectRole.value = 'development_team';
            },
        }
    );
};

const changeProjectMemberRole = (project, member, role) => {
    router.patch(
        route('projects.members.role', [props.workspace.id, project.space.id, project.id]),
        {
            user_id: member.id,
            role,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
            },
        }
    );
};

const removeProjectMember = async (project, member) => {
    if (!await confirmDialog(`Remove ${member.name} from ${project.name}?`, 'Remove Project Member')) {
        return;
    }

    router.delete(
        route('projects.members.remove', [props.workspace.id, project.space.id, project.id]),
        {
            data: { user_id: member.id },
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
            },
        }
    );
};

// Delete workspace
const showDeleteWorkspace = ref(false);
const isDeletingWorkspace = ref(false);

const accessLayers = [
    { title: 'General Website', desc: 'Global account access — login, profile, and security settings.', icon: 'mdi-web', color: 'primary', hex: '#7B68EE' },
    { title: 'Workspace Access', desc: 'Admin / Member roles for workspace-wide capabilities.', icon: 'mdi-view-dashboard-outline', color: 'info', hex: '#49CCF9' },
    { title: 'Space Access', desc: 'Space-level membership control — manage who can access each space.', icon: 'mdi-layers-outline', color: 'warning', hex: '#FFB84D' },
    { title: 'Project Access', desc: 'Project-level roles: owner, manager, developer, guest.', icon: 'mdi-view-list-outline', color: 'success', hex: '#6BC950' },
];

const deleteWorkspace = () => {
    isDeletingWorkspace.value = true;
    router.delete(
        route('workspaces.destroy', props.workspace.id),
        {
            onError: () => {
                showSnackbar('Failed to delete workspace', 'error');
            },
            onFinish: () => {
                isDeletingWorkspace.value = false;
            },
        }
    );
};
</script>

<template>
    <MainLayout :title="`${workspace?.name} Settings`">
        <div class="settings-page">

            <!-- Page Header -->
            <div class="page-header mb-6">
                <div class="d-flex align-center ga-3">
                    <div class="header-icon">
                        <v-icon size="22" color="primary">mdi-cog</v-icon>
                    </div>
                    <div>
                        <h1 class="text-h6 font-weight-bold">Workspace Settings</h1>
                        <div class="text-caption text-medium-emphasis mt-1">
                            Manage <span class="text-primary font-weight-medium">{{ workspace?.name }}</span> — access
                            layers, members, and configuration
                        </div>
                    </div>
                </div>
            </div>

            <!-- Access Layers -->
            <div class="settings-section mb-5">
                <div class="section-label mb-3">
                    <v-icon size="14" color="primary" class="mr-1">mdi-shield-account-outline</v-icon>
                    Access Hierarchy
                </div>
                <div class="access-layers-grid">
                    <div class="access-layer-card" v-for="(layer, i) in accessLayers" :key="i"
                        :style="`--layer-color: ${layer.hex}`">
                        <div class="layer-badge" :style="`background: ${layer.hex}18; color: ${layer.hex}`">
                            <v-icon size="16" :color="layer.color">{{ layer.icon }}</v-icon>
                        </div>
                        <div class="layer-step">{{ String(i + 1).padStart(2, '0') }}</div>
                        <div class="layer-title">{{ layer.title }}</div>
                        <div class="layer-desc text-caption text-medium-emphasis">{{ layer.desc }}</div>
                        <div class="layer-connector" v-if="i < accessLayers.length - 1">
                            <v-icon size="14" color="grey-darken-1">mdi-chevron-right</v-icon>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Workspace Members -->
            <div class="settings-section mb-5">
                <div class="section-header">
                    <div class="d-flex align-center ga-2">
                        <div class="section-label">
                            <v-icon size="14" color="info" class="mr-1">mdi-account-group-outline</v-icon>
                            Workspace Members
                        </div>
                        <v-chip size="x-small" variant="tonal" color="info">{{ members?.length || 0 }}</v-chip>
                    </div>
                    <div v-if="isAdmin" class="d-flex ga-2">
                        <v-btn size="small" color="secondary" variant="tonal" rounded="lg"
                            @click="openCreateUserDialog">
                            <v-icon start size="16">mdi-account-plus-outline</v-icon>
                            Create User
                        </v-btn>
                        <v-btn size="small" color="primary" variant="flat" rounded="lg" @click="showAddMember = true">
                            <v-icon start size="16">mdi-account-multiple-plus</v-icon>
                            Add Member
                        </v-btn>
                    </div>
                </div>

                <div class="member-list">
                    <div v-if="!members?.length" class="empty-state">
                        <v-icon size="36" color="grey-darken-1">mdi-account-off-outline</v-icon>
                        <div class="text-body-2 text-medium-emphasis mt-2">No members yet</div>
                    </div>
                    <div v-for="member in members" :key="member.id" class="member-row">
                        <v-avatar size="34" :color="member.avatar_color || 'primary'" class="member-avatar">
                            <img v-if="member.profile_photo_url" :src="member.profile_photo_url" />
                            <span v-else class="text-xs font-weight-medium">{{ member.initials }}</span>
                        </v-avatar>
                        <div class="flex-1 min-w-0">
                            <div class="text-body-2 font-weight-medium text-truncate">{{ member.name }}</div>
                            <div class="text-caption text-medium-emphasis text-truncate">{{ member.email }}</div>
                        </div>
                        <v-menu v-if="isAdmin && canModifyWorkspaceMember(member)" location="bottom end">
                            <template #activator="{ props: menuProps }">
                                <v-chip v-bind="menuProps" :color="getRoleBadgeColor(member.pivot?.role || member.role)"
                                    size="x-small" variant="tonal" class="cursor-pointer role-chip font-weight-bold">
                                    {{ (member.pivot?.role || member.role || 'member').toUpperCase() }}
                                    <v-icon end size="12">mdi-chevron-down</v-icon>
                                </v-chip>
                            </template>
                            <v-card rounded="lg" elevation="4" min-width="150">
                                <v-list density="compact" nav>
                                    <v-list-item title="Admin" @click="changeRole(member, 'admin')"
                                        prepend-icon="mdi-shield-crown-outline" />
                                    <v-list-item title="Member" @click="changeRole(member, 'member')"
                                        prepend-icon="mdi-account-outline" />
                                </v-list>
                            </v-card>
                        </v-menu>
                        <v-chip v-else :color="getRoleBadgeColor(member.pivot?.role || member.role)" size="x-small"
                            variant="tonal" class="font-weight-bold">
                            {{ (member.pivot?.role || member.role || 'member').toUpperCase() }}
                        </v-chip>
                        <div v-if="isAdmin" class="member-actions">
                            <v-btn icon variant="text" size="x-small" color="info"
                                :href="route('workspaces.members.report', [workspace.id, member.id])"
                                title="View Report">
                                <v-icon size="15">mdi-chart-box-outline</v-icon>
                            </v-btn>
                            <v-btn icon variant="text" size="x-small" color="grey" @click="openEditUserDialog(member)">
                                <v-icon size="15">mdi-pencil-outline</v-icon>
                            </v-btn>
                            <v-btn icon variant="text" size="x-small" color="error"
                                :disabled="!canModifyWorkspaceMember(member)" @click="removeMember(member)">
                                <v-icon size="15">mdi-delete-outline</v-icon>
                            </v-btn>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scope Settings Navigation -->
            <div class="settings-section mb-5">
                <div class="section-label mb-3">
                    <v-icon size="14" color="warning" class="mr-1">mdi-sitemap-outline</v-icon>
                    Scope Access Management
                </div>
                <div class="scope-grid">
                    <div class="scope-card">
                        <div class="scope-card-header">
                            <div class="scope-card-icon bg-warning-subtle">
                                <v-icon size="18" color="warning">mdi-layers-outline</v-icon>
                            </div>
                            <div>
                                <div class="text-body-2 font-weight-bold">Spaces</div>
                                <div class="text-caption text-medium-emphasis">Manage space-level roles</div>
                            </div>
                        </div>
                        <div class="scope-links">
                            <v-btn v-for="space in spaces" :key="`space-${space.id}`" size="small" variant="tonal"
                                color="warning" rounded="lg" class="scope-link-btn"
                                @click="router.visit(route('spaces.settings', [workspace.id, space.id]))">
                                <v-icon start size="13">mdi-layers</v-icon>
                                {{ space.name }}
                            </v-btn>
                            <span v-if="!spaces?.length" class="text-caption text-medium-emphasis">No spaces
                                found.</span>
                        </div>
                    </div>

                    <div class="scope-card">
                        <div class="scope-card-header">
                            <div class="scope-card-icon bg-success-subtle">
                                <v-icon size="18" color="success">mdi-view-list-outline</v-icon>
                            </div>
                            <div>
                                <div class="text-body-2 font-weight-bold">Products</div>
                                <div class="text-caption text-medium-emphasis">Manage project member roles</div>
                            </div>
                        </div>
                        <div class="scope-links">
                            <v-btn v-for="project in projectLists" :key="`project-${project.id}`" size="small"
                                variant="tonal" color="success" rounded="lg" class="scope-link-btn"
                                @click="router.visit(route('projects.settings', [workspace.id, project.space.id, project.id]))">
                                <v-icon start size="13">mdi-view-list</v-icon>
                                {{ project.name }}
                            </v-btn>
                            <span v-if="!projectLists?.length" class="text-caption text-medium-emphasis">No products
                                found.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div v-if="isOwner" class="danger-zone">
                <div class="danger-zone-header">
                    <v-icon size="16" color="error" class="mr-1">mdi-alert-circle-outline</v-icon>
                    <span>Danger Zone</span>
                </div>
                <div class="danger-zone-body">
                    <div class="flex-1 min-w-0">
                        <div class="text-body-2 font-weight-bold mb-1">Delete Workspace</div>
                        <div class="text-caption text-medium-emphasis">
                            Permanently delete this workspace, all spaces, folders, lists, and tasks. This action cannot
                            be undone.
                        </div>
                    </div>
                    <v-btn color="error" variant="outlined" size="small" rounded="lg"
                        @click="showDeleteWorkspace = true">
                        <v-icon start size="16">mdi-delete-outline</v-icon>
                        Delete Workspace
                    </v-btn>
                </div>
            </div>
        </div>

        <!-- Add Member Dialog -->
        <v-dialog v-model="showAddMember" max-width="460" rounded="xl">
            <v-card rounded="xl">
                <div class="dialog-header">
                    <div class="dialog-header-icon bg-primary-subtle">
                        <v-icon color="primary" size="20">mdi-account-multiple-plus</v-icon>
                    </div>
                    <div>
                        <div class="text-subtitle-2 font-weight-bold">Add Member</div>
                        <div class="text-caption text-medium-emphasis">Add user by email or select from list</div>
                    </div>
                    <v-spacer />
                    <v-btn icon variant="text" size="x-small" @click="showAddMember = false">
                        <v-icon size="18">mdi-close</v-icon>
                    </v-btn>
                </div>
                <v-divider />
                <v-card-text class="pt-4">
                    <!-- Autocomplete from known users (including already-members for role update) -->
                    <v-autocomplete
                        v-model="selectedUser"
                        :items="availableUsers"
                        item-title="name"
                        item-value="id"
                        label="Search by name"
                        variant="outlined"
                        density="comfortable"
                        clearable
                        class="mb-3"
                        placeholder="Start typing a name…"
                    >
                        <template #item="{ props: itemProps, item }">
                            <v-list-item v-bind="itemProps">
                                <template #prepend>
                                    <v-avatar size="30" :color="item.raw.avatar_color || 'primary'" class="mr-2">
                                        <span class="text-[10px] font-weight-medium">{{ item.raw.initials }}</span>
                                    </v-avatar>
                                </template>
                                <v-list-item-subtitle>{{ item.raw.email }}</v-list-item-subtitle>
                            </v-list-item>
                        </template>
                    </v-autocomplete>

                    <div class="d-flex align-center ga-2 mb-3">
                        <v-divider />
                        <span class="text-caption text-medium-emphasis px-1">or by email</span>
                        <v-divider />
                    </div>

                    <!-- Direct email input as fallback -->
                    <v-text-field
                        v-model="inviteEmail"
                        :disabled="!!selectedUser"
                        label="Email address"
                        type="email"
                        variant="outlined"
                        density="comfortable"
                        placeholder="user@example.com"
                        class="mb-3"
                        hide-details
                    />

                    <v-select v-model="selectedRole"
                        :items="[{ title: 'Admin', value: 'admin' }, { title: 'Member', value: 'member' }]"
                        label="Role" variant="outlined" density="comfortable" />
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-spacer />
                    <v-btn variant="text" rounded="lg" @click="showAddMember = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat" rounded="lg"
                        :disabled="!selectedUser && !inviteEmail.trim()"
                        @click="addMember">
                        Add Member
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Create User Dialog -->
        <v-dialog v-model="showCreateUser" max-width="500" rounded="xl">
            <v-card rounded="xl">
                <div class="dialog-header">
                    <div class="dialog-header-icon bg-secondary-subtle">
                        <v-icon color="secondary" size="20">mdi-account-plus-outline</v-icon>
                    </div>
                    <div>
                        <div class="text-subtitle-2 font-weight-bold">Create User</div>
                        <div class="text-caption text-medium-emphasis">Create a new account and add to workspace</div>
                    </div>
                    <v-spacer />
                    <v-btn icon variant="text" size="x-small" @click="showCreateUser = false">
                        <v-icon size="18">mdi-close</v-icon>
                    </v-btn>
                </div>
                <v-divider />
                <v-card-text class="pt-4">
                    <div class="dialog-grid-2 mb-3">
                        <v-text-field v-model="createUserForm.name" label="Full Name" variant="outlined"
                            density="comfortable" autofocus hide-details />
                        <v-text-field v-model="createUserForm.email" label="Email" type="email" variant="outlined"
                            density="comfortable" hide-details />
                    </div>
                    <div class="dialog-grid-2 mb-3">
                        <v-text-field v-model="createUserForm.password" label="Password" type="password"
                            variant="outlined" density="comfortable" hide-details />
                        <v-text-field v-model="createUserForm.password_confirmation" label="Confirm Password"
                            type="password" variant="outlined" density="comfortable" hide-details />
                    </div>
                    <div class="dialog-grid-2">
                        <v-text-field v-model.number="createUserForm.hourly_rate" label="Hourly Rate (Rp)" type="number"
                            min="0" step="1000" variant="outlined" density="comfortable" prepend-inner-icon="mdi-cash"
                            hide-details />
                        <v-select v-model="createUserForm.role"
                            :items="[{ title: 'Admin', value: 'admin' }, { title: 'Member', value: 'member' }]"
                            label="Workspace Role" variant="outlined" density="comfortable" hide-details />
                    </div>
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-spacer />
                    <v-btn variant="text" rounded="lg" @click="showCreateUser = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat" rounded="lg"
                        :disabled="!createUserForm.name.trim() || !createUserForm.email.trim() || !createUserForm.password"
                        @click="createUser">Create User</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Edit User Dialog -->
        <v-dialog v-model="showEditUser" max-width="440" rounded="xl">
            <v-card rounded="xl">
                <div class="dialog-header">
                    <div class="dialog-header-icon bg-info-subtle">
                        <v-icon color="info" size="20">mdi-account-edit-outline</v-icon>
                    </div>
                    <div>
                        <div class="text-subtitle-2 font-weight-bold">Edit User</div>
                        <div class="text-caption text-medium-emphasis">{{ editingUser?.name }}</div>
                    </div>
                    <v-spacer />
                    <v-btn icon variant="text" size="x-small" @click="showEditUser = false">
                        <v-icon size="18">mdi-close</v-icon>
                    </v-btn>
                </div>
                <v-divider />
                <v-card-text class="pt-4 d-flex flex-column ga-3">
                    <v-text-field v-model="editUserForm.name" label="Full Name" variant="outlined" density="comfortable"
                        hide-details />
                    <v-text-field v-model="editUserForm.email" label="Email" type="email" variant="outlined"
                        density="comfortable" hide-details />
                    <v-text-field v-model.number="editUserForm.hourly_rate" label="Hourly Rate (Rp)" type="number"
                        min="0" step="1000" variant="outlined" density="comfortable" prepend-inner-icon="mdi-cash"
                        hide-details />
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-spacer />
                    <v-btn variant="text" rounded="lg" @click="showEditUser = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat" rounded="lg"
                        :disabled="!editUserForm.name.trim() || !editUserForm.email.trim()" @click="updateUser">Save
                        Changes</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Workspace Dialog -->
        <DeleteConfirmDialog
            v-model="showDeleteWorkspace"
            item-type="workspace"
            :item-name="workspace?.name"
            :loading="isDeletingWorkspace"
            @confirm="deleteWorkspace"
        />
    </MainLayout>
</template>

<style scoped>
/*  Page Layout  */
.settings-page {
    padding: 24px;
    max-width: 1040px;
    margin: 0 auto;
}

/*  Page Header  */
.page-header {
    padding: 20px 24px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 14px;
}

.header-icon {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(123, 104, 238, 0.12);
    border-radius: 12px;
    flex-shrink: 0;
}

/*  Section shared  */
.settings-section {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 14px;
    overflow: hidden;
}

.section-label {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.45);
    padding: 12px 16px 0;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

/*  Access Layers  */
.access-layers-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0;
    padding: 0 16px 16px;
}

.access-layer-card {
    position: relative;
    padding: 16px 12px 14px;
    border-radius: 10px;
    transition: background 0.2s ease;
}

.access-layer-card:hover {
    background: rgba(255, 255, 255, 0.03);
}

.layer-badge {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
}

.layer-step {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.05em;
    color: rgba(255, 255, 255, 0.2);
    margin-bottom: 4px;
}

.layer-title {
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 5px;
    color: var(--layer-color);
}

.layer-desc {
    font-size: 11.5px;
    line-height: 1.5;
}

.layer-connector {
    position: absolute;
    right: -8px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1;
}

/*  Member List  */
.member-list {
    display: flex;
    flex-direction: column;
}

.member-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    transition: background 0.15s ease;
}

.member-row:last-child {
    border-bottom: none;
}

.member-row:hover {
    background: rgba(255, 255, 255, 0.025);
}

.member-row:hover .member-actions {
    opacity: 1;
}

.member-avatar {
    flex-shrink: 0;
}

.role-chip {
    cursor: pointer;
}

.member-actions {
    display: flex;
    gap: 2px;
    opacity: 0;
    transition: opacity 0.15s ease;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 48px 20px;
}

/*  Scope Settings  */
.scope-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    padding: 0 16px 16px;
}

.scope-card {
    padding: 14px;
    background: rgba(255, 255, 255, 0.015);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}

.scope-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.scope-card-icon {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.bg-warning-subtle {
    background: rgba(255, 184, 77, 0.12);
}

.bg-success-subtle {
    background: rgba(107, 201, 80, 0.12);
}

.bg-primary-subtle {
    background: rgba(123, 104, 238, 0.12);
}

.bg-secondary-subtle {
    background: rgba(139, 92, 246, 0.12);
}

.bg-info-subtle {
    background: rgba(73, 204, 249, 0.12);
}

.bg-error-subtle {
    background: rgba(255, 107, 107, 0.12);
}

.scope-links {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.scope-link-btn {
    font-size: 12px !important;
}

/*  Danger Zone  */
.danger-zone {
    border: 1px solid rgba(255, 107, 107, 0.25);
    border-radius: 14px;
    overflow: hidden;
}

.danger-zone-header {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgb(255, 107, 107);
    background: rgba(255, 107, 107, 0.06);
    border-bottom: 1px solid rgba(255, 107, 107, 0.15);
}

.danger-zone-body {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px;
}

/*  Dialogs  */
.dialog-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
}

.dialog-header-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.dialog-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

/*  Responsive  */
@media (max-width: 768px) {
    .access-layers-grid {
        grid-template-columns: 1fr 1fr;
    }

    .layer-connector {
        display: none;
    }

    .scope-grid {
        grid-template-columns: 1fr;
    }

    .dialog-grid-2 {
        grid-template-columns: 1fr;
    }

    .danger-zone-body {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 480px) {
    .settings-page {
        padding: 16px;
    }

    .access-layers-grid {
        grid-template-columns: 1fr;
    }

    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}
</style>