import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { safeFetch } from '@/utils/safeFetch';
import { useSnackbar } from '@/composables/useSnackbar';

export function useTaskTimer(props, localTask) {
    const page = usePage();
    const { showSnackbar } = useSnackbar();

    const isTracking = ref(false);
    const trackingDuration = ref(0);
    const trackingInterval = ref(null);
    const runningEntryId = ref(null);
    const isTimerLoading = ref(false);

    const formatTrackingDuration = computed(() => {
        const h = Math.floor(trackingDuration.value / 3600);
        const m = Math.floor((trackingDuration.value % 3600) / 60);
        const s = trackingDuration.value % 60;
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    });

    const startInterval = () => {
        if (trackingInterval.value) clearInterval(trackingInterval.value);
        trackingInterval.value = setInterval(() => { trackingDuration.value++; }, 1000);
    };

    const stopInterval = () => {
        if (trackingInterval.value) {
            clearInterval(trackingInterval.value);
            trackingInterval.value = null;
        }
    };

    const getTimerRouteParams = () => {
        const workspaceId = props.workspace?.id;
        const spaceId = props.space?.id;
        const listId = props.list?.id;
        const taskId = props.parentTask?.id;

        if (!workspaceId || !spaceId || !listId || !taskId) {
            return null;
        }

        return [workspaceId, spaceId, listId, taskId];
    };

    const init = async () => {
        if (!props.parentTask || !props.task?.id) return;
        const currentId = Number(props.task.id);

        const entries = Array.isArray(props.task?.time_entries) ? props.task.time_entries : [];
        const running = entries.find((entry) => entry && entry.is_running);

        if (running && Number(running.subtask_id) === currentId) {
            isTracking.value = true;
            runningEntryId.value = running.id ?? null;
            trackingDuration.value = Math.floor((Date.now() - new Date(running.started_at).getTime()) / 1000);
            startInterval();
            return;
        }

        const global = page.props.runningTimer;
        if (global?.subtask_id === currentId && global?.is_running) {
            isTracking.value = true;
            runningEntryId.value = global.id;
            trackingDuration.value = Math.floor((Date.now() - new Date(global.started_at).getTime()) / 1000);
            startInterval();
            return;
        }

        try {
            const res = await safeFetch(route('time-tracking.running'), { method: 'GET' });
            if (res.ok && props.task?.id === currentId) {
                const data = await res.json().catch(() => ({}));
                const t = data.timer;
                if (t?.subtask_id === currentId && (t?.is_running ?? false)) {
                    isTracking.value = true;
                    runningEntryId.value = t.id;
                    trackingDuration.value = Math.floor((Date.now() - new Date(t.started_at).getTime()) / 1000);
                    startInterval();
                }
            }
        } catch {}
    };

    const start = async () => {
        if (!props.parentTask || isTimerLoading.value) return;

        const routeParams = getTimerRouteParams();
        const subtaskId = props.task?.id;
        if (!routeParams || !subtaskId) {
            showSnackbar('Task context is not ready yet. Please try again.', 'warning');
            return;
        }

        isTimerLoading.value = true;
        try {
            const url = route('tasks.timer.start', routeParams);
            const res = await safeFetch(url, {
                method: 'POST',
                body: JSON.stringify({ subtask_id: subtaskId }),
            });
            if (res.ok || res.status === 302 || res.status === 303) {
                const data = await res.json().catch(() => ({}));
                isTracking.value = true;
                trackingDuration.value = 0;
                runningEntryId.value = data.timeEntry?.id || data.time_entry?.id || data.id || null;
                startInterval();
                router.reload({ preserveScroll: true, only: ['task', 'tasksByStatus', 'runningTimer'] });
                showSnackbar('Timer started!', 'success');
            } else if (res.status === 419) {
                router.reload();
            } else {
                const data = await res.json().catch(() => ({}));
                showSnackbar(data.message || 'Failed to start timer', 'error');
            }
        } catch {
            showSnackbar('Failed to start timer', 'error');
        } finally {
            isTimerLoading.value = false;
        }
    };

    const stop = async () => {
        if (isTimerLoading.value) return;

        const routeParams = getTimerRouteParams();
        if (!routeParams) {
            showSnackbar('Task context is not ready yet. Please try again.', 'warning');
            return;
        }

        isTimerLoading.value = true;
        stopInterval();

        let entryId = runningEntryId.value;
        if (!entryId) entryId = props.task?.time_entries?.find(e => e.is_running)?.id;
        if (!entryId) {
            const g = page.props.runningTimer;
            if (g?.subtask_id === props.task?.id && g?.is_running) entryId = g.id;
        }
        if (!entryId) {
            try {
                const res = await safeFetch(route('time-tracking.running'), { method: 'GET' });
                if (res.ok) {
                    const d = await res.json().catch(() => ({}));
                    if (d.timer?.id) entryId = d.timer.id;
                }
            } catch {}
        }

        if (!entryId) {
            showSnackbar('No running timer found.', 'warning');
            isTracking.value = false;
            trackingDuration.value = 0;
            isTimerLoading.value = false;
            router.reload({ preserveScroll: true });
            return;
        }

        try {
            const url = route('tasks.timer.stop', [...routeParams, entryId]);
            const res = await safeFetch(url, { method: 'POST' });
            if (res.ok || res.status === 302 || res.status === 303) {
                const data = await res.json().catch(() => ({}));
                isTracking.value = false;
                runningEntryId.value = null;
                showSnackbar(`Timer stopped: ${formatTrackingDuration.value}`, 'success');
                if (data.timeEntry) {
                    const entry = data.timeEntry;
                    if (localTask.value) {
                        localTask.value.time_spent = (localTask.value.time_spent || 0) + (entry.duration || 0);
                        if (Array.isArray(localTask.value.time_entries)) {
                            const idx = localTask.value.time_entries.findIndex(e => e.id === entry.id);
                            if (idx >= 0) localTask.value.time_entries[idx] = entry;
                            else localTask.value.time_entries.push(entry);
                        }
                    }
                } else {
                    if (localTask.value) {
                        localTask.value.time_spent = (localTask.value.time_spent || 0) + Math.max(1, Math.ceil(trackingDuration.value / 60));
                    }
                }
                trackingDuration.value = 0;
                router.reload({ preserveScroll: true, only: ['task', 'tasksByStatus', 'runningTimer'] });
            } else if (res.status === 419) {
                router.reload();
            } else {
                showSnackbar('Failed to stop timer', 'error');
            }
        } catch {
            showSnackbar('Failed to stop timer', 'error');
        } finally {
            isTimerLoading.value = false;
        }
    };

    const reset = () => {
        stopInterval();
        isTracking.value = false;
        trackingDuration.value = 0;
        runningEntryId.value = null;
        isTimerLoading.value = false;
    };

    watch(() => props.task?.time_entries, () => {
        if (!isTracking.value) {
            init();
        } else {
            const running = props.task?.time_entries?.find(e => e.is_running);
            if (running) {
                runningEntryId.value = running.id;
            } else {
                stopInterval();
                isTracking.value = false;
                trackingDuration.value = 0;
                runningEntryId.value = null;
            }
        }
    }, { deep: true });

    return { isTracking, trackingDuration, formatTrackingDuration, isTimerLoading, start, stop, init, reset, stopInterval };
}
