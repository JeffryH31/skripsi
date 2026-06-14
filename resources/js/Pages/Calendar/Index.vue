<script setup>
import { ref, computed } from "vue";
import { Head, router } from "@inertiajs/vue3";
import MainLayout from "@/Layouts/MainLayout.vue";
import TaskDetailPanel from "@/Components/Tasks/TaskDetailPanel.vue";
import { PRIORITY_MAP } from "@/constants/priorities";
import { useSnackbar } from "@/composables/useSnackbar";

const { showSnackbar } = useSnackbar();

const props = defineProps({
    workspace: { type: Object, default: null },
    subtasks: { type: Array, default: () => [] },
    startDate: { type: String, default: '' },
    endDate: { type: String, default: '' },
    viewMode: {
        type: String,
        default: "month",
    },
});

// State
const currentDate = ref(new Date(props.startDate || new Date()));
const viewMode = ref(props.viewMode);
const selectedTask = ref(null);
const showTaskDetail = ref(false);
const searchQuery = ref("");
const filterAssignee = ref(null);
const filterPriority = ref(null);
const filterStatus = ref(null);

// Calendar calculations
const year = computed(() => currentDate.value.getFullYear());
const month = computed(() => currentDate.value.getMonth());
const week = computed(() => {
    const today = new Date(currentDate.value);
    const first = today.getDate() - today.getDay();
    return [
        new Date(today.getFullYear(), today.getMonth(), first),
        new Date(today.getFullYear(), today.getMonth(), first + 1),
        new Date(today.getFullYear(), today.getMonth(), first + 2),
        new Date(today.getFullYear(), today.getMonth(), first + 3),
        new Date(today.getFullYear(), today.getMonth(), first + 4),
        new Date(today.getFullYear(), today.getMonth(), first + 5),
        new Date(today.getFullYear(), today.getMonth(), first + 6),
    ];
});

const monthName = computed(() => {
    return currentDate.value.toLocaleDateString("en-US", {
        month: "long",
        year: "numeric",
    });
});

const weekName = computed(() => {
    const weekStart = week.value[0];
    const weekEnd = week.value[6];
    return `${weekStart.toLocaleDateString("en-US", { month: "short", day: "numeric" })} - ${weekEnd.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" })}`;
});

const daysInMonth = computed(() => {
    return new Date(year.value, month.value + 1, 0).getDate();
});

const firstDayOfMonth = computed(() => {
    return new Date(year.value, month.value, 1).getDay();
});

const calendarDays = computed(() => {
    const days = [];
    const prevMonthDays = new Date(year.value, month.value, 0).getDate();

    for (let i = firstDayOfMonth.value - 1; i >= 0; i--) {
        days.push({
            date: new Date(year.value, month.value - 1, prevMonthDays - i),
            isCurrentMonth: false,
        });
    }

    for (let i = 1; i <= daysInMonth.value; i++) {
        days.push({
            date: new Date(year.value, month.value, i),
            isCurrentMonth: true,
        });
    }

    const remainingDays = 42 - days.length;
    for (let i = 1; i <= remainingDays; i++) {
        days.push({
            date: new Date(year.value, month.value + 1, i),
            isCurrentMonth: false,
        });
    }

    return days;
});

const calendarWeeks = computed(() => {
    const weeks = [];
    for (let i = 0; i < calendarDays.value.length; i += 7) {
        weeks.push(calendarDays.value.slice(i, i + 7));
    }
    return weeks;
});

const weekDays = computed(() => {
    const start = getWeekStart(currentDate.value);
    const days = [];
    for (let i = 0; i < 7; i++) {
        const date = new Date(start);
        date.setDate(start.getDate() + i);
        days.push({
            date,
            isCurrentMonth: date.getMonth() === currentDate.value.getMonth(),
        });
    }
    return days;
});

const toDateOnly = (value) => {
    if (!value) return null;
    const d = new Date(value);
    d.setHours(0, 0, 0, 0);
    return d;
};

const isSameDate = (a, b) => a && b && a.getTime() === b.getTime();

const getWeekStart = (date) => {
    const copy = new Date(date);
    const day = copy.getDay();
    copy.setDate(copy.getDate() - day);
    copy.setHours(0, 0, 0, 0);
    return copy;
};

const daysBetween = (start, end) => {
    const diff = end.getTime() - start.getTime();
    return Math.floor(diff / (1000 * 60 * 60 * 24));
};

const rangesOverlap = (startA, endA, startB, endB) => {
    return startA <= endB && startB <= endA;
};

const getSubtaskRange = (subtask) => {
    const start = toDateOnly(subtask.start_date || subtask.due_date);
    const end = toDateOnly(subtask.due_date || subtask.start_date);
    if (!start || !end) return null;
    return start <= end ? { start, end } : { start: end, end: start };
};

const getSingleDaySubtasksForDate = (date) => {
    const current = toDateOnly(date);
    return filteredSubtasks.value.filter((subtask) => {
        const range = getSubtaskRange(subtask);
        if (!range || !current) return false;
        if (range.start.getTime() !== range.end.getTime()) return false;
        return isSameDate(range.start, current);
    });
};

const getVisibleSubtasksForDate = (date, limit = 1) => {
    return getSingleDaySubtasksForDate(date).slice(0, limit);
};

const getOverflowSubtasksCount = (date, limit = 1) => {
    const total = getSingleDaySubtasksForDate(date).length;
    return total > limit ? total - limit : 0;
};

const getWeekBars = (days) => {
    const weekStartDate = toDateOnly(days[0]?.date);
    const weekEndDate = toDateOnly(days[6]?.date);
    if (!weekStartDate || !weekEndDate) return [];

    const candidates = filteredSubtasks.value
        .map((subtask) => ({ subtask, range: getSubtaskRange(subtask) }))
        .filter(
            ({ range }) =>
                range && range.start.getTime() !== range.end.getTime(),
        )
        .filter(({ range }) =>
            rangesOverlap(range.start, range.end, weekStartDate, weekEndDate),
        )
        .sort((a, b) => {
            if (a.range.start.getTime() !== b.range.start.getTime()) {
                return a.range.start.getTime() - b.range.start.getTime();
            }
            return b.range.end.getTime() - a.range.end.getTime();
        });

    const lanes = [];
    const bars = [];

    candidates.forEach(({ subtask, range }) => {
        const visualStart =
            range.start < weekStartDate ? weekStartDate : range.start;
        const visualEnd = range.end > weekEndDate ? weekEndDate : range.end;

        const startCol = daysBetween(weekStartDate, visualStart) + 1;
        const endCol = daysBetween(weekStartDate, visualEnd) + 1;

        let laneIndex = 0;
        while (
            lanes[laneIndex] &&
            lanes[laneIndex].some(
                (seg) => !(endCol < seg.startCol || startCol > seg.endCol),
            )
        ) {
            laneIndex += 1;
        }

        if (!lanes[laneIndex]) lanes[laneIndex] = [];
        lanes[laneIndex].push({ startCol, endCol });

        bars.push({
            subtask,
            row: laneIndex,
            startCol,
            endCol,
            startsBeforeWeek: range.start < weekStartDate,
            endsAfterWeek: range.end > weekEndDate,
            color: getStatusColor(subtask.status),
        });
    });

    return bars;
};

const getWeekBarsLimited = (days, maxRows = 3) => {
    return getWeekBars(days).filter((bar) => bar.row < maxRows);
};

const getWeekBarsOverflowCount = (days, maxRows = 3) => {
    return getWeekBars(days).filter((bar) => bar.row >= maxRows).length;
};

const getWeekLaneCount = (days, maxRows = 3) => {
    const bars = getWeekBars(days);
    if (!bars.length) return 1;
    const laneCount = Math.max(...bars.map((bar) => bar.row + 1));
    return Math.min(laneCount, maxRows);
};

// Filter subtasks
const filteredSubtasks = computed(() => {
    return props.subtasks.filter((subtask) => {
        const matchesSearch =
            !searchQuery.value ||
            subtask.name
                .toLowerCase()
                .includes(searchQuery.value.toLowerCase()) ||
            subtask.task?.name
                .toLowerCase()
                .includes(searchQuery.value.toLowerCase());

        const matchesAssignee =
            !filterAssignee.value ||
            (subtask.assignees || []).some(
                (a) => a.id === filterAssignee.value,
            );

        const matchesPriority =
            !filterPriority.value ||
            subtask.priority_level === filterPriority.value;

        const matchesStatus =
            !filterStatus.value || subtask.status?.id === filterStatus.value;

        return (
            matchesSearch && matchesAssignee && matchesPriority && matchesStatus
        );
    });
});

// Get unique filter options
const assigneeOptions = computed(() => {
    const assignees = new Map();
    props.subtasks.forEach((st) => {
        (st.assignees || []).forEach((a) => {
            if (a?.id) assignees.set(a.id, a);
        });
    });
    return Array.from(assignees.values());
});

const PRIORITY_LABELS = Object.fromEntries(
    Object.values(PRIORITY_MAP).map((p) => [p.level, p.name])
);
const PRIORITY_COLORS = Object.fromEntries(
    Object.values(PRIORITY_MAP).map((p) => [p.level, p.color])
);

const priorityOptions = computed(() => {
    const levels = new Set();
    props.subtasks.forEach((st) => {
        if (st.priority_level) levels.add(st.priority_level);
    });
    return Array.from(levels)
        .sort()
        .map((level) => ({
            id: level,
            name: PRIORITY_LABELS[level] || `P${level}`,
            color: PRIORITY_COLORS[level] || "#9CA3AF",
        }));
});

const statusOptions = computed(() => {
    const statuses = new Map();
    props.subtasks.forEach((st) => {
        if (st.status) statuses.set(st.status.id, st.status);
    });
    return Array.from(statuses.values());
});

// Get subtasks for a specific date
const getSubtasksForDate = (date) => {
    const dateStr = date.toISOString().split("T")[0];
    return filteredSubtasks.value.filter((subtask) => {
        const dueDate = subtask.due_date
            ? subtask.due_date.split("T")[0]
            : null;
        const startDate = subtask.start_date
            ? subtask.start_date.split("T")[0]
            : null;

        if (startDate && dueDate) {
            return dateStr >= startDate && dateStr <= dueDate;
        }
        return dueDate === dateStr || startDate === dateStr;
    });
};

// Get subtasks spanning multiple days (for visual bars)
const getSpanningSubtasksForDate = (date) => {
    const dateStr = date.toISOString().split("T")[0];
    return filteredSubtasks.value.filter((subtask) => {
        const dueDate = subtask.due_date
            ? subtask.due_date.split("T")[0]
            : null;
        const startDate = subtask.start_date
            ? subtask.start_date.split("T")[0]
            : null;

        if (startDate && dueDate && startDate !== dueDate) {
            return dateStr >= startDate && dateStr <= dueDate;
        }
        return false;
    });
};

// Check if date is today
const isToday = (date) => {
    const today = new Date();
    return date.toDateString() === today.toDateString();
};

// Check if subtask spans multiple days
const isSpanningTask = (subtask) => {
    if (!subtask.start_date || !subtask.due_date) return false;
    const startDate = subtask.start_date.split("T")[0];
    const dueDate = subtask.due_date.split("T")[0];
    return startDate !== dueDate;
};

// Navigation
const previousMonth = () => {
    currentDate.value = new Date(year.value, month.value - 1, 1);
    loadCalendarData();
};

const nextMonth = () => {
    currentDate.value = new Date(year.value, month.value + 1, 1);
    loadCalendarData();
};

const previousWeek = () => {
    const newDate = new Date(currentDate.value);
    newDate.setDate(newDate.getDate() - 7);
    currentDate.value = newDate;
    loadCalendarData();
};

const nextWeek = () => {
    const newDate = new Date(currentDate.value);
    newDate.setDate(newDate.getDate() + 7);
    currentDate.value = newDate;
    loadCalendarData();
};

const goToToday = () => {
    currentDate.value = new Date();
    loadCalendarData();
};

const previousPeriod = () => {
    if (viewMode.value === "week") {
        previousWeek();
        return;
    }
    previousMonth();
};

const nextPeriod = () => {
    if (viewMode.value === "week") {
        nextWeek();
        return;
    }
    nextMonth();
};

const selectDate = (date) => {
    currentDate.value = new Date(date);
    loadCalendarData();
};

const loadCalendarData = () => {
    let start = new Date(year.value, month.value, 1);
    let end = new Date(year.value, month.value + 1, 0);

    if (viewMode.value === "week") {
        start = week.value[0];
        end = week.value[6];
    }

    router.get(
        route("calendar.index", props.workspace.id),
        {
            start_date: start.toISOString().split("T")[0],
            end_date: end.toISOString().split("T")[0],
            view: viewMode.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

// Task actions
const handleTaskClick = (subtask) => {
    selectedTask.value = subtask;
    showTaskDetail.value = true;
};

const openSubtaskFromPanel = (subtask) => {
    selectedTask.value = subtask;
    showTaskDetail.value = true;
};

const handleDragStart = (subtask, event) => {
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("subtask", JSON.stringify(subtask));
};

const handleDrop = (date, event) => {
    event.preventDefault();
    const subtaskData = event.dataTransfer.getData("subtask");
    if (!subtaskData) return;

    const subtask = JSON.parse(subtaskData);
    const task = subtask.task;
    const space = task?.project?.space;
    if (!space || !task) return;

    const newDueDate = date.toISOString().split("T")[0];

    router.patch(
        route("tasks.subtasks.update", [
            space.workspace_id,
            space.id,
            task.project_id || task.project?.id,
            task.id,
            subtask.id,
        ]),
        {
            due_date: newDueDate,
        },
        {
            preserveScroll: true,
            onSuccess: () => loadCalendarData(),
            onError: () => showSnackbar('Failed to update due date', 'error'),
        },
    );
};

const handleDragOver = (event) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = "move";
};

// Summary stats
const totalSubtasks = computed(() => filteredSubtasks.value.length);
const completedSubtasks = computed(
    () => filteredSubtasks.value.filter((s) => s.completed_at).length,
);
const overdueSubtasks = computed(() => {
    const today = new Date().toISOString().split("T")[0];
    return filteredSubtasks.value.filter(
        (s) => !s.completed_at && s.due_date && s.due_date < today,
    ).length;
});

const completionRate = computed(() => {
    if (!totalSubtasks.value) return 0;
    return Math.round((completedSubtasks.value / totalSubtasks.value) * 100);
});

const dueThisWeek = computed(() => {
    const start = getWeekStart(currentDate.value);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);

    return filteredSubtasks.value.filter((subtask) => {
        if (!subtask.due_date) return false;
        const due = toDateOnly(subtask.due_date);
        return due && due >= start && due <= end;
    }).length;
});

const getAgendaItems = computed(() => {
    return filteredSubtasks.value
        .filter((st) => st.due_date)
        .map((st) => ({
            ...st,
            dueDate: new Date(st.due_date),
        }))
        .sort((a, b) => a.dueDate - b.dueDate)
        .slice(0, 10);
});

const getStatusColor = (status) => {
    return status?.color || "#6366F1";
};

const formatScheduleRange = (startDate, dueDate) => {
    const start = startDate ? startDate.split("T")[0] : "N/A";
    const end = dueDate ? dueDate.split("T")[0] : "N/A";
    return `${start} → ${end}`;
};
</script>

<template>
    <MainLayout>
        <Head title="Calendar" />

        <div class="h-full flex gap-1 bg-[#1e1e1e]">
            <!-- Sidebar -->
            <div
                class="w-72 border-r border-[#2d2d30] flex flex-col overflow-y-auto bg-[#1e1e1e]"
            >
                <!-- Mini Calendar Navigation -->
                <div class="p-4 border-b border-[#2d2d30]">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-300">
                            {{ year }}
                        </h3>
                        <div class="flex gap-1">
                            <v-btn
                                size="x-small"
                                icon
                                @click="previousMonth"
                                density="compact"
                            >
                                <v-icon size="16">mdi-chevron-left</v-icon>
                            </v-btn>
                            <v-btn
                                size="x-small"
                                icon
                                @click="nextMonth"
                                density="compact"
                            >
                                <v-icon size="16">mdi-chevron-right</v-icon>
                            </v-btn>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">{{ monthName }}</p>

                    <!-- Mini Calendar -->
                    <div class="mini-calendar">
                        <div class="grid grid-cols-7 gap-1 mb-2">
                            <div
                                v-for="day in [
                                    'S',
                                    'M',
                                    'T',
                                    'W',
                                    'T',
                                    'F',
                                    'S',
                                ]"
                                :key="day"
                                class="text-center text-xs font-semibold text-gray-600"
                            >
                                {{ day }}
                            </div>
                        </div>
                        <div class="grid grid-cols-7 gap-1">
                            <div
                                v-for="day in calendarDays.slice(0, 42)"
                                :key="`mini-${day.date.getTime()}`"
                                class="p-1 text-center text-xs rounded cursor-pointer transition"
                                :class="{
                                    'bg-[#4c9aff] text-white font-semibold':
                                        isToday(day.date),
                                    'text-gray-500 opacity-50':
                                        !day.isCurrentMonth,
                                    'text-gray-300 hover:bg-[#2d2d30]':
                                        day.isCurrentMonth &&
                                        !isToday(day.date),
                                }"
                                @click="selectDate(day.date)"
                            >
                                {{ day.date.getDate() }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="p-4 border-b border-[#2d2d30]">
                    <h3 class="text-sm font-semibold text-gray-300 mb-3">
                        Filters
                    </h3>

                    <!-- Search -->
                    <div class="mb-3">
                        <v-text-field
                            v-model="searchQuery"
                            density="compact"
                            placeholder="Search tasks..."
                            prepend-inner-icon="mdi-magnify"
                            variant="outlined"
                            hide-details
                        />
                    </div>

                    <!-- Assignee Filter -->
                    <div class="mb-3">
                        <label class="text-xs text-gray-500 block mb-1"
                            >Assignee</label
                        >
                        <v-select
                            v-model="filterAssignee"
                            :items="assigneeOptions"
                            item-title="name"
                            item-value="id"
                            variant="outlined"
                            density="compact"
                            placeholder="All assignees"
                            clearable
                            hide-details
                        />
                    </div>

                    <!-- Priority Filter -->
                    <div class="mb-3">
                        <label class="text-xs text-gray-500 block mb-1"
                            >Priority</label
                        >
                        <v-select
                            v-model="filterPriority"
                            :items="priorityOptions"
                            item-title="label"
                            item-value="id"
                            variant="outlined"
                            density="compact"
                            placeholder="All priorities"
                            clearable
                            hide-details
                        />
                    </div>

                    <!-- Status Filter -->
                    <div class="mb-3">
                        <label class="text-xs text-gray-500 block mb-1"
                            >Status</label
                        >
                        <v-select
                            v-model="filterStatus"
                            :items="statusOptions"
                            item-title="name"
                            item-value="id"
                            variant="outlined"
                            density="compact"
                            placeholder="All statuses"
                            clearable
                            hide-details
                        />
                    </div>

                    <!-- Clear Filters -->
                    <v-btn
                        v-if="
                            searchQuery ||
                            filterAssignee ||
                            filterPriority ||
                            filterStatus
                        "
                        size="small"
                        variant="text"
                        density="compact"
                        @click="
                            () => {
                                searchQuery = '';
                                filterAssignee = null;
                                filterPriority = null;
                                filterStatus = null;
                            }
                        "
                        class="w-full"
                    >
                        <v-icon size="16">mdi-close</v-icon>
                        Clear All
                    </v-btn>
                </div>

                <!-- Summary Stats -->
                <div class="p-4 border-b border-[#2d2d30]">
                    <h3 class="text-sm font-semibold text-gray-300 mb-3">
                        Summary
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between text-gray-400">
                            <span>Total Tasks</span>
                            <span class="font-semibold">{{
                                totalSubtasks
                            }}</span>
                        </div>
                        <div
                            v-if="completedSubtasks > 0"
                            class="flex justify-between text-green-400"
                        >
                            <span>Completed</span>
                            <span class="font-semibold">{{
                                completedSubtasks
                            }}</span>
                        </div>
                        <div
                            v-if="overdueSubtasks > 0"
                            class="flex justify-between text-red-400"
                        >
                            <span>Overdue</span>
                            <span class="font-semibold">{{
                                overdueSubtasks
                            }}</span>
                        </div>
                        <div class="pt-2 border-t border-[#2d2d30] mt-2">
                            <div
                                class="flex justify-between text-gray-400 mb-1"
                            >
                                <span>Progress</span>
                                <span class="font-semibold"
                                    >{{
                                        totalSubtasks > 0
                                            ? Math.round(
                                                  (completedSubtasks /
                                                      totalSubtasks) *
                                                      100,
                                              )
                                            : 0
                                    }}%</span
                                >
                            </div>
                            <v-progress-linear
                                :value="
                                    totalSubtasks > 0
                                        ? (completedSubtasks / totalSubtasks) *
                                          100
                                        : 0
                                "
                                height="4"
                            />
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="p-4 flex-1 overflow-y-auto">
                    <h3 class="text-sm font-semibold text-gray-300 mb-3">
                        Upcoming
                    </h3>
                    <div class="space-y-2">
                        <div
                            v-for="item in getAgendaItems"
                            :key="item.id"
                            class="p-2 rounded bg-[#252526] hover:bg-[#2d2d30] cursor-pointer transition text-xs"
                            @click="handleTaskClick(item)"
                        >
                            <div class="font-semibold text-gray-300 truncate">
                                {{ item.name }}
                            </div>
                            <div class="text-gray-500 text-xs">
                                {{
                                    item.dueDate.toLocaleDateString("en-US", {
                                        month: "short",
                                        day: "numeric",
                                    })
                                }}
                            </div>
                        </div>
                        <div
                            v-if="getAgendaItems.length === 0"
                            class="text-center text-gray-500 py-4"
                        >
                            No upcoming tasks
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Calendar Area -->
            <div class="flex-1 flex flex-col bg-[#1e1e1e] overflow-hidden">
                <!-- Top Controls -->
                <div
                    class="flex items-center justify-between p-4 border-b border-[#2d2d30]"
                >
                    <div class="flex items-center gap-4">
                        <h1 class="text-2xl font-bold text-white">
                            <v-icon class="mr-2">mdi-calendar-month</v-icon>
                            {{ workspace.name }}
                        </h1>

                        <!-- Navigation Controls -->
                        <div class="flex items-center gap-2">
                            <v-btn-group
                                density="compact"
                                variant="outlined"
                                divided
                            >
                                <v-btn
                                    @click="previousPeriod"
                                    size="small"
                                    icon
                                >
                                    <v-icon>mdi-chevron-left</v-icon>
                                </v-btn>
                                <v-btn
                                    @click="goToToday"
                                    size="small"
                                    min-width="70"
                                    >Today</v-btn
                                >
                                <v-btn @click="nextPeriod" size="small" icon>
                                    <v-icon>mdi-chevron-right</v-icon>
                                </v-btn>
                            </v-btn-group>
                            <h2 class="text-lg font-semibold min-w-48">
                                {{
                                    viewMode === "month" ? monthName : weekName
                                }}
                            </h2>
                        </div>
                    </div>

                    <!-- View Mode Toggle -->
                    <div class="flex items-center gap-2">
                        <v-btn-group
                            density="compact"
                            variant="outlined"
                            divided
                        >
                            <v-btn
                                :variant="
                                    viewMode === 'month' ? 'flat' : 'outlined'
                                "
                                @click="
                                    viewMode = 'month';
                                    loadCalendarData();
                                "
                                size="small"
                                title="Month view"
                            >
                                <v-icon size="18">mdi-calendar-month</v-icon>
                            </v-btn>
                            <v-btn
                                :variant="
                                    viewMode === 'week' ? 'flat' : 'outlined'
                                "
                                @click="
                                    viewMode = 'week';
                                    loadCalendarData();
                                "
                                size="small"
                                title="Week view"
                            >
                                <v-icon size="18">mdi-calendar-week</v-icon>
                            </v-btn>
                            <v-btn
                                :variant="
                                    viewMode === 'agenda' ? 'flat' : 'outlined'
                                "
                                @click="viewMode = 'agenda'"
                                size="small"
                                title="Agenda view"
                            >
                                <v-icon size="18">mdi-format-list-text</v-icon>
                            </v-btn>
                        </v-btn-group>
                        <v-chip size="small" variant="tonal" class="ml-2">
                            <v-icon start size="14"
                                >mdi-check-circle-outline</v-icon
                            >
                            {{ completionRate }}% complete
                        </v-chip>
                        <v-chip size="small" color="warning" variant="tonal">
                            <v-icon start size="14">mdi-calendar-clock</v-icon>
                            {{ dueThisWeek }} due this week
                        </v-chip>
                    </div>
                </div>

                <!-- Calendar Content -->
                <div class="flex-1 overflow-auto">
                    <!-- Month View -->
                    <div v-if="viewMode === 'month'" class="p-4">
                        <div class="calendar-month-grid">
                            <!-- Day headers -->
                            <div
                                v-for="day in [
                                    'Sunday',
                                    'Monday',
                                    'Tuesday',
                                    'Wednesday',
                                    'Thursday',
                                    'Friday',
                                    'Saturday',
                                ]"
                                :key="day"
                                class="calendar-header"
                            >
                                <span class="hidden sm:inline">{{ day }}</span>
                                <span class="sm:hidden">{{
                                    day.slice(0, 3)
                                }}</span>
                            </div>

                            <div
                                v-for="(weekRow, weekIndex) in calendarWeeks"
                                :key="`week-${weekIndex}`"
                                class="calendar-week-row"
                            >
                                <div
                                    class="week-bars-overlay"
                                    :style="{
                                        gridTemplateRows: `repeat(${getWeekLaneCount(weekRow, 3)}, 22px)`,
                                    }"
                                >
                                    <div
                                        v-for="bar in getWeekBarsLimited(
                                            weekRow,
                                            3,
                                        )"
                                        :key="`bar-${weekIndex}-${bar.subtask.id}-${bar.row}`"
                                        class="calendar-span-bar"
                                        :style="{
                                            gridColumn: `${bar.startCol} / ${bar.endCol + 1}`,
                                            gridRow: bar.row + 1,
                                            backgroundColor: bar.color,
                                        }"
                                        @click="handleTaskClick(bar.subtask)"
                                    >
                                        <v-icon
                                            v-if="bar.startsBeforeWeek"
                                            size="12"
                                            >mdi-chevron-left</v-icon
                                        >
                                        <span class="span-name">{{
                                            bar.subtask.name
                                        }}</span>
                                        <v-avatar
                                            v-if="bar.subtask.assignee"
                                            size="16"
                                            :color="
                                                bar.subtask.assignee
                                                    .avatar_color || 'primary'
                                            "
                                        >
                                            <span class="text-[10px]">{{
                                                bar.subtask.assignee.initials
                                            }}</span>
                                        </v-avatar>
                                        <v-icon
                                            v-if="bar.subtask.completed_at"
                                            size="12"
                                            >mdi-check</v-icon
                                        >
                                        <v-icon
                                            v-if="bar.endsAfterWeek"
                                            size="12"
                                            >mdi-chevron-right</v-icon
                                        >
                                    </div>
                                    <div
                                        v-if="
                                            getWeekBarsOverflowCount(
                                                weekRow,
                                                3,
                                            ) > 0
                                        "
                                        class="week-bar-overflow"
                                    >
                                        +{{
                                            getWeekBarsOverflowCount(weekRow, 3)
                                        }}
                                        more ongoing
                                    </div>
                                </div>

                                <div class="week-days-grid">
                                    <div
                                        v-for="(day, dayIndex) in weekRow"
                                        :key="`day-${weekIndex}-${dayIndex}`"
                                        class="calendar-day"
                                        :class="{
                                            'current-month': day.isCurrentMonth,
                                            'other-month': !day.isCurrentMonth,
                                            today: isToday(day.date),
                                        }"
                                        @dragover="handleDragOver"
                                        @drop="handleDrop(day.date, $event)"
                                    >
                                        <div class="day-header">
                                            <span class="day-number">{{
                                                day.date.getDate()
                                            }}</span>
                                        </div>

                                        <div class="day-content">
                                            <div
                                                v-for="subtask in getVisibleSubtasksForDate(
                                                    day.date,
                                                    1,
                                                )"
                                                :key="subtask.id"
                                                class="calendar-task"
                                                draggable
                                                @dragstart="
                                                    handleDragStart(
                                                        subtask,
                                                        $event,
                                                    )
                                                "
                                                @click.stop="
                                                    handleTaskClick(subtask)
                                                "
                                            >
                                                <v-chip
                                                    size="small"
                                                    :color="
                                                        getStatusColor(
                                                            subtask.status,
                                                        )
                                                    "
                                                    class="w-full text-left"
                                                >
                                                    <div
                                                        class="flex items-center gap-1 w-full overflow-hidden"
                                                    >
                                                        <span
                                                            class="text-xs truncate flex-1"
                                                            >{{
                                                                subtask.name
                                                            }}</span
                                                        >
                                                        <v-icon
                                                            v-if="
                                                                subtask.completed_at
                                                            "
                                                            size="10"
                                                            >mdi-check-circle</v-icon
                                                        >
                                                    </div>
                                                </v-chip>
                                            </div>

                                            <div
                                                v-if="
                                                    getOverflowSubtasksCount(
                                                        day.date,
                                                        1,
                                                    ) > 0
                                                "
                                                class="calendar-overflow"
                                            >
                                                +{{
                                                    getOverflowSubtasksCount(
                                                        day.date,
                                                        1,
                                                    )
                                                }}
                                                more
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Week View -->
                    <div v-else-if="viewMode === 'week'" class="week-view p-4">
                        <div class="overflow-x-auto">
                            <div class="mini-calendar-grid week-grid">
                                <div
                                    v-for="day in [
                                        'Sun',
                                        'Mon',
                                        'Tue',
                                        'Wed',
                                        'Thu',
                                        'Fri',
                                        'Sat',
                                    ]"
                                    :key="`wk-${day}`"
                                    class="calendar-header"
                                >
                                    {{ day }}
                                </div>

                                <div
                                    v-for="(day, index) in weekDays"
                                    :key="`wkday-${index}`"
                                    class="calendar-day"
                                    :class="{
                                        'current-month': day.isCurrentMonth,
                                        'other-month': !day.isCurrentMonth,
                                        today: isToday(day.date),
                                    }"
                                    @dragover="handleDragOver"
                                    @drop="handleDrop(day.date, $event)"
                                >
                                    <div class="day-header">
                                        <span class="day-number">{{
                                            day.date.getDate()
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <div
                                class="week-bars-standalone"
                                :style="{
                                    gridTemplateRows: `repeat(${getWeekLaneCount(weekDays, 3)}, 24px)`,
                                }"
                            >
                                <div
                                    v-for="bar in getWeekBarsLimited(
                                        weekDays,
                                        3,
                                    )"
                                    :key="`wkbar-${bar.subtask.id}-${bar.row}`"
                                    class="calendar-span-bar"
                                    :style="{
                                        gridColumn: `${bar.startCol} / ${bar.endCol + 1}`,
                                        gridRow: bar.row + 1,
                                        backgroundColor: bar.color,
                                    }"
                                    @click="handleTaskClick(bar.subtask)"
                                >
                                    <v-icon
                                        v-if="bar.startsBeforeWeek"
                                        size="12"
                                        >mdi-chevron-left</v-icon
                                    >
                                    <span class="span-name">{{
                                        bar.subtask.name
                                    }}</span>
                                    <v-avatar
                                        v-if="bar.subtask.assignee"
                                        size="16"
                                        :color="
                                            bar.subtask.assignee.avatar_color ||
                                            'primary'
                                        "
                                    >
                                        <span class="text-[10px]">{{
                                            bar.subtask.assignee.initials
                                        }}</span>
                                    </v-avatar>
                                    <v-icon
                                        v-if="bar.subtask.completed_at"
                                        size="12"
                                        >mdi-check</v-icon
                                    >
                                    <v-icon v-if="bar.endsAfterWeek" size="12"
                                        >mdi-chevron-right</v-icon
                                    >
                                </div>
                                <div
                                    v-if="
                                        getWeekBarsOverflowCount(weekDays, 3) >
                                        0
                                    "
                                    class="week-bar-overflow"
                                >
                                    +{{ getWeekBarsOverflowCount(weekDays, 3) }}
                                    more ongoing
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Agenda View -->
                    <div v-else-if="viewMode === 'agenda'" class="p-4">
                        <div
                            v-if="filteredSubtasks.length === 0"
                            class="text-center text-gray-500 py-8"
                        >
                            <v-icon size="48" class="mb-4"
                                >mdi-calendar-blank-outline</v-icon
                            >
                            <p>No tasks found</p>
                        </div>
                        <div v-else class="space-y-2 max-w-4xl">
                            <div
                                v-for="subtask in filteredSubtasks.slice().sort(
                                    (a, b) => {
                                        const aDate = a.due_date
                                            ? new Date(a.due_date)
                                            : new Date(8640000000000000);
                                        const bDate = b.due_date
                                            ? new Date(b.due_date)
                                            : new Date(8640000000000000);
                                        return aDate - bDate;
                                    },
                                )"
                                :key="subtask.id"
                                class="agenda-item"
                            >
                                <div
                                    class="flex gap-3 p-3 rounded hover:bg-[#252526] cursor-pointer transition"
                                    @click="handleTaskClick(subtask)"
                                >
                                    <div class="flex-shrink-0">
                                        <div
                                            class="w-10 h-10 rounded flex items-center justify-center"
                                            :style="{
                                                backgroundColor: getStatusColor(
                                                    subtask.status,
                                                ),
                                            }"
                                        >
                                            <v-icon
                                                v-if="subtask.completed_at"
                                                size="16"
                                                >mdi-check</v-icon
                                            >
                                            <v-icon v-else size="16"
                                                >mdi-circle-outline</v-icon
                                            >
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-white">
                                            {{ subtask.name }}
                                        </div>
                                        <div class="text-sm text-gray-400">
                                            {{ subtask.task?.name }}
                                        </div>
                                        <div
                                            class="flex items-center gap-2 mt-2 flex-wrap"
                                        >
                                            <v-chip
                                                size="x-small"
                                                v-if="subtask.assignee"
                                            >
                                                <v-icon start size="12"
                                                    >mdi-account</v-icon
                                                >
                                                {{ subtask.assignee.name }}
                                            </v-chip>
                                            <v-chip
                                                size="x-small"
                                                v-if="subtask.due_date"
                                            >
                                                <v-icon start size="12"
                                                    >mdi-calendar</v-icon
                                                >
                                                {{
                                                    new Date(
                                                        subtask.due_date,
                                                    ).toLocaleDateString(
                                                        "en-US",
                                                        {
                                                            month: "short",
                                                            day: "numeric",
                                                        },
                                                    )
                                                }}
                                            </v-chip>
                                            <v-chip
                                                size="x-small"
                                                v-if="subtask.time_spent"
                                            >
                                                <v-icon start size="12"
                                                    >mdi-clock</v-icon
                                                >
                                                {{
                                                    Math.floor(
                                                        subtask.time_spent / 60,
                                                    )
                                                }}h
                                            </v-chip>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task Detail Panel -->
        <TaskDetailPanel
            v-model="showTaskDetail"
            :task="selectedTask"
            :workspace="workspace"
            :space="selectedTask?.task?.project?.space"
            :list="selectedTask?.task?.project"
            :parent-task="selectedTask?.task"
            :statuses="workspace.spaces?.flatMap((s) => s.statuses) || []"
            :members="workspace.members || []"
            :labels="workspace.labels || []"
            @open-subtask="openSubtaskFromPanel"
        />
    </MainLayout>
</template>

<style scoped>
.calendar-month-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 1px;
    background-color: #262a33;
    border: 1px solid #313643;
    border-radius: 8px;
    overflow: hidden;
    width: 100%;
}

.calendar-week-row {
    grid-column: 1 / -1;
    background: #1b1f27;
    border-top: 1px solid #2c3340;
    position: relative;
}

.week-days-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 1px;
    background: #2c3340;
    position: relative;
    z-index: 1;
}

.week-bars-overlay {
    position: absolute;
    top: 30px;
    left: 0;
    right: 0;
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 3px;
    padding: 3px 8px 4px;
    pointer-events: none;
    z-index: 2;
    align-items: center;
    min-width: 0;
}

.week-bar-overflow {
    position: absolute;
    top: 2px;
    right: 10px;
    font-size: 11px;
    color: #b4bdca;
    background: rgba(24, 32, 46, 0.92);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 999px;
    padding: 2px 8px;
    z-index: 3;
}

.mini-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 1px;
    background-color: #2c3340;
    border: 1px solid #313643;
    border-radius: 8px;
    overflow: hidden;
}

.mini-calendar-grid.week-grid {
    grid-template-columns: repeat(7, minmax(180px, 1fr));
}

.week-bars-standalone {
    margin-top: 8px;
    border: 1px solid #313643;
    border-radius: 8px;
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 3px;
    padding: 8px;
    background: #161b24;
    position: relative;
}

.calendar-header {
    background-color: #20242d;
    padding: 12px;
    text-align: center;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    color: #8b949e;
}

.calendar-day {
    background-color: #191d25;
    min-height: 102px;
    padding: 34px 8px 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    border: 1px solid #262d3a;
    position: relative;
}

.calendar-day.other-month {
    background-color: #151922;
    opacity: 0.6;
}

.calendar-day.today {
    background-color: #182438;
    border: 2px solid #4c9aff;
}

.day-header {
    position: absolute;
    top: 6px;
    left: 8px;
    right: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 3;
    pointer-events: none;
}

.day-number {
    font-size: 14px;
    font-weight: 600;
    color: #d7dce5;
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 999px;
    padding: 1px 8px;
    line-height: 20px;
}

.calendar-day.today .day-number {
    background-color: #4c9aff;
    border-color: transparent;
    color: white;
}

.day-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
    overflow-y: auto;
    flex: 1;
    position: relative;
    z-index: 1;
}

.calendar-task {
    cursor: pointer;
}

.calendar-overflow {
    font-size: 11px;
    color: #b4bdca;
    padding: 2px 6px;
    border-radius: 6px;
    background: #273245;
    width: fit-content;
}

.calendar-span-bar {
    height: 18px;
    border-radius: 5px;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 7px;
    color: #fff;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
    pointer-events: auto;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
}

.calendar-span-bar:hover {
    filter: brightness(1.04);
}

.span-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
    min-width: 0;
}

.mini-calendar {
    padding: 8px;
    background-color: #252526;
    border-radius: 4px;
}
</style>
