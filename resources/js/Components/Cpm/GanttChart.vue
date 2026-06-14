<script setup>
import { ref, computed } from 'vue';
import { formatHours as formatDuration } from '@/utils/duration';

const props = defineProps({
    cpmData: {
        type: Object,
        required: true,
    },
    workspace: { type: Object, default: null },
    space: { type: Object, default: null },
    list: { type: Object, default: null },
    task: { type: Object, default: null },
});

const emit = defineEmits(['subtask-click', 'dependency-add', 'dependency-remove']);

// Zoom level (pixels per hour)
const zoomLevel = ref(40);
const zoomLevels = [20, 30, 40, 60, 80, 100];

// Chart dimensions
const chartPadding = 200; // Left padding for task names
const rowHeight = 48;
const headerHeight = 50;

// Computed values
const subtasks = computed(() => {
    if (!props.cpmData?.success || !props.cpmData?.data?.subtasks) {
        return [];
    }
    return Object.values(props.cpmData.data.subtasks).sort((a, b) => a.earlyStart - b.earlyStart);
});

const summary = computed(() => props.cpmData?.data?.summary || {});
const criticalPath = computed(() => props.cpmData?.data?.criticalPath || []);

const projectDuration = computed(() => summary.value.projectDurationHours || 0);

// Calculate chart width based on project duration and zoom
const chartWidth = computed(() => {
    return chartPadding + (projectDuration.value * zoomLevel.value) + 100;
});

// Calculate chart height
const chartHeight = computed(() => {
    return headerHeight + (subtasks.value.length * rowHeight) + 50;
});

// Time markers for the header
const timeMarkers = computed(() => {
    const markers = [];
    const maxHours = Math.ceil(projectDuration.value);
    const interval = zoomLevel.value >= 60 ? 1 : zoomLevel.value >= 30 ? 2 : 4;

    for (let h = 0; h <= maxHours; h += interval) {
        markers.push({
            hour: h,
            x: chartPadding + (h * zoomLevel.value),
            label: `${h}h`,
        });
    }
    return markers;
});

// Get bar position and width for a subtask
const getBarStyle = (subtask) => {
    const left = chartPadding + (subtask.earlyStart * zoomLevel.value);
    const width = Math.max(subtask.duration * zoomLevel.value, 20); // Minimum width of 20px

    return {
        left: `${left}px`,
        width: `${width}px`,
    };
};

// Get slack bar style (shown after the main bar)
const getSlackStyle = (subtask) => {
    if (subtask.slack <= 0) return null;

    const left = chartPadding + (subtask.earlyFinish * zoomLevel.value);
    const width = subtask.slack * zoomLevel.value;

    return {
        left: `${left}px`,
        width: `${width}px`,
    };
};

// Get color for subtask bar
const getBarColor = (subtask) => {
    if (subtask.completedAt) {
        return 'bg-green-500'; // Completed takes priority over critical
    }
    if (subtask.isCritical) {
        return 'bg-red-500'; // Critical path (not yet completed)
    }
    return 'bg-blue-500'; // Normal
};


// Zoom controls
const zoomIn = () => {
    const currentIndex = zoomLevels.indexOf(zoomLevel.value);
    if (currentIndex < zoomLevels.length - 1) {
        zoomLevel.value = zoomLevels[currentIndex + 1];
    }
};

const zoomOut = () => {
    const currentIndex = zoomLevels.indexOf(zoomLevel.value);
    if (currentIndex > 0) {
        zoomLevel.value = zoomLevels[currentIndex - 1];
    }
};

// Dependency line drawing
const getDependencyLines = computed(() => {
    const lines = [];

    subtasks.value.forEach((subtask, index) => {
        subtask.dependencies?.forEach(depId => {
            const depSubtask = subtasks.value.find(s => s.id === depId);
            if (!depSubtask) return;

            const depIndex = subtasks.value.findIndex(s => s.id === depId);

            // Calculate line coordinates
            const startX = chartPadding + (depSubtask.earlyFinish * zoomLevel.value);
            const startY = headerHeight + (depIndex * rowHeight) + (rowHeight / 2);
            const endX = chartPadding + (subtask.earlyStart * zoomLevel.value);
            const endY = headerHeight + (index * rowHeight) + (rowHeight / 2);

            // Create path with curve
            const midX = (startX + endX) / 2;

            lines.push({
                id: `${depId}-${subtask.id}`,
                path: `M ${startX} ${startY} C ${midX} ${startY}, ${midX} ${endY}, ${endX} ${endY}`,
                isCritical: subtask.isCritical && depSubtask.isCritical,
                fromId: depId,
                toId: subtask.id,
            });
        });
    });

    return lines;
});

// Handle subtask click
const handleSubtaskClick = (subtask) => {
    if (linkingMode.value) {
        completeLinking(subtask);
    } else {
        emit('subtask-click', subtask);
    }
};

// Dependency Linking Mode
const linkingMode = ref(false);
const linkingSource = ref(null);

const startLinking = (subtask) => {
    linkingMode.value = true;
    linkingSource.value = subtask;
};

const cancelLinking = () => {
    linkingMode.value = false;
    linkingSource.value = null;
};

const completeLinking = (target) => {
    if (!linkingSource.value || linkingSource.value.id === target.id) {
        cancelLinking();
        return;
    }
    emit('dependency-add', {
        subtaskId: target.id,
        dependsOnId: linkingSource.value.id,
    });
    cancelLinking();
};

const handleRemoveDependency = (line) => {
    emit('dependency-remove', {
        subtaskId: line.toId,
        dependsOnId: line.fromId,
    });
};

// Tooltip state
const tooltipSubtask = ref(null);
const tooltipPosition = ref({ x: 0, y: 0 });

const showTooltip = (subtask, event) => {
    tooltipSubtask.value = subtask;

    const tooltipWidth = 280;
    const tooltipHeight = 260;
    const margin = 12;
    const vw = window.innerWidth;
    const vh = window.innerHeight;

    let x = event.clientX + margin;
    let y = event.clientY + margin;

    // Flip left if overflowing right
    if (x + tooltipWidth > vw) {
        x = event.clientX - tooltipWidth - margin;
    }
    // Flip up if overflowing bottom
    if (y + tooltipHeight > vh) {
        y = event.clientY - tooltipHeight - margin;
    }

    // Clamp to viewport edges
    x = Math.max(4, x);
    y = Math.max(4, y);

    tooltipPosition.value = { x, y };
};

const hideTooltip = () => {
    tooltipSubtask.value = null;
};

// Scroll container ref
const scrollContainer = ref(null);
</script>

<template>
    <div class="gantt-chart-container">
        <!-- Header with controls -->
        <div class="gantt-header flex items-center justify-between p-4 border-b border-gray-700">
            <div class="flex items-center gap-4">
                <h3 class="text-lg font-semibold">Gantt Chart - CPM Analysis</h3>
                <div class="flex items-center gap-2">
                    <v-chip size="small" color="error" variant="flat">
                        <v-icon start size="14">mdi-alert-circle</v-icon>
                        Critical Path: {{ criticalPath.length }} subtasks
                    </v-chip>
                    <v-chip size="small" color="primary" variant="tonal">
                        Duration: {{ formatDuration(projectDuration) }}
                    </v-chip>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- Add Dependency (Link) Button -->
                <v-btn v-if="!linkingMode" variant="tonal" size="small" color="warning" @click="linkingMode = true">
                    <v-icon start size="16">mdi-link-plus</v-icon>
                    Add Dependency
                </v-btn>
                <template v-else>
                    <v-chip color="warning" variant="flat" size="small">
                        <v-icon start size="14">mdi-cursor-default-click</v-icon>
                        {{ linkingSource ? 'Now click the successor...' : 'Click predecessor first...' }}
                    </v-chip>
                    <v-btn variant="text" size="small" @click="cancelLinking">
                        <v-icon>mdi-close</v-icon>
                        Cancel
                    </v-btn>
                </template>

                <!-- Zoom controls -->
                <v-btn-group density="compact" variant="outlined">
                    <v-btn icon size="small" @click="zoomOut" :disabled="zoomLevel <= zoomLevels[0]">
                        <v-icon>mdi-magnify-minus</v-icon>
                    </v-btn>
                    <v-btn size="small" disabled class="px-2">
                        {{ zoomLevel }}px/h
                    </v-btn>
                    <v-btn icon size="small" @click="zoomIn" :disabled="zoomLevel >= zoomLevels[zoomLevels.length - 1]">
                        <v-icon>mdi-magnify-plus</v-icon>
                    </v-btn>
                </v-btn-group>
            </div>
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-4 px-4 py-2 bg-[#252526] border-b border-gray-700">
            <div class="flex items-center gap-2 text-sm">
                <div class="w-4 h-4 rounded bg-red-500"></div>
                <span class="text-gray-400">Critical Path</span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <div class="w-4 h-4 rounded bg-blue-500"></div>
                <span class="text-gray-400">Normal</span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <div class="w-4 h-4 rounded bg-green-500"></div>
                <span class="text-gray-400">Completed</span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <div class="w-4 h-2 rounded bg-gray-600"></div>
                <span class="text-gray-400">Slack (Float)</span>
            </div>
        </div>

        <!-- Error state -->
        <div v-if="!cpmData?.success" class="p-8 text-center">
            <v-icon size="64" color="warning" class="mb-4">mdi-alert-circle-outline</v-icon>
            <p class="text-lg text-gray-400">{{ cpmData?.message || 'Unable to calculate CPM' }}</p>
            <p class="text-sm text-gray-500 mt-2">
                Make sure subtasks have time estimates and no circular dependencies.
            </p>
        </div>

        <!-- Chart container -->
        <div v-else ref="scrollContainer" class="gantt-scroll-container overflow-auto"
            style="max-height: calc(100vh - 300px);">
            <svg :width="chartWidth" :height="chartHeight" class="gantt-svg">
                <!-- Background grid -->
                <defs>
                    <pattern id="grid" :width="zoomLevel" height="48" patternUnits="userSpaceOnUse">
                        <path :d="`M ${zoomLevel} 0 L 0 0 0 48`" fill="none" stroke="#333" stroke-width="0.5" />
                    </pattern>
                </defs>
                <rect :x="chartPadding" y="0" :width="chartWidth - chartPadding" :height="chartHeight"
                    fill="url(#grid)" />

                <!-- Time header -->
                <g class="time-header">
                    <rect x="0" y="0" :width="chartWidth" :height="headerHeight" fill="#1e1e1e" />
                    <line :x1="chartPadding" :y1="headerHeight" :x2="chartWidth" :y2="headerHeight" stroke="#444" />

                    <g v-for="marker in timeMarkers" :key="marker.hour">
                        <line :x1="marker.x" :y1="headerHeight - 10" :x2="marker.x" :y2="headerHeight" stroke="#666" />
                        <text :x="marker.x" :y="headerHeight - 15" fill="#999" text-anchor="middle" font-size="12">
                            {{ marker.label }}
                        </text>
                    </g>
                </g>

                <!-- Task name column header -->
                <rect x="0" y="0" :width="chartPadding" :height="headerHeight" fill="#1e1e1e" />
                <text x="10" :y="headerHeight / 2 + 5" fill="#fff" font-size="14" font-weight="bold">
                    Subtask
                </text>

                <!-- Dependency lines (behind bars) -->
                <g class="dependency-lines">
                    <!-- Invisible wider path for easier click target -->
                    <path v-for="line in getDependencyLines" :key="'hit-' + line.id" :d="line.path" fill="none"
                        stroke="transparent" stroke-width="12" class="cursor-pointer"
                        @click.stop="handleRemoveDependency(line)" />
                    <!-- Visible line -->
                    <path v-for="line in getDependencyLines" :key="line.id" :d="line.path" fill="none"
                        :stroke="line.isCritical ? '#ef4444' : '#666'" stroke-width="2"
                        :marker-end="line.isCritical ? 'url(#arrowhead-critical)' : 'url(#arrowhead)'"
                        class="dependency-line cursor-pointer" @click.stop="handleRemoveDependency(line)">
                        <title>Click to remove dependency</title>
                    </path>
                </g>

                <!-- Arrow marker definition -->
                <defs>
                    <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                        <polygon points="0 0, 10 3.5, 0 7" fill="#666" />
                    </marker>
                    <marker id="arrowhead-critical" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                        <polygon points="0 0, 10 3.5, 0 7" fill="#ef4444" />
                    </marker>
                </defs>

                <!-- Subtask rows -->
                <g v-for="(subtask, index) in subtasks" :key="subtask.id" class="subtask-row">
                    <!-- Row background -->
                    <rect x="0" :y="headerHeight + (index * rowHeight)" :width="chartWidth" :height="rowHeight"
                        :fill="linkingMode && linkingSource?.id === subtask.id ? '#3d3d00' : (index % 2 === 0 ? '#1e1e1e' : '#252526')"
                        :class="linkingMode ? 'cursor-crosshair' : 'cursor-pointer'" class="hover:brightness-110"
                        @click="linkingMode ? (linkingSource ? completeLinking(subtask) : startLinking(subtask)) : handleSubtaskClick(subtask)" />

                    <!-- Task name -->
                    <foreignObject x="10" :y="headerHeight + (index * rowHeight) + 8" :width="chartPadding - 20"
                        :height="rowHeight - 16">
                        <div class="flex items-center gap-2 h-full">
                            <v-icon v-if="subtask.completedAt" size="16" color="success">mdi-check-circle</v-icon>
                            <v-icon v-else-if="subtask.isCritical" size="16" color="error">mdi-alert-circle</v-icon>
                            <span class="text-sm truncate" :class="{ 'text-red-400 font-medium': subtask.isCritical && !subtask.completedAt }">
                                {{ subtask.name }}
                            </span>
                        </div>
                    </foreignObject>

                    <!-- Subtask bar -->
                    <foreignObject :x="chartPadding + (subtask.earlyStart * zoomLevel)"
                        :y="headerHeight + (index * rowHeight) + 10" :width="Math.max(subtask.duration * zoomLevel, 30)"
                        :height="rowHeight - 20">
                        <div class="gantt-bar h-full rounded flex items-center justify-center transition-all hover:brightness-110"
                            :class="[
                                getBarColor(subtask),
                                linkingMode ? 'cursor-crosshair' : 'cursor-pointer',
                                linkingMode && linkingSource?.id === subtask.id ? 'ring-2 ring-yellow-400' : '',
                            ]" @mouseenter="showTooltip(subtask, $event)" @mouseleave="hideTooltip"
                            @click.stop="linkingMode ? (linkingSource ? completeLinking(subtask) : startLinking(subtask)) : handleSubtaskClick(subtask)">
                            <span v-if="subtask.duration * zoomLevel > 40" class="text-xs text-white font-medium">
                                {{ formatDuration(subtask.duration) }}
                            </span>
                        </div>
                    </foreignObject>

                    <!-- Slack bar (if any) -->
                    <rect v-if="subtask.slack > 0" :x="chartPadding + (subtask.earlyFinish * zoomLevel)"
                        :y="headerHeight + (index * rowHeight) + 18" :width="subtask.slack * zoomLevel" :height="12"
                        fill="#4b5563" rx="2" opacity="0.6" />
                </g>

                <!-- Today line (if using real dates) -->
                <line v-if="false" :x1="chartPadding + 100" :y1="headerHeight" :x2="chartPadding + 100"
                    :y2="chartHeight" stroke="#f59e0b" stroke-width="2" stroke-dasharray="5,5" />
            </svg>
        </div>

        <!-- Tooltip -->
        <Teleport to="body">
            <div v-if="tooltipSubtask"
                class="gantt-tooltip fixed z-50 bg-[#2d2d30] border border-gray-600 rounded-lg shadow-xl p-3 max-w-xs"
                :style="{ left: tooltipPosition.x + 'px', top: tooltipPosition.y + 'px' }">
                <div class="font-semibold mb-2" :class="{ 'text-red-400': tooltipSubtask.isCritical }">
                    {{ tooltipSubtask.name }}
                    <v-chip v-if="tooltipSubtask.isCritical" size="x-small" color="error" class="ml-2">Critical</v-chip>
                </div>
                <div class="space-y-1 text-sm text-gray-400">
                    <div class="flex justify-between">
                        <span>Duration:</span>
                        <span class="text-white">{{ formatDuration(tooltipSubtask.duration) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Early Start:</span>
                        <span class="text-white">{{ formatDuration(tooltipSubtask.earlyStart) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Early Finish:</span>
                        <span class="text-white">{{ formatDuration(tooltipSubtask.earlyFinish) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Late Start:</span>
                        <span class="text-white">{{ formatDuration(tooltipSubtask.lateStart) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Late Finish:</span>
                        <span class="text-white">{{ formatDuration(tooltipSubtask.lateFinish) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-gray-600 pt-1 mt-1">
                        <span>Slack (Float):</span>
                        <span :class="tooltipSubtask.slack === 0 ? 'text-red-400' : 'text-green-400'">
                            {{ formatDuration(tooltipSubtask.slack) }}
                        </span>
                    </div>
                </div>
                <div v-if="tooltipSubtask.dependencies?.length" class="mt-2 pt-2 border-t border-gray-600">
                    <span class="text-xs text-gray-500">
                        Depends on {{ tooltipSubtask.dependencies.length }} subtask(s)
                    </span>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.gantt-chart-container {
    background-color: #1e1e1e;
    border-radius: 8px;
    overflow: hidden;
}

.gantt-scroll-container {
    scrollbar-width: thin;
    scrollbar-color: #555 #1e1e1e;
}

.gantt-scroll-container::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.gantt-scroll-container::-webkit-scrollbar-track {
    background: #1e1e1e;
}

.gantt-scroll-container::-webkit-scrollbar-thumb {
    background-color: #555;
    border-radius: 4px;
}

.gantt-bar {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.dependency-line {
    transition: stroke 0.2s ease;
}

.dependency-line:hover {
    stroke-width: 3;
}

.gantt-tooltip {
    pointer-events: none;
}
</style>
