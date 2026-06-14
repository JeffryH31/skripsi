<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import ColorPicker from '@/Components/ColorPicker.vue';
import { normalizeHexColor } from '@/utils/color';
import { useSnackbar } from '@/composables/useSnackbar';

const { showSnackbar } = useSnackbar();
const page = usePage();
const isSuperAdmin = computed(() => page.props.isSuperAdmin || false);

const props = defineProps({
    workspaces: { type: Array, default: () => [] },
});

const showCreateDialog = ref(false);
const workspaceName = ref('');
const workspaceColor = ref('#3B82F6');
const isCreating = ref(false);

const openDialog = () => {
    workspaceName.value = '';
    workspaceColor.value = '#3B82F6';
    showCreateDialog.value = true;
};

const createWorkspace = () => {
    if (!workspaceName.value.trim() || isCreating.value) return;
    isCreating.value = true;

    router.post(
        route('workspaces.store'),
        {
            name: workspaceName.value.trim(),
            color: normalizeHexColor(workspaceColor.value),
        },
        {
            onSuccess: () => {
                showCreateDialog.value = false;
                router.visit(route('dashboard'));
            },
            onError: () => showSnackbar('Failed to create workspace', 'error'),
            onFinish: () => {
                isCreating.value = false;
            },
        }
    );
};
</script>

<template>
    <MainLayout title="Workspaces">
        <Head title="Workspaces" />

        <div class="workspaces-index-page">
            <!-- Has workspaces: list them -->
            <template v-if="workspaces && workspaces.length > 0">
                <div class="page-header mb-6">
                    <h1 class="text-2xl font-bold">Your Workspaces</h1>
                    <v-btn v-if="isSuperAdmin" color="primary" @click="openDialog">
                        <v-icon start>mdi-plus</v-icon>
                        New Workspace
                    </v-btn>
                </div>

                <div class="workspaces-grid">
                    <v-card
                        v-for="ws in workspaces"
                        :key="ws.id"
                        class="workspace-card"
                        @click="router.visit(route('workspaces.show', ws.id))"
                        hover
                    >
                        <div class="workspace-card-accent" :style="{ backgroundColor: ws.color || '#6366F1' }"></div>
                        <v-card-text class="pa-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="workspace-avatar"
                                    :style="{ backgroundColor: ws.color || '#6366F1' }"
                                >
                                    {{ ws.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <div>
                                    <div class="font-semibold text-base">{{ ws.name }}</div>
                                    <div class="text-sm text-gray-500">{{ ws.members_count ?? 0 }} member(s)</div>
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>
                </div>
            </template>

            <!-- No workspaces: empty state -->
            <template v-else>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <v-icon size="72" color="primary">mdi-briefcase-outline</v-icon>
                    </div>
                    <h2 class="empty-state-title text-white">No workspaces yet</h2>
                    <p class="empty-state-subtitle">
                        Create your first workspace to start managing projects and your team.
                    </p>
                    <v-btn color="primary" size="large" @click="openDialog">
                        <v-icon start>mdi-plus</v-icon>
                        Create Workspace
                    </v-btn>
                </div>
            </template>
        </div>

        <!-- Create Workspace Dialog -->
        <v-dialog v-model="showCreateDialog" max-width="480" persistent>
            <v-card>
                <v-card-title class="pa-6 pb-2">
                    <span class="text-h6 font-bold">Create New Workspace</span>
                </v-card-title>

                <v-card-text class="pa-6 pt-4">
                    <v-text-field
                        v-model="workspaceName"
                        label="Workspace Name"
                        placeholder="e.g. Marketing Team"
                        variant="outlined"
                        density="comfortable"
                        autofocus
                        @keyup.enter="createWorkspace"
                    />

                    <div class="mt-4">
                        <ColorPicker v-model="workspaceColor" />
                    </div>
                </v-card-text>

                <v-card-actions class="pa-6 pt-0">
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateDialog = false" :disabled="isCreating">
                        Cancel
                    </v-btn>
                    <v-btn
                        color="primary"
                        :loading="isCreating"
                        :disabled="!workspaceName.trim()"
                        @click="createWorkspace"
                    >
                        Create
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </MainLayout>
</template>

<style scoped>
.workspaces-index-page {
    padding: 32px;
    max-width: 960px;
    margin: 0 auto;
}

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.workspaces-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 16px;
}

.workspace-card {
    cursor: pointer;
    overflow: hidden;
    position: relative;
}

.workspace-card-accent {
    height: 4px;
    width: 100%;
}

.workspace-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 18px;
    flex-shrink: 0;
}

/* Empty state */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 60vh;
    text-align: center;
    gap: 16px;
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
}

.empty-state-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.empty-state-subtitle {
    font-size: 1rem;
    color: #64748b;
    max-width: 360px;
    line-height: 1.6;
    margin-bottom: 8px;
}


</style>
