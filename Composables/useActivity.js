/**
 * Activity Composable
 * 
 * Handles activity logging and display
 */
import { ref, computed } from 'vue';

export function useActivity() {
    const activityLog = ref([]);
    const activityFilterUser = ref(null);

    const addActivityLog = (type, data, userId = 1) => {
        const newId = activityLog.value.length > 0 
            ? Math.max(...activityLog.value.map(a => a.id)) + 1 
            : 1;
        activityLog.value.unshift({
            id: newId,
            type: type,
            userId: userId,
            timestamp: new Date().toISOString(),
            ...data
        });
    };

    const getActivityIcon = (type) => {
        const icons = {
            'time_logged': 'mdi-clock-plus-outline',
            'task_completed': 'mdi-checkbox-marked-circle-outline',
            'task_moved': 'mdi-arrow-right-bold',
            'task_assigned': 'mdi-account-plus',
            'task_created': 'mdi-plus-circle-outline',
            'estimation_updated': 'mdi-timer-edit-outline',
            'comment_added': 'mdi-comment-plus-outline'
        };
        return icons[type] || 'mdi-circle-outline';
    };

    const getActivityColor = (type) => {
        const colors = {
            'time_logged': 'primary',
            'task_completed': 'success',
            'task_moved': 'info',
            'task_assigned': 'warning',
            'task_created': 'secondary',
            'estimation_updated': 'purple',
            'comment_added': 'grey'
        };
        return colors[type] || 'grey';
    };

    const getMember = (memberId, teamMembers = []) => {
        return teamMembers.find(m => m.id === memberId);
    };

    const getActivityDescription = (activity, teamMembers = []) => {
        const user = getMember(activity.userId, teamMembers);
        const userName = user?.name || 'Unknown';

        switch (activity.type) {
            case 'time_logged':
                return `${userName} logged ${activity.hours}h on "${activity.taskTitle}"`;
            case 'task_completed':
                return `${userName} completed "${activity.taskTitle}"`;
            case 'task_moved':
                return `${userName} moved "${activity.taskTitle}" from ${activity.fromList} to ${activity.toList}`;
            case 'task_assigned':
                const assignee = getMember(activity.assignedTo, teamMembers);
                return `${userName} assigned "${activity.taskTitle}" to ${assignee?.name || 'Unknown'}`;
            case 'task_created':
                return `${userName} created "${activity.taskTitle}"`;
            case 'estimation_updated':
                return `${userName} updated estimation for "${activity.taskTitle}" (${activity.oldEstimate}h → ${activity.newEstimate}h)`;
            default:
                return `${userName} performed an action`;
        }
    };

    const formatActivityTime = (timestamp) => {
        const date = new Date(timestamp);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    };

    const filteredActivityLog = computed(() => {
        if (!activityFilterUser.value) return activityLog.value;
        return activityLog.value.filter(a => a.userId === activityFilterUser.value);
    });

    // Get activities for current user (for employee dashboard)
    const getMyActivities = computed(() => {
        return activityLog.value.filter(a => a.userId === 1).slice(0, 10);
    });

    // Get time logged by each team member
    const getTeamTimeStats = computed(() => {
        // This should be passed team members when called
        return {};
    });

    const calculateTeamTimeStats = (teamMembers = []) => {
        const stats = {};
        teamMembers.forEach(member => {
            stats[member.id] = {
                member: member,
                today: 0,
                week: 0,
                tasks: 0
            };
        });

        const now = new Date();
        const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const weekStart = new Date(now);
        weekStart.setDate(now.getDate() - 7);

        activityLog.value.forEach(activity => {
            if (stats[activity.userId]) {
                const actDate = new Date(activity.timestamp);
                if (activity.type === 'time_logged') {
                    if (actDate >= todayStart) {
                        stats[activity.userId].today += activity.hours || 0;
                    }
                    if (actDate >= weekStart) {
                        stats[activity.userId].week += activity.hours || 0;
                    }
                }
                if (activity.type === 'task_completed') {
                    stats[activity.userId].tasks++;
                }
            }
        });

        return stats;
    };

    const setActivityLog = (activities) => {
        activityLog.value = activities || [];
    };

    return {
        activityLog,
        activityFilterUser,
        addActivityLog,
        getActivityIcon,
        getActivityColor,
        getActivityDescription,
        formatActivityTime,
        filteredActivityLog,
        getMyActivities,
        getTeamTimeStats,
        calculateTeamTimeStats,
        setActivityLog
    };
}
