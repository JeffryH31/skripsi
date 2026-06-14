<script setup>
/**
 * BoardHeader Component
 * 
 * Header section for the active board with title, members, and filters
 */
import { useDisplay } from 'vuetify';

const { mobile, smAndDown } = useDisplay();

const props = defineProps({
    activeBoard: {
        type: Object,
        default: null
    },
    boardMembers: {
        type: Array,
        default: () => []
    },
    availableLabels: {
        type: Array,
        default: () => []
    },
    teamMembers: {
        type: Array,
        default: () => []
    },
    activeFilters: {
        type: Object,
        default: () => ({ labels: [], members: [], priority: null, dueDate: null })
    },
    hasActiveFilters: {
        type: Boolean,
        default: false
    },
    priorityOptions: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits([
    'open-members-modal',
    'toggle-filter-label',
    'toggle-filter-member',
    'set-priority-filter',
    'set-due-date-filter',
    'clear-filters'
]);
</script>

<template>
    <v-sheet color="surface" class="d-flex align-center flex-wrap pa-2 pa-sm-3 board-header">
        <h1 :class="mobile ? 'text-h6' : 'text-h5'" class="font-weight-bold text-white mr-2 mr-sm-3">
            {{ activeBoard?.name || 'No Board Selected' }}
        </h1>
        <v-btn icon variant="text" size="small" class="mr-2">
            <v-icon color="grey-lighten-1">mdi-star-outline</v-icon>
        </v-btn>

        <!-- Members -->
        <div v-if="!smAndDown && boardMembers.length > 0" class="d-flex align-center">
            <v-divider vertical class="mx-2 border-opacity-25" style="height: 24px;" />
            <div class="d-flex align-center mx-2">
                <v-tooltip 
                    v-for="member in boardMembers.slice(0, 4)" 
                    :key="member.id"
                    location="bottom"
                >
                    <template #activator="{ props: tooltipProps }">
                        <v-avatar 
                            v-bind="tooltipProps" 
                            :size="mobile ? 28 : 32" 
                            :color="member.color"
                            class="member-avatar cursor-pointer"
                            @click="$emit('open-members-modal')"
                        >
                            <span class="text-caption font-weight-bold">{{ member.avatar }}</span>
                        </v-avatar>
                    </template>
                    {{ member.name }}
                </v-tooltip>
                <v-avatar 
                    v-if="boardMembers.length > 4" 
                    :size="mobile ? 28 : 32"
                    color="grey-darken-1" 
                    class="member-avatar cursor-pointer"
                    @click="$emit('open-members-modal')"
                >
                    <span class="text-caption">+{{ boardMembers.length - 4 }}</span>
                </v-avatar>
                <v-btn 
                    icon 
                    variant="text" 
                    size="small" 
                    class="ml-1"
                    @click="$emit('open-members-modal')"
                >
                    <v-icon color="grey-lighten-1" size="20">mdi-plus</v-icon>
                </v-btn>
            </div>
        </div>

        <v-spacer />

        <!-- Board Actions -->
        <template v-if="!smAndDown">
            <!-- Filter Button -->
            <v-menu :close-on-content-click="false">
                <template #activator="{ props: menuProps }">
                    <v-btn 
                        v-bind="menuProps" 
                        variant="tonal" 
                        size="small" 
                        class="text-none mr-2"
                        :color="hasActiveFilters ? 'primary' : undefined"
                    >
                        <v-badge 
                            v-if="hasActiveFilters" 
                            dot 
                            color="error" 
                            offset-x="-8" 
                            offset-y="-8"
                        >
                            <v-icon start size="18">mdi-filter-variant</v-icon>
                        </v-badge>
                        <v-icon v-else start size="18">mdi-filter-variant</v-icon>
                        Filter
                    </v-btn>
                </template>
                <!-- Filter Menu Content -->
                <v-card color="surface" min-width="300">
                    <v-card-title class="d-flex align-center pa-3">
                        <v-icon start size="20">mdi-filter-variant</v-icon>
                        Filters
                        <v-spacer />
                        <v-btn 
                            v-if="hasActiveFilters" 
                            variant="text" 
                            size="small" 
                            color="error"
                            class="text-none" 
                            @click="$emit('clear-filters')"
                        >
                            Clear all
                        </v-btn>
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <!-- Labels Filter -->
                        <v-list-subheader>Labels</v-list-subheader>
                        <div class="px-4 pb-2 d-flex flex-wrap ga-1">
                            <v-chip 
                                v-for="label in availableLabels" 
                                :key="label.id" 
                                size="small"
                                :variant="activeFilters.labels.includes(label.id) ? 'flat' : 'outlined'"
                                :style="{
                                    backgroundColor: activeFilters.labels.includes(label.id) ? label.color : 'transparent',
                                    borderColor: label.color
                                }" 
                                @click="$emit('toggle-filter-label', label.id)"
                            >
                                {{ label.name }}
                            </v-chip>
                        </div>

                        <!-- Members Filter -->
                        <v-list-subheader>Members</v-list-subheader>
                        <div class="px-4 pb-2 d-flex flex-wrap ga-1">
                            <v-chip 
                                v-for="member in teamMembers" 
                                :key="member.id" 
                                size="small"
                                :variant="activeFilters.members.includes(member.id) ? 'flat' : 'outlined'"
                                :color="activeFilters.members.includes(member.id) ? 'primary' : undefined"
                                @click="$emit('toggle-filter-member', member.id)"
                            >
                                {{ member.name.split(' ')[0] }}
                            </v-chip>
                        </div>

                        <!-- Priority Filter -->
                        <v-list-subheader>Priority</v-list-subheader>
                        <div class="px-4 pb-2 d-flex flex-wrap ga-1">
                            <v-chip 
                                v-for="priority in priorityOptions" 
                                :key="priority.value"
                                size="small"
                                :variant="activeFilters.priority === priority.value ? 'flat' : 'outlined'"
                                :color="activeFilters.priority === priority.value ? priority.color : undefined"
                                @click="$emit('set-priority-filter', activeFilters.priority === priority.value ? null : priority.value)"
                            >
                                <v-icon start size="14">mdi-flag</v-icon>
                                {{ priority.title }}
                            </v-chip>
                        </div>

                        <!-- Due Date Filter -->
                        <v-list-subheader>Due Date</v-list-subheader>
                        <div class="px-4 pb-3 d-flex flex-wrap ga-1">
                            <v-chip 
                                size="small"
                                :variant="activeFilters.dueDate === 'overdue' ? 'flat' : 'outlined'"
                                :color="activeFilters.dueDate === 'overdue' ? 'error' : undefined"
                                @click="$emit('set-due-date-filter', activeFilters.dueDate === 'overdue' ? null : 'overdue')"
                            >
                                Overdue
                            </v-chip>
                            <v-chip 
                                size="small"
                                :variant="activeFilters.dueDate === 'due-soon' ? 'flat' : 'outlined'"
                                :color="activeFilters.dueDate === 'due-soon' ? 'warning' : undefined"
                                @click="$emit('set-due-date-filter', activeFilters.dueDate === 'due-soon' ? null : 'due-soon')"
                            >
                                Due Soon
                            </v-chip>
                            <v-chip 
                                size="small"
                                :variant="activeFilters.dueDate === 'no-date' ? 'flat' : 'outlined'"
                                @click="$emit('set-due-date-filter', activeFilters.dueDate === 'no-date' ? null : 'no-date')"
                            >
                                No Date
                            </v-chip>
                        </div>
                    </v-card-text>
                </v-card>
            </v-menu>
            <v-btn variant="tonal" size="small" class="text-none">
                <v-icon start size="18">mdi-dots-horizontal</v-icon>
                Menu
            </v-btn>
        </template>

        <!-- Mobile Actions Menu -->
        <template v-if="smAndDown">
            <v-menu :close-on-content-click="false">
                <template #activator="{ props: menuProps }">
                    <v-btn 
                        v-bind="menuProps" 
                        icon 
                        variant="tonal" 
                        size="small"
                        :color="hasActiveFilters ? 'primary' : undefined"
                    >
                        <v-badge 
                            v-if="hasActiveFilters" 
                            dot 
                            color="error"
                        >
                            <v-icon size="20">mdi-tune</v-icon>
                        </v-badge>
                        <v-icon v-else size="20">mdi-tune</v-icon>
                    </v-btn>
                </template>
                <!-- Mobile Filter/Actions Menu Content -->
                <v-card color="surface" min-width="280">
                    <v-card-title class="d-flex align-center pa-3">
                        <v-icon start size="20">mdi-tune</v-icon>
                        Filters & Actions
                        <v-spacer />
                        <v-btn 
                            v-if="hasActiveFilters" 
                            variant="text" 
                            size="small" 
                            color="error"
                            class="text-none" 
                            @click="$emit('clear-filters')"
                        >
                            Clear
                        </v-btn>
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <!-- Members Action -->
                        <v-list-item @click="$emit('open-members-modal')">
                            <template #prepend>
                                <v-icon size="20">mdi-account-group</v-icon>
                            </template>
                            <v-list-item-title>Manage Members</v-list-item-title>
                            <template #append>
                                <v-chip size="x-small">{{ boardMembers.length }}</v-chip>
                            </template>
                        </v-list-item>
                        <v-divider />

                        <!-- Labels Filter -->
                        <v-list-subheader>Labels</v-list-subheader>
                        <div class="px-4 pb-2 d-flex flex-wrap ga-1">
                            <v-chip 
                                v-for="label in availableLabels" 
                                :key="label.id" 
                                size="small"
                                :variant="activeFilters.labels.includes(label.id) ? 'flat' : 'outlined'"
                                :style="{
                                    backgroundColor: activeFilters.labels.includes(label.id) ? label.color : 'transparent',
                                    borderColor: label.color
                                }" 
                                @click="$emit('toggle-filter-label', label.id)"
                            >
                                {{ label.name }}
                            </v-chip>
                        </div>

                        <!-- Members Filter -->
                        <v-list-subheader>Filter by Member</v-list-subheader>
                        <div class="px-4 pb-2 d-flex flex-wrap ga-1">
                            <v-chip 
                                v-for="member in teamMembers" 
                                :key="member.id" 
                                size="small"
                                :variant="activeFilters.members.includes(member.id) ? 'flat' : 'outlined'"
                                :color="activeFilters.members.includes(member.id) ? 'primary' : undefined"
                                @click="$emit('toggle-filter-member', member.id)"
                            >
                                {{ member.name.split(' ')[0] }}
                            </v-chip>
                        </div>

                        <!-- Priority Filter -->
                        <v-list-subheader>Priority</v-list-subheader>
                        <div class="px-4 pb-2 d-flex flex-wrap ga-1">
                            <v-chip 
                                v-for="priority in priorityOptions" 
                                :key="priority.value"
                                size="small"
                                :variant="activeFilters.priority === priority.value ? 'flat' : 'outlined'"
                                :color="activeFilters.priority === priority.value ? priority.color : undefined"
                                @click="$emit('set-priority-filter', activeFilters.priority === priority.value ? null : priority.value)"
                            >
                                <v-icon start size="14">mdi-flag</v-icon>
                                {{ priority.title }}
                            </v-chip>
                        </div>

                        <!-- Due Date Filter -->
                        <v-list-subheader>Due Date</v-list-subheader>
                        <div class="px-4 pb-3 d-flex flex-wrap ga-1">
                            <v-chip 
                                size="small"
                                :variant="activeFilters.dueDate === 'overdue' ? 'flat' : 'outlined'"
                                :color="activeFilters.dueDate === 'overdue' ? 'error' : undefined"
                                @click="$emit('set-due-date-filter', activeFilters.dueDate === 'overdue' ? null : 'overdue')"
                            >
                                Overdue
                            </v-chip>
                            <v-chip 
                                size="small"
                                :variant="activeFilters.dueDate === 'due-soon' ? 'flat' : 'outlined'"
                                :color="activeFilters.dueDate === 'due-soon' ? 'warning' : undefined"
                                @click="$emit('set-due-date-filter', activeFilters.dueDate === 'due-soon' ? null : 'due-soon')"
                            >
                                Due Soon
                            </v-chip>
                            <v-chip 
                                size="small"
                                :variant="activeFilters.dueDate === 'no-date' ? 'flat' : 'outlined'"
                                @click="$emit('set-due-date-filter', activeFilters.dueDate === 'no-date' ? null : 'no-date')"
                            >
                                No Date
                            </v-chip>
                        </div>
                    </v-card-text>
                </v-card>
            </v-menu>
        </template>
    </v-sheet>
</template>

<style scoped>
.board-header {
    flex-shrink: 0;
}

.member-avatar {
    margin-left: -8px;
    border: 2px solid rgb(var(--v-theme-surface));
}

.member-avatar:first-child {
    margin-left: 0;
}
</style>
