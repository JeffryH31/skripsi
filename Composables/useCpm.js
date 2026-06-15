import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useSnackbar } from '@/composables/useSnackbar';
import { safeFetch } from '@/utils/safeFetch';

export function useCpm(context) {
    const { showSnackbar } = useSnackbar();
    const cpmData = ref(null);
    const loading = ref(false);

    const resolveIds = () => {
        const workspace = context.workspace?.value ?? context.workspace;
        const space = context.space?.value ?? context.space;
        const list = context.list?.value ?? context.list;
        const parentTask = context.parentTask?.value ?? context.parentTask;
        if (!workspace || !space || !list || !parentTask) return null;
        return [workspace.id, space.id, list.id, parentTask.id];
    };

    const fetchCpmData = async () => {
        const ids = resolveIds();
        if (!ids) return;

        loading.value = true;
        try {
            const response = await safeFetch(route('tasks.cpm.analyze', ids), { method: 'GET' });

            if (response.ok) {
                cpmData.value = await response.json();
            } else {
                cpmData.value = { success: false, message: 'Failed to fetch CPM data' };
            }
        } catch (error) {
            console.error('Error fetching CPM data:', error);
            cpmData.value = { success: false, message: 'An error occurred while calculating CPM' };
        } finally {
            loading.value = false;
        }
    };

    const mutateDependency = ({ subtaskId, dependsOnId, mode }) => {
        const ids = resolveIds();
        if (!ids) return;

        const routeName = mode === 'add'
            ? 'tasks.cpm.dependencies.add'
            : 'tasks.cpm.dependencies.remove';

        const payload = mode === 'add'
            ? { subtask_id: subtaskId, depends_on_id: dependsOnId, type: 'blocks' }
            : { subtask_id: subtaskId, depends_on_id: dependsOnId };

        const successMessage = mode === 'add' ? 'Dependency added!' : 'Dependency removed!';
        const errorMessage = mode === 'add'
            ? 'Failed to add dependency'
            : 'Failed to remove dependency';

        const options = {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showSnackbar(successMessage, 'success');
                fetchCpmData();
            },
            onError: (errors) => {
                const message = errors?.error || errors?.message || errorMessage;
                showSnackbar(message, 'error');
            },
        };

        if (mode === 'add') {
            router.post(route(routeName, ids), payload, options);
        } else {
            router.delete(route(routeName, ids), { ...options, data: payload });
        }
    };

    const addDependency = (payload) => mutateDependency({ ...payload, mode: 'add' });
    const removeDependency = (payload) => mutateDependency({ ...payload, mode: 'remove' });

    const reset = () => {
        cpmData.value = null;
    };

    return {
        cpmData,
        loading,
        fetchCpmData,
        addDependency,
        removeDependency,
        reset,
    };
}
