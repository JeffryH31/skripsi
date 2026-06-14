<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useSnackbar } from '@/composables/useSnackbar';

const { confirm: confirmDialog } = useConfirmDialog();
const { showSnackbar } = useSnackbar();

const props = defineProps({
    workspace: { type: Object, default: null },
    space: { type: Object, default: null },
    list: { type: Object, default: null },
    members: { type: Array, default: () => [] },
    availableUsers: { type: Array, default: () => [] },
    canManageMembers: { type: Boolean, default: false },
});

const showAddMemberDialog = ref(false);
const selectedUserId = ref(null);
const selectedRole = ref('development_team');

const projectRoleItems = [
    { title: 'Project Owner', value: 'project_owner' },
    { title: 'Project Manager', value: 'project_manager' },
    { title: 'Development Team', value: 'development_team' },
    { title: 'Guest', value: 'guest' },
];

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
    const matched = projectRoleItems.find((item) => item.value === role);
    return matched?.title || role || 'Unknown';
};

const openAddMemberDialog = () => {
    selectedUserId.value = null;
    selectedRole.value = 'development_team';
    showAddMemberDialog.value = true;
};

const addMember = () => {
    if (!selectedUserId.value) {
        return;
    }

    router.post(
        route('projects.members.add', [props.workspace.id, props.space.id, props.list.id]),
        {
            user_id: selectedUserId.value,
            role: selectedRole.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showAddMemberDialog.value = false;
                selectedUserId.value = null;
                selectedRole.value = 'development_team';
            },
            onError: () => showSnackbar('Failed to add member', 'error'),
        }
    );
};

const changeMemberRole = (member, role) => {
    router.patch(
        route('projects.members.role', [props.workspace.id, props.space.id, props.list.id]),
        {
            user_id: member.id,
            role,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
            },
            onError: () => showSnackbar('Failed to update member role', 'error'),
        }
    );
};

const removeMember = async (member) => {
    const ok = await confirmDialog(
        `Remove ${member.name} from ${props.list.name}?`,
        'Remove Member'
    );
    if (!ok) return;

    router.delete(
        route('projects.members.remove', [props.workspace.id, props.space.id, props.list.id]),
        {
            data: { user_id: member.id },
            preserveScroll: true,
            onSuccess: () => {
            },
            onError: () => showSnackbar('Failed to remove member', 'error'),
        }
    );
};
</script>

<template>
    <Head :title="`${list?.name} Project Access`" />

    <MainLayout :title="`${list?.name} Access Settings`">
        <div class="settings-page">
            <div class="settings-header">
                <div class="d-flex align-center ga-2 mb-2">
                    <v-btn variant="text" size="small" @click="router.visit(route('projects.show', [workspace.id, space.id, list.id]))">
                        <v-icon start size="16">mdi-arrow-left</v-icon>
                        Back to Project
                    </v-btn>
                    <v-btn variant="text" size="small" @click="router.visit(route('spaces.settings', [workspace.id, space.id]))">
                        <v-icon start size="16">mdi-shield-home-outline</v-icon>
                        Space Access
                    </v-btn>
                </div>
                <h1 class="text-2xl font-bold">Project Access Settings</h1>
                <p class="text-gray-500 mt-1">Manage membership and roles for {{ list?.name }}</p>
            </div>

            <v-card variant="outlined" rounded="lg" class="mt-6">
                <v-card-text class="d-flex flex-wrap align-center ga-2">
                    <v-chip color="success" variant="tonal" size="small">
                        Active Project
                    </v-chip>
                    <v-chip color="primary" variant="tonal" size="small">
                        {{ members?.length || 0 }} members
                    </v-chip>
                    <v-chip color="info" variant="tonal" size="small">
                        Space: {{ space?.name }}
                    </v-chip>
                </v-card-text>
            </v-card>

            <v-card variant="outlined" rounded="lg" class="mt-6">
                <v-card-title class="d-flex align-center justify-space-between">
                    <span>Project Members</span>
                    <v-btn
                        v-if="canManageMembers"
                        color="primary"
                        size="small"
                        variant="tonal"
                        :disabled="!availableUsers?.length"
                        @click="openAddMemberDialog"
                    >
                        <v-icon start size="16">mdi-account-plus</v-icon>
                        Add Project Member
                    </v-btn>
                </v-card-title>
                <v-divider />
                <v-table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="member in members" :key="member.id">
                            <td>
                                <div class="d-flex align-center ga-3">
                                    <v-avatar size="32" :color="member.avatar_color || 'primary'">
                                        <img v-if="member.profile_photo_url" :src="member.profile_photo_url" />
                                        <span v-else class="text-xs">{{ member.initials }}</span>
                                    </v-avatar>
                                    <span class="font-medium">{{ member.name }}</span>
                                </div>
                            </td>
                            <td>{{ member.email }}</td>
                            <td>
                                <v-menu v-if="canManageMembers && member.role !== 'project_owner'">
                                    <template #activator="{ props: menuProps }">
                                        <v-chip
                                            v-bind="menuProps"
                                            :color="getProjectRoleBadgeColor(member.role)"
                                            size="small"
                                            class="cursor-pointer"
                                        >
                                            {{ getProjectRoleLabel(member.role) }}
                                        </v-chip>
                                    </template>
                                    <v-card color="surface">
                                        <v-list density="compact">
                                            <v-list-item
                                                v-for="role in projectRoleItems"
                                                :key="role.value"
                                                :title="role.title"
                                                @click="changeMemberRole(member, role.value)"
                                            />
                                        </v-list>
                                    </v-card>
                                </v-menu>
                                <v-chip v-else :color="getProjectRoleBadgeColor(member.role)" size="small">
                                    {{ getProjectRoleLabel(member.role) }}
                                </v-chip>
                            </td>
                            <td>
                                <v-btn
                                    v-if="canManageMembers"
                                    icon
                                    variant="text"
                                    size="small"
                                    color="error"
                                    :disabled="member.role === 'project_owner'"
                                    @click="removeMember(member)"
                                >
                                    <v-icon size="18">mdi-delete</v-icon>
                                </v-btn>
                            </td>
                        </tr>
                        <tr v-if="!members?.length">
                            <td colspan="4" class="text-center py-8 text-gray-500">
                                No project members yet
                            </td>
                        </tr>
                    </tbody>
                </v-table>
            </v-card>
        </div>

        <v-dialog v-model="showAddMemberDialog" max-width="520">
            <v-card>
                <v-card-title>Add Project Member</v-card-title>
                <v-card-text>
                    <v-alert v-if="!availableUsers?.length" type="warning" variant="tonal" class="mb-4">
                        All workspace members are already assigned to this project.
                    </v-alert>

                    <v-select
                        v-model="selectedUserId"
                        :items="availableUsers"
                        item-title="name"
                        item-value="id"
                        label="Select Workspace Member"
                        variant="outlined"
                        class="mb-4"
                    >
                        <template #item="{ props: itemProps, item }">
                            <v-list-item v-bind="itemProps">
                                <template #prepend>
                                    <v-avatar size="32" :color="item.raw.avatar_color || 'primary'">
                                        <span class="text-xs">{{ item.raw.initials }}</span>
                                    </v-avatar>
                                </template>
                                <v-list-item-title>{{ item.raw.name }}</v-list-item-title>
                                <v-list-item-subtitle>{{ item.raw.email }}</v-list-item-subtitle>
                            </v-list-item>
                        </template>
                    </v-select>

                    <v-select
                        v-model="selectedRole"
                        :items="projectRoleItems"
                        item-title="title"
                        item-value="value"
                        label="Project Role"
                        variant="outlined"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showAddMemberDialog = false">Cancel</v-btn>
                    <v-btn
                        color="primary"
                        :disabled="!selectedUserId || !availableUsers?.length"
                        @click="addMember"
                    >
                        Add Member
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </MainLayout>
</template>

<style scoped>
.settings-page {
    padding: 24px;
    max-width: 1000px;
    margin: 0 auto;
}

.settings-header {
    margin-bottom: 24px;
}
</style>
