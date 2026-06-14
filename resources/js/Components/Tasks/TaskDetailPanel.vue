<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from "vue";
import { router } from "@inertiajs/vue3";
import { useSnackbar } from "@/composables/useSnackbar";
import { useTaskTimer } from "@/composables/useTaskTimer";
import { getStoredSubtaskCompletionTarget } from "@/utils/subtaskCompletionAutomation";
import DeleteConfirmDialog from "@/Components/DeleteConfirmDialog.vue";
import DetailsTab from "./DetailsTab.vue";
import CommentsTab from "./CommentsTab.vue";
import TimeTab from "./TimeTab.vue";
import ActivityTab from "./ActivityTab.vue";

const { showSnackbar } = useSnackbar();

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  task: { type: Object, default: null },
  workspace: { type: Object, default: null },
  space: { type: Object, default: null },
  list: { type: Object, default: null },
  parentTask: { type: Object, default: null },
  siblingSubtasks: { type: Array, default: () => [] },
  statuses: { type: Array, default: () => [] },
  members: { type: Array, default: () => [] },
  labels: { type: Array, default: () => [] },
  sprints: { type: Array, default: () => [] },
  canOperateTasks: { type: Boolean, default: false },
  canManageTaskStructure: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:modelValue",
  "updated",
  "view-subtasks",
  "open-subtask",
]);

// Local reactive copy of task to ensure proper reactivity
const localTask = ref(null);
const panelBodyRef = ref(null);

const deepClone = (obj) => {
  if (!obj) return null;
  try {
    return structuredClone(obj);
  } catch {
    return JSON.parse(JSON.stringify(obj));
  }
};

// Timer composable
const {
  isTracking,
  formatTrackingDuration,
  isTimerLoading,
  start: startTracking,
  stop: stopTracking,
  init: initRunningTimer,
  reset: resetTimer,
  stopInterval: stopTimerInterval,
} = useTaskTimer(props, localTask);

// Watch for task prop changes
watch(
  () => props.task,
  (newTask, oldTask) => {
    localTask.value = deepClone(newTask);
    if (oldTask?.id !== newTask?.id) {
      resetTimer();
      if (newTask) nextTick(() => initRunningTimer());
      nextTick(() => { if (panelBodyRef.value) panelBodyRef.value.scrollTop = 0; });
    }
  },
  { immediate: true, deep: true }
);

// Determine if we're viewing a subtask
const isSubtask = computed(() => !!props.parentTask);

// Route helpers (used in header: status chip, delete)
const getUpdateRoute = () => {
  if (isSubtask.value) {
    return route("tasks.subtasks.update", [
      props.workspace.id,
      props.space.id,
      props.list.id,
      props.parentTask.id,
      props.task.id,
    ]);
  }
  return route("tasks.update", [
    props.workspace.id,
    props.space.id,
    props.list.id,
    props.task.id,
  ]);
};

// Tab state
const activeTab = ref("details");

// Task name editing (header)
const isEditing = ref(false);
const editedName = ref("");
watch(
  () => localTask.value,
  (t) => {
    if (t) editedName.value = t.name;
  },
  { immediate: true }
);

const saveName = () => {
  if (editedName.value.trim() && editedName.value !== props.task.name) {
    if (editedName.value.trim().length > 255) {
      showSnackbar("Name cannot exceed 255 characters.", "error");
      isEditing.value = false;
      return;
    }
    router.patch(
      getUpdateRoute(),
      { name: editedName.value.trim() },
      {
        preserveScroll: true,
        onSuccess: () => {
          router.reload({ only: ["tasksByStatus"] });
          showSnackbar(
            `${isSubtask.value ? "Subtask" : "Task"} name updated!`,
            "success"
          );
        },
      }
    );
  }
  isEditing.value = false;
};

// Panel close
const close = () => emit("update:modelValue", false);



// Completion state
const isCompleted = computed(() => localTask.value?.completed_at);

// Change status (used in header)
const changeStatus = (statusId) => {
  router.patch(
    getUpdateRoute(),
    { status_id: statusId },
    {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({ only: ["tasksByStatus"] });
        showSnackbar("Status changed!", "success");
      },
    }
  );
};

// Toggle complete (subtask only)
const toggleComplete = () => {
  if (!isSubtask.value) return;
  const wasCompleted = isCompleted.value;
  const targetStatusId = getStoredSubtaskCompletionTarget(
    props.space?.id,
    props.statuses
  );
  const payload =
    !wasCompleted && targetStatusId ? { target_status_id: targetStatusId } : {};

  router.post(
    route(wasCompleted ? "tasks.subtasks.reopen" : "tasks.subtasks.complete", [
      props.workspace.id,
      props.space.id,
      props.list.id,
      props.parentTask.id,
      props.task.id,
    ]),
    payload,
    {
      preserveScroll: true,
      onSuccess: () => {
        showSnackbar(
          wasCompleted ? "Subtask reopened!" : "Subtask completed!",
          "success"
        );
        router.reload({ only: ["tasksByStatus"] });
      },
      onError: (errors) => {
        if (errors.dependency)
          showSnackbar(errors.dependency, "error");
      },
    }
  );
};

// Delete task
const showDeleteDialog = ref(false);
const isDeleting = ref(false);

const performDelete = () => {
  const deleteUrl = isSubtask.value
    ? route("tasks.subtasks.destroy", [
      props.workspace.id,
      props.space.id,
      props.list.id,
      props.parentTask.id,
      props.task.id,
    ])
    : route("tasks.destroy", [
      props.workspace.id,
      props.space.id,
      props.list.id,
      props.task.id,
    ]);
  isDeleting.value = true;
  router.delete(
    deleteUrl,
    {
      preserveScroll: true,
      onSuccess: () => {
        showSnackbar("Task deleted!", "success");
        showDeleteDialog.value = false;
        close();
      },
      onFinish: () => {
        isDeleting.value = false;
      },
    }
  );
};

// Duplicate task / subtask
const duplicateTask = () => {
  const url = isSubtask.value
    ? route("tasks.subtasks.duplicate", [
      props.workspace.id,
      props.space.id,
      props.list.id,
      props.parentTask.id,
      props.task.id,
    ])
    : route("tasks.duplicate", [
      props.workspace.id,
      props.space.id,
      props.list.id,
      props.task.id,
    ]);
  router.post(
    url,
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        showSnackbar(
          isSubtask.value ? "Subtask duplicated!" : "Task duplicated!",
          "success"
        );
        router.reload({ only: ["tasksByStatus"] });
      },
    }
  );
};

// Main task ID for comments route (always the parent when viewing a subtask)
const mainTaskId = computed(() =>
  isSubtask.value ? props.parentTask?.id : props.task?.id
);

onMounted(() => initRunningTimer());
onUnmounted(() => stopTimerInterval());
</script>

<template>
  <v-navigation-drawer :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)"
    @click:outside="close" location="right" temporary :scrim="true" width="620" class="task-detail-panel">
    <div v-if="localTask" class="d-flex flex-column h-100">
      <!-- Header -->
      <div class="panel-header">
        <div class="d-flex align-center ga-2">
          <!-- Complete Button (subtask only) -->
          <v-btn v-if="isSubtask && canOperateTasks" :icon="isCompleted
            ? 'mdi-checkbox-marked-circle'
            : 'mdi-checkbox-blank-circle-outline'
            " :color="isCompleted ? 'success' : 'grey'" variant="text" size="small" @click="toggleComplete" />

          <!-- Status Chip -->
          <v-menu :disabled="!canOperateTasks">
            <template v-slot:activator="{ props: menuProps }">
              <v-chip v-bind="menuProps" :color="localTask.status?.color" size="small" variant="flat"
                class="cursor-pointer font-weight-medium">
                {{ localTask.status?.name || "No Status" }}
                <v-icon end size="14">mdi-chevron-down</v-icon>
              </v-chip>
            </template>
            <v-card color="surface" min-width="180">
              <v-list density="compact">
                <v-list-item v-for="status in statuses" :key="status.id" :active="status.id === localTask.status_id"
                  @click="changeStatus(status.id)">
                  <template v-slot:prepend>
                    <div class="w-3 h-3 rounded-full mr-3" :style="{ backgroundColor: status.color }" />
                  </template>
                  <v-list-item-title>{{ status.name }}</v-list-item-title>
                </v-list-item>
              </v-list>
            </v-card>
          </v-menu>

          <!-- Task ID badge -->
          <v-chip v-if="localTask.task_id" size="x-small" variant="outlined" color="grey">
            {{ localTask.task_id }}
          </v-chip>
        </div>

        <div class="d-flex align-center">
          <v-menu v-if="canManageTaskStructure">
            <template v-slot:activator="{ props: menuProps }">
              <v-btn v-bind="menuProps" icon variant="text" size="small">
                <v-icon size="20">mdi-dots-horizontal</v-icon>
              </v-btn>
            </template>
            <v-card color="surface" min-width="160">
              <v-list density="compact">
                <v-list-item prepend-icon="mdi-content-copy" title="Duplicate" @click="duplicateTask" />
                <v-divider />
                <v-list-item prepend-icon="mdi-delete-outline" title="Delete" class="text-error" @click="showDeleteDialog = true" />
              </v-list>
            </v-card>
          </v-menu>
          <v-btn icon variant="text" size="small" @click="close">
            <v-icon size="20">mdi-close</v-icon>
          </v-btn>
        </div>
      </div>

      <!-- Task Name -->
      <div class="px-5 pt-4 pb-2">
        <div v-if="!isEditing" class="task-name-display" @click="canOperateTasks && (isEditing = true)">
          {{ localTask.name }}
        </div>
        <v-text-field v-else v-model="editedName" variant="outlined" density="compact" hide-details autofocus
          class="task-name-input" @blur="saveName" @keydown.enter="saveName" @keydown.escape="isEditing = false" />
      </div>

      <!-- Tabs -->
      <v-tabs v-model="activeTab" color="primary" class="flex-shrink-0 px-2" height="40">
        <v-tab value="details" size="small">
          <v-icon start size="16">mdi-text-box-outline</v-icon>
          Details
        </v-tab>
        <v-tab value="comments" size="small">
          <v-icon start size="16">mdi-comment-outline</v-icon>
          Comments
          <v-badge v-if="localTask.comments?.length" :content="localTask.comments.length" color="primary" inline
            class="ml-1" />
        </v-tab>
        <v-tab v-if="isSubtask" value="time" size="small">
          <v-icon start size="16">mdi-clock-outline</v-icon>
          Time
          <v-badge v-if="localTask.time_entries?.length" :content="localTask.time_entries.length" color="primary" inline
            class="ml-1" />
        </v-tab>
        <v-tab value="activity" size="small">
          <v-icon start size="16">mdi-history</v-icon>
          Activity
        </v-tab>
      </v-tabs>

      <v-divider />

      <!-- Tab Content -->
      <div ref="panelBodyRef" class="flex-1 overflow-y-auto">
        <v-tabs-window v-model="activeTab">
          <v-tabs-window-item value="details">
            <DetailsTab :local-task="localTask" :task="task" :is-subtask="isSubtask" :workspace="workspace"
              :space="space" :list="list" :parent-task="parentTask" :statuses="statuses" :members="members"
              :labels="labels" :sprints="sprints" :sibling-subtasks="siblingSubtasks" :is-tracking="isTracking"
              :format-tracking-duration="formatTrackingDuration" :is-timer-loading="isTimerLoading"
              :can-operate-tasks="canOperateTasks" :can-manage-task-structure="canManageTaskStructure"
              @start-tracking="startTracking" @stop-tracking="stopTracking" @updated="emit('updated')"
              @view-subtasks="emit('view-subtasks', $event)" @open-subtask="emit('open-subtask', $event)" />
          </v-tabs-window-item>

          <v-tabs-window-item value="comments">
            <CommentsTab :comments="localTask.comments || []" :is-subtask="isSubtask" :workspace="workspace"
              :space="space" :list="list" :main-task-id="mainTaskId" :subtask-id="isSubtask ? task?.id : null"
              @updated="emit('updated')" />
          </v-tabs-window-item>

          <v-tabs-window-item v-if="isSubtask" value="time">
            <TimeTab :task="localTask" :workspace="workspace" :space="space" :list="list" :parent-task="parentTask" />
          </v-tabs-window-item>

          <v-tabs-window-item value="activity">
            <ActivityTab :activities="localTask.activities || []" />
          </v-tabs-window-item>
        </v-tabs-window>
      </div>
    </div>
  </v-navigation-drawer>

  <DeleteConfirmDialog
    v-model="showDeleteDialog"
    :item-type="isSubtask ? 'subtask' : 'task'"
    :item-name="localTask?.name"
    :loading="isDeleting"
    @confirm="performDelete"
  />
</template>

<style scoped>
.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.task-name-display {
  font-size: 1.15rem;
  font-weight: 600;
  line-height: 1.4;
  cursor: pointer;
  padding: 6px 10px;
  margin: 0 -10px;
  border-radius: 6px;
  transition: background-color 0.12s;
}

.task-name-display:hover {
  background-color: rgba(255, 255, 255, 0.04);
}

.task-name-input :deep(.v-field) {
  font-size: 1.15rem;
  font-weight: 600;
}
</style>