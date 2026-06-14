<script setup>
import { computed, toRef } from 'vue';
import { useCalendarGrid } from '@/composables/useCalendarGrid';

const props = defineProps({
    items: { type: Array, required: true },
    statuses: { type: Array, required: true },
});

const emit = defineEmits(['item-open']);

const itemsRef = toRef(props, 'items');

const getItemStatus = (item) => props.statuses.find((s) => s.id === item.status_id);
const getItemColor = (item) => getItemStatus(item)?.color || '#6366F1';

const {
    subView,
    calendarTitle,
    calendarWeeks,
    weekDays,
    weekBarsByIndex,
    standaloneWeekBars,
    getVisibleItemsForDate,
    getOverflowItemsCount,
    toDateOnly,
    isDateToday,
    getWeekStart,
    previousPeriod,
    nextPeriod,
    goToToday,
    currentDate,
} = useCalendarGrid(itemsRef, getItemColor);

// Stats
const completionRate = computed(() => {
    const total = props.items.length;
    if (!total) return 0;
    const completed = props.items.filter((i) => i.completed_at).length;
    return Math.round((completed / total) * 100);
});

const dueThisWeek = computed(() => {
    const start = getWeekStart(currentDate.value);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);
    return props.items.filter((item) => {
        const due = toDateOnly(item.due_date);
        return due && due >= start && due <= end;
    }).length;
});

const onItemOpen = (item) => emit('item-open', item);
</script>

<template>
    <div class="calendar-view">
        <div class="calendar-container">
            <!-- Header -->
            <div class="calendar-header">
                <v-btn-group density="compact" variant="outlined" divided>
                    <v-btn @click="previousPeriod">
                        <v-icon>mdi-chevron-left</v-icon>
                    </v-btn>
                    <v-btn min-width="80" @click="goToToday">Today</v-btn>
                    <v-btn @click="nextPeriod">
                        <v-icon>mdi-chevron-right</v-icon>
                    </v-btn>
                </v-btn-group>

                <h2 class="text-xl font-semibold">{{ calendarTitle }}</h2>

                <v-btn-toggle v-model="subView" mandatory density="compact" variant="outlined">
                    <v-btn value="month" size="small">
                        <v-icon size="14" class="mr-1">mdi-calendar-month</v-icon>
                        Month
                    </v-btn>
                    <v-btn value="week" size="small">
                        <v-icon size="14" class="mr-1">mdi-calendar-week</v-icon>
                        Week
                    </v-btn>
                </v-btn-toggle>

                <div class="ml-auto flex items-center gap-2">
                    <v-chip size="small" variant="tonal">
                        <v-icon start size="14">mdi-check-circle-outline</v-icon>
                        {{ completionRate }}% complete
                    </v-chip>
                    <v-chip size="small" color="warning" variant="tonal">
                        <v-icon start size="14">mdi-calendar-clock</v-icon>
                        {{ dueThisWeek }} due this week
                    </v-chip>
                </div>
            </div>

            <!-- Month grid -->
            <div v-if="subView === 'month'" class="calendar-month-grid">
                <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day"
                    class="calendar-day-header">
                    {{ day }}
                </div>

                <div v-for="(week, weekIndex) in calendarWeeks" :key="`week-${weekIndex}`" class="calendar-week-row">
                    <div class="week-bars-overlay" :style="{
                        gridTemplateRows: `repeat(${Math.max(weekBarsByIndex[weekIndex].length, 1)}, 22px)`
                    }">
                        <div v-for="bar in weekBarsByIndex[weekIndex]"
                            :key="`bar-${weekIndex}-${bar.item.id}-${bar.row}`" class="calendar-span-bar" :style="{
                                gridColumn: `${bar.startCol} / ${bar.endCol + 1}`,
                                gridRow: bar.row + 1,
                                backgroundColor: bar.color,
                            }" @click="onItemOpen(bar.item)">
                            <v-icon v-if="bar.startsBeforeWeek" size="12">mdi-chevron-left</v-icon>
                            <span class="span-name">{{ bar.item.name }}</span>
                            <v-avatar v-if="bar.item.assignees?.[0]" size="16"
                                :color="bar.item.assignees[0].avatar_color || 'primary'">
                                <span class="text-[10px]">{{ bar.item.assignees[0].initials }}</span>
                            </v-avatar>
                            <v-icon v-if="bar.item.completed_at" size="12">mdi-check</v-icon>
                            <v-icon v-if="bar.endsAfterWeek" size="12">mdi-chevron-right</v-icon>
                        </div>
                    </div>

                    <div class="week-days-grid">
                        <div v-for="(day, dayIndex) in week" :key="`day-${weekIndex}-${dayIndex}`" class="calendar-cell"
                            :class="{
                                'current-month': day.isCurrentMonth,
                                'other-month': !day.isCurrentMonth,
                                today: isDateToday(day.date),
                            }">
                            <div class="cell-header">
                                <span class="day-num">{{ day.date.getDate() }}</span>
                            </div>
                            <div class="cell-tasks">
                                <div v-for="item in getVisibleItemsForDate(day.date, 1)"
                                    :key="`single-${weekIndex}-${dayIndex}-${item.id}`" class="calendar-item"
                                    @click="onItemOpen(item)">
                                    <div class="item-dot"
                                        :style="{ backgroundColor: getItemStatus(item)?.color || '#6366F1' }" />
                                    <span class="item-name">{{ item.name }}</span>
                                    <v-icon v-if="item.completed_at" size="12" color="success">mdi-check-circle</v-icon>
                                </div>
                                <div v-if="getOverflowItemsCount(day.date, 1) > 0" class="calendar-overflow">
                                    +{{ getOverflowItemsCount(day.date, 1) }} more
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Week grid -->
            <div v-else class="calendar-week-only">
                <div class="mini-calendar-grid week-grid">
                    <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="`wk-${day}`"
                        class="calendar-day-header">
                        {{ day }}
                    </div>
                    <div v-for="(day, index) in weekDays" :key="`wkday-${index}`" class="calendar-cell" :class="{
                        'current-month': day.isCurrentMonth,
                        'other-month': !day.isCurrentMonth,
                        today: isDateToday(day.date),
                    }">
                        <div class="cell-header">
                            <span class="day-num">{{ day.date.getDate() }}</span>
                        </div>
                    </div>
                </div>

                <div class="week-bars-grid week-bars-standalone"
                    :style="{ gridTemplateRows: `repeat(${Math.max(standaloneWeekBars.length, 1)}, 24px)` }">
                    <div v-for="bar in standaloneWeekBars" :key="`wkbar-${bar.item.id}-${bar.row}`"
                        class="calendar-span-bar" :style="{
                            gridColumn: `${bar.startCol} / ${bar.endCol + 1}`,
                            gridRow: bar.row + 1,
                            backgroundColor: bar.color,
                        }" @click="onItemOpen(bar.item)">
                        <v-icon v-if="bar.startsBeforeWeek" size="12">mdi-chevron-left</v-icon>
                        <span class="span-name">{{ bar.item.name }}</span>
                        <v-avatar v-if="bar.item.assignees?.[0]" size="16"
                            :color="bar.item.assignees[0].avatar_color || 'primary'">
                            <span class="text-[10px]">{{ bar.item.assignees[0].initials }}</span>
                        </v-avatar>
                        <v-icon v-if="bar.item.completed_at" size="12">mdi-check</v-icon>
                        <v-icon v-if="bar.endsAfterWeek" size="12">mdi-chevron-right</v-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.calendar-view {
    padding: 24px;
    height: 100%;
    overflow: auto;
}

.calendar-container {
    max-width: 1400px;
    margin: 0 auto;
}

.calendar-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.mini-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background-color: #2d2d30;
    border: 1px solid #2d2d30;
    border-radius: 8px;
    overflow: hidden;
}

.mini-calendar-grid.week-grid {
    grid-template-columns: repeat(7, minmax(180px, 1fr));
    overflow-x: auto;
}

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

.week-bars-standalone {
    margin-top: 8px;
    border: 1px solid #2d2d30;
    border-radius: 8px;
}

.calendar-day-header {
    background-color: #20242d;
    padding: 12px;
    text-align: center;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    color: #8b949e;
}

.calendar-cell {
    background-color: #191d25;
    min-height: 102px;
    padding: 34px 8px 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    border: 1px solid #262d3a;
    position: relative;
}

.calendar-cell.other-month {
    background-color: #181818;
    opacity: 0.5;
}

.calendar-cell.today {
    background-color: #182438;
    border: 2px solid #4c9aff;
}

.cell-header {
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

.day-num {
    font-size: 14px;
    font-weight: 600;
    color: #d7dce5;
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 999px;
    padding: 1px 8px;
    line-height: 20px;
}

.calendar-cell.today .day-num {
    background-color: #4c9aff;
    border-color: transparent;
    color: white;
}

.cell-tasks {
    display: flex;
    flex-direction: column;
    gap: 2px;
    overflow-y: auto;
    flex: 1;
    position: relative;
    z-index: 1;
}

.calendar-item {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 3px 6px;
    background-color: #242b37;
    border: 1px solid transparent;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
    font-size: 11px;
}

.calendar-item:hover {
    background-color: #2e3747;
    border-color: #3b82f6;
}

.item-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.item-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #c5c5c5;
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
    position: relative;
    z-index: 2;
    line-height: 1;
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
</style>
