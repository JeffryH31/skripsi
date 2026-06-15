import { computed, ref } from 'vue';

/**
 * Reusable calendar grid logic.
 *
 * Used by:
 *   - Components/Lists/CalendarView.vue
 *   - Pages/Calendar/Index.vue
 *
 * Caller passes a reactive ref to the items array and a getter that
 * extracts the status color (since shape varies between subtasks/tasks).
 */
export function useCalendarGrid(itemsRef, getColor = () => '#6366F1') {
    const currentDate = ref(new Date());
    const subView = ref('month'); // 'month' | 'week'

    // Date helpers
    const toDateOnly = (value) => {
        if (!value) return null;
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return null;
        d.setHours(0, 0, 0, 0);
        return d;
    };

    const isSameDate = (a, b) => a && b && a.getTime() === b.getTime();

    const isDateToday = (date) => {
        const today = new Date();
        return date.toDateString() === today.toDateString();
    };

    const getWeekStart = (date) => {
        const copy = new Date(date);
        copy.setDate(copy.getDate() - copy.getDay());
        copy.setHours(0, 0, 0, 0);
        return copy;
    };

    const daysBetween = (start, end) =>
        Math.floor((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24));

    const rangesOverlap = (startA, endA, startB, endB) =>
        startA <= endB && startB <= endA;

    const getItemRange = (item) => {
        const start = toDateOnly(item.start_date || item.due_date);
        const end = toDateOnly(item.due_date || item.start_date);
        if (!start || !end) return null;
        return start <= end ? { start, end } : { start: end, end: start };
    };

    // Header labels
    const calendarYear = computed(() => currentDate.value.getFullYear());
    const calendarMonth = computed(() => currentDate.value.getMonth());

    const monthLabel = computed(() =>
        currentDate.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
    );

    const weekLabel = computed(() => {
        const start = getWeekStart(currentDate.value);
        const end = new Date(start);
        end.setDate(start.getDate() + 6);
        const fmt = { month: 'short', day: 'numeric' };
        return `${start.toLocaleDateString('en-US', fmt)} - ${end.toLocaleDateString('en-US', { ...fmt, year: 'numeric' })}`;
    });

    const calendarTitle = computed(() =>
        subView.value === 'month' ? monthLabel.value : weekLabel.value
    );

    // Grids
    const calendarDays = computed(() => {
        const days = [];
        const firstDay = new Date(calendarYear.value, calendarMonth.value, 1).getDay();
        const daysInMonth = new Date(calendarYear.value, calendarMonth.value + 1, 0).getDate();
        const prevMonthDays = new Date(calendarYear.value, calendarMonth.value, 0).getDate();

        for (let i = firstDay - 1; i >= 0; i--) {
            days.push({
                date: new Date(calendarYear.value, calendarMonth.value - 1, prevMonthDays - i),
                isCurrentMonth: false,
            });
        }
        for (let i = 1; i <= daysInMonth; i++) {
            days.push({
                date: new Date(calendarYear.value, calendarMonth.value, i),
                isCurrentMonth: true,
            });
        }
        const remaining = 42 - days.length;
        for (let i = 1; i <= remaining; i++) {
            days.push({
                date: new Date(calendarYear.value, calendarMonth.value + 1, i),
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
        return Array.from({ length: 7 }, (_, i) => {
            const date = new Date(start);
            date.setDate(start.getDate() + i);
            return {
                date,
                isCurrentMonth: date.getMonth() === currentDate.value.getMonth(),
            };
        });
    });

    // Pre-computed item maps for performance
    const dateKey = (date) => {
        const d = toDateOnly(date);
        return d ? `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}` : '';
    };

    const singleDayItemsByDate = computed(() => {
        const map = new Map();
        for (const item of itemsRef.value || []) {
            const range = getItemRange(item);
            if (!range) continue;
            if (range.start.getTime() !== range.end.getTime()) continue;
            const key = dateKey(range.start);
            if (!map.has(key)) map.set(key, []);
            map.get(key).push(item);
        }
        return map;
    });

    const multiDayItems = computed(() =>
        (itemsRef.value || [])
            .map((item) => ({ item, range: getItemRange(item) }))
            .filter(({ range }) => range && range.start.getTime() !== range.end.getTime())
    );

    const getSingleDayItems = (date) => singleDayItemsByDate.value.get(dateKey(date)) || [];

    const getVisibleItemsForDate = (date, limit = 1) => getSingleDayItems(date).slice(0, limit);

    const getOverflowItemsCount = (date, limit = 1) => {
        const total = getSingleDayItems(date).length;
        return total > limit ? total - limit : 0;
    };

    const buildWeekBars = (days) => {
        const weekStart = toDateOnly(days[0]?.date);
        const weekEnd = toDateOnly(days[6]?.date);
        if (!weekStart || !weekEnd) return [];

        const candidates = multiDayItems.value
            .filter(({ range }) => rangesOverlap(range.start, range.end, weekStart, weekEnd))
            .sort((a, b) => {
                if (a.range.start.getTime() !== b.range.start.getTime()) {
                    return a.range.start.getTime() - b.range.start.getTime();
                }
                return b.range.end.getTime() - a.range.end.getTime();
            });

        const lanes = [];
        const bars = [];

        for (const { item, range } of candidates) {
            const visualStart = range.start < weekStart ? weekStart : range.start;
            const visualEnd = range.end > weekEnd ? weekEnd : range.end;
            const startCol = daysBetween(weekStart, visualStart) + 1;
            const endCol = daysBetween(weekStart, visualEnd) + 1;

            let laneIndex = 0;
            while (
                lanes[laneIndex] &&
                lanes[laneIndex].some((seg) => !(endCol < seg.startCol || startCol > seg.endCol))
            ) {
                laneIndex += 1;
            }
            if (!lanes[laneIndex]) lanes[laneIndex] = [];
            lanes[laneIndex].push({ startCol, endCol });

            bars.push({
                item,
                row: laneIndex,
                startCol,
                endCol,
                startsBeforeWeek: range.start < weekStart,
                endsAfterWeek: range.end > weekEnd,
                color: getColor(item) || '#6366F1',
            });
        }
        return bars;
    };

    const weekBarsByIndex = computed(() => calendarWeeks.value.map(buildWeekBars));
    const standaloneWeekBars = computed(() => buildWeekBars(weekDays.value));

    // Navigation
    const previousMonth = () => {
        currentDate.value = new Date(calendarYear.value, calendarMonth.value - 1, 1);
    };
    const nextMonth = () => {
        currentDate.value = new Date(calendarYear.value, calendarMonth.value + 1, 1);
    };
    const previousWeek = () => {
        const d = new Date(currentDate.value);
        d.setDate(d.getDate() - 7);
        currentDate.value = d;
    };
    const nextWeek = () => {
        const d = new Date(currentDate.value);
        d.setDate(d.getDate() + 7);
        currentDate.value = d;
    };
    const previousPeriod = () => (subView.value === 'month' ? previousMonth() : previousWeek());
    const nextPeriod = () => (subView.value === 'month' ? nextMonth() : nextWeek());
    const goToToday = () => {
        currentDate.value = new Date();
    };

    return {
        // state
        currentDate,
        subView,
        // header
        calendarTitle,
        monthLabel,
        weekLabel,
        // grids
        calendarDays,
        calendarWeeks,
        weekDays,
        // bars + items
        weekBarsByIndex,
        standaloneWeekBars,
        getVisibleItemsForDate,
        getOverflowItemsCount,
        // helpers (re-exported so callers don't need their own)
        toDateOnly,
        isSameDate,
        isDateToday,
        getWeekStart,
        getItemRange,
        // navigation
        previousPeriod,
        nextPeriod,
        previousMonth,
        nextMonth,
        previousWeek,
        nextWeek,
        goToToday,
    };
}
