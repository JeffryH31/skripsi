/**
 * Timer Composable
 * 
 * Handles all timer-related logic for time tracking
 */
import { ref, computed } from 'vue';

export function useTimer(notify = () => {}) {
    // Timer System - Tracks active work sessions
    const activeTimers = ref({}); // { taskId: { startTime, status: 'working'|'paused', pausedDuration, lastPauseTime } }
    const timerDisplays = ref({}); // { taskId: 'HH:MM:SS' }
    let timerInterval = null;

    // Format milliseconds to HH:MM:SS
    const formatElapsedTime = (ms) => {
        const totalSeconds = Math.floor(ms / 1000);
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    };

    // Convert milliseconds to hours (for logging)
    const msToHours = (ms) => {
        return Math.round((ms / 3600000) * 100) / 100; // Round to 2 decimal places
    };

    // Start timer update loop
    const startTimerLoop = () => {
        if (timerInterval) return;
        timerInterval = setInterval(() => {
            Object.keys(activeTimers.value).forEach(taskId => {
                const timer = activeTimers.value[taskId];
                if (timer.status === 'working') {
                    const elapsed = Date.now() - timer.startTime - timer.pausedDuration;
                    timerDisplays.value[taskId] = formatElapsedTime(elapsed);
                }
            });
        }, 1000);
    };

    const stopTimerLoop = () => {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    };

    // Get timer status for a task
    const getTimerStatus = (taskId) => {
        return activeTimers.value[taskId]?.status || null;
    };

    // Get elapsed time display for a task
    const getTimerDisplay = (taskId) => {
        return timerDisplays.value[taskId] || '00:00:00';
    };

    // Check if any timer is active
    const hasActiveTimer = computed(() => {
        return Object.values(activeTimers.value).some(t => t.status === 'working');
    });

    // Pause a timer
    const pauseTimer = (taskId) => {
        const timer = activeTimers.value[taskId];
        if (timer && timer.status === 'working') {
            timer.status = 'paused';
            timer.lastPauseTime = Date.now();
            notify('Timer paused - On Hold');
            return true;
        }
        return false;
    };

    // Start working on a task
    const startWorkingOn = (task, feature = null) => {
        // Stop any other active timer first
        Object.keys(activeTimers.value).forEach(taskId => {
            if (activeTimers.value[taskId].status === 'working' && parseInt(taskId) !== task.id) {
                pauseTimer(parseInt(taskId));
            }
        });

        if (activeTimers.value[task.id]) {
            // Resume from pause
            const timer = activeTimers.value[task.id];
            if (timer.status === 'paused') {
                timer.pausedDuration += Date.now() - timer.lastPauseTime;
                timer.status = 'working';
            }
        } else {
            // Start new timer
            activeTimers.value[task.id] = {
                startTime: Date.now(),
                status: 'working',
                pausedDuration: 0,
                lastPauseTime: null,
                description: '',
                taskTitle: task.title,
                featureTitle: feature?.title || ''
            };
            timerDisplays.value[task.id] = '00:00:00';
        }

        startTimerLoop();
        notify(`Started working on "${task.title}"`);
    };

    // Stop timer and get elapsed time
    const stopTimerAndLog = (task, feature = null, description = '', onComplete = null) => {
        const timer = activeTimers.value[task.id];
        if (!timer) return null;

        let elapsedMs;
        if (timer.status === 'working') {
            elapsedMs = Date.now() - timer.startTime - timer.pausedDuration;
        } else {
            // Paused state
            elapsedMs = timer.lastPauseTime - timer.startTime - timer.pausedDuration;
        }

        const hours = msToHours(elapsedMs);

        // Clear timer
        delete activeTimers.value[task.id];
        delete timerDisplays.value[task.id];

        // Stop loop if no more active timers
        if (Object.keys(activeTimers.value).length === 0) {
            stopTimerLoop();
        }

        if (hours > 0) {
            notify(`Logged ${hours.toFixed(2)} hours for "${task.title}"`);
            if (onComplete) onComplete(hours);
        }

        return {
            hours,
            elapsedMs,
            description: description || timer.description || 'Work session completed'
        };
    };

    // Discard timer without logging
    const discardTimer = (taskId) => {
        delete activeTimers.value[taskId];
        delete timerDisplays.value[taskId];
        if (Object.keys(activeTimers.value).length === 0) {
            stopTimerLoop();
        }
        notify('Timer discarded');
    };

    // Get current working task from feature lists
    const getCurrentWorkingTask = (featureLists) => {
        const taskId = Object.keys(activeTimers.value).find(id => activeTimers.value[id].status === 'working');
        if (taskId) {
            // Find task across all features
            for (const list of featureLists) {
                for (const feature of list.features || []) {
                    for (const taskList of feature.taskLists || []) {
                        const task = taskList.tasks?.find(t => t.id === parseInt(taskId));
                        if (task) return { task, feature };
                    }
                }
            }
        }
        return null;
    };

    return {
        activeTimers,
        timerDisplays,
        formatElapsedTime,
        msToHours,
        getTimerStatus,
        getTimerDisplay,
        hasActiveTimer,
        pauseTimer,
        startWorkingOn,
        stopTimerAndLog,
        discardTimer,
        getCurrentWorkingTask,
        startTimerLoop,
        stopTimerLoop
    };
}
