/**
 * Filters Composable
 * 
 * Handles search and filter logic for features
 */
import { ref, computed } from 'vue';

export function useFilters() {
    // Search
    const searchQuery = ref('');
    const isMobileSearchOpen = ref(false);

    // Filters
    const isFilterMenuOpen = ref(false);
    const activeFilters = ref({
        labels: [],
        members: [],
        priority: null,
        dueDate: null // 'overdue', 'due-soon', 'no-date'
    });

    const hasActiveFilters = computed(() => {
        return activeFilters.value.labels.length > 0 ||
            activeFilters.value.members.length > 0 ||
            activeFilters.value.priority !== null ||
            activeFilters.value.dueDate !== null;
    });

    const clearFilters = () => {
        activeFilters.value = {
            labels: [],
            members: [],
            priority: null,
            dueDate: null
        };
    };

    // Date helpers
    const isOverdue = (dueDate) => {
        if (!dueDate) return false;
        return new Date(dueDate) < new Date();
    };

    const isDueSoon = (dueDate) => {
        if (!dueDate) return false;
        const due = new Date(dueDate);
        const now = new Date();
        const diff = due - now;
        return diff > 0 && diff < 3 * 24 * 60 * 60 * 1000; // 3 days
    };

    const featureMatchesFilter = (feature) => {
        // Check search query
        if (searchQuery.value.trim()) {
            const query = searchQuery.value.toLowerCase();
            const matchesSearch = feature.title.toLowerCase().includes(query) ||
                feature.description?.toLowerCase().includes(query) ||
                feature.labels.some(l => l.name.toLowerCase().includes(query)) ||
                feature.taskLists.some(tl => tl.tasks.some(t => t.title.toLowerCase().includes(query)));
            if (!matchesSearch) return false;
        }

        // Check label filter
        if (activeFilters.value.labels.length > 0) {
            if (!feature.labels.some(l => activeFilters.value.labels.includes(l.id))) {
                return false;
            }
        }

        // Check member filter
        if (activeFilters.value.members.length > 0) {
            if (!feature.assignees.some(a => activeFilters.value.members.includes(a))) {
                return false;
            }
        }

        // Check priority filter
        if (activeFilters.value.priority !== null) {
            if (feature.priority !== activeFilters.value.priority) {
                return false;
            }
        }

        // Check due date filter
        if (activeFilters.value.dueDate !== null) {
            if (activeFilters.value.dueDate === 'overdue' && !isOverdue(feature.dueDate)) return false;
            if (activeFilters.value.dueDate === 'due-soon' && !isDueSoon(feature.dueDate)) return false;
            if (activeFilters.value.dueDate === 'no-date' && feature.dueDate) return false;
        }

        return true;
    };

    const getVisibleFeaturesCount = (list) => {
        if (!hasActiveFilters.value && !searchQuery.value.trim()) {
            return list.features.length;
        }
        return list.features.filter(f => featureMatchesFilter(f)).length;
    };

    const toggleFilterLabel = (labelId) => {
        const index = activeFilters.value.labels.indexOf(labelId);
        if (index > -1) {
            activeFilters.value.labels.splice(index, 1);
        } else {
            activeFilters.value.labels.push(labelId);
        }
    };

    const toggleFilterMember = (memberId) => {
        const index = activeFilters.value.members.indexOf(memberId);
        if (index > -1) {
            activeFilters.value.members.splice(index, 1);
        } else {
            activeFilters.value.members.push(memberId);
        }
    };

    // Filter feature lists based on search and filters
    const filterFeatureLists = (lists) => {
        let result = lists;

        // Apply search query
        if (searchQuery.value.trim()) {
            const query = searchQuery.value.toLowerCase();
            result = result.map(list => ({
                ...list,
                features: list.features.filter(f =>
                    f.title.toLowerCase().includes(query) ||
                    f.description?.toLowerCase().includes(query) ||
                    f.labels.some(l => l.name.toLowerCase().includes(query)) ||
                    f.taskLists.some(tl => tl.tasks.some(t => t.title.toLowerCase().includes(query)))
                )
            }));
        }

        // Apply filters
        if (hasActiveFilters.value) {
            result = result.map(list => ({
                ...list,
                features: list.features.filter(f => featureMatchesFilter(f))
            }));
        }

        return result;
    };

    return {
        searchQuery,
        isMobileSearchOpen,
        isFilterMenuOpen,
        activeFilters,
        hasActiveFilters,
        clearFilters,
        isOverdue,
        isDueSoon,
        featureMatchesFilter,
        getVisibleFeaturesCount,
        toggleFilterLabel,
        toggleFilterMember,
        filterFeatureLists
    };
}
