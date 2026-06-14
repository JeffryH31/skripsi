<script setup>
import { ref, computed, watch } from "vue";
import { router } from "@inertiajs/vue3";
import ChecklistItemRow from "./ChecklistItemRow.vue";
import SubtaskRow from "./SubtaskRow.vue";
import TaskPropertiesSection from "./TaskPropertiesSection.vue";
import TaskDescriptionSection from "./TaskDescriptionSection.vue";
import { useSnackbar } from "@/composables/useSnackbar";
import {
  getStoredSubtaskCompletionTarget,
} from "@/utils/subtaskCompletionAutomation";

const { showSnackbar } = useSnackbar();

const props = defineProps({
  localTask: { type: Object, default: null },
  task: { type: Object, default: null },
  isSubtask: { type: Boolean, default: false },
  workspace: { type: Object, default: null },
  space: { type: Object, default: null },
  list: { type: Object, default: null },
  parentTask: { type: Object, default: null },
  statuses: { type: Array, default: () => [] },
  members: { type: Array, default: () => [] },
  labels: { type: Array, default: () => [] },
  sprints: { type: Array, default: () => [] },
  siblingSubtasks: { type: Array, default: () => [] },
  isTracking: { type: Boolean, default: false },
  formatTrackingDuration: { type: String, default: '' },
  isTimerLoading: { type: Boolean, default: false },
  canOperateTasks: { type: Boolean, default: false },
  canManageTaskStructure: { type: Boolean, default: false },
});

const emit = defineEmits([
  "start-tracking",
  "stop-tracking",
  "updated",
  "view-subtasks",
  "open-subtask",
]);

// Description
const editedDescription = ref(props.localTask?.description || "");
watch(
  () => props.localTask?.description,
  (v) => {
    editedDescription.value = v || "";
  },
  { immediate: true }
);

const getUpdateRoute = () => {
  if (props.isSubtask) {
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

const saveDescription = () => {
  if (editedDescription.value !== props.task.description) {
    router.patch(
      getUpdateRoute(),
      { description: editedDescription.value },
      {
        preserveScroll: true,
        onSuccess: () => {
          router.reload({ only: ["tasksByStatus"] });
        },
      }
    );
  }
};

// Subtask list management (for tasks, not subtask panel)
const topLevelSubtasks = computed(() => {
  return (props.localTask?.subtasks || []).filter((s) => !s.parent_id);
});

const toggleSubtask = (subtask) => {
  const done = !!subtask.completed_at;
  const targetStatusId = getStoredSubtaskCompletionTarget(
    props.space?.id,
    props.statuses
  );
  const payload =
    !done && targetStatusId ? { target_status_id: targetStatusId } : {};

  router.post(
    route(done ? "tasks.subtasks.reopen" : "tasks.subtasks.complete", [
      props.workspace.id,
      props.space.id,
      props.list.id,
      props.task.id,
      subtask.id,
    ]),
    payload,
    {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({ only: ["tasksByStatus"] });
      },
      onError: (errors) => {
        if (errors.dependency)
          showSnackbar(errors.dependency, "error");
      },
    }
  );
};

// Checklist items (subtask only)
const checklistRouteParams = computed(() => {
  if (!props.isSubtask || !props.parentTask) return [];
  return [
    props.workspace.id,
    props.space.id,
    props.list.id,
    props.parentTask.id,
    props.task.id,
  ];
});

// Build a nested tree from the flat checklist_items array using parent_id
const checklistTree = computed(() => {
  const items = props.localTask?.checklist_items || [];
  if (!items.length) return [];
  const map = new Map(items.map((i) => [i.id, { ...i, children: [] }]));
  const roots = [];
  for (const item of items) {
    if (item.parent_id && map.has(item.parent_id)) {
      map.get(item.parent_id).children.push(map.get(item.id));
    } else {
      roots.push(map.get(item.id));
    }
  }
  const sort = (arr) => {
    arr.sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
    arr.forEach((i) => sort(i.children));
    return arr;
  };
  return sort(roots);
});

const newChecklistName = ref("");
const checklistAddLoading = ref(false);

const addChecklistItem = () => {
  const name = newChecklistName.value.trim();
  if (!name) return;
  checklistAddLoading.value = true;
  router.post(
    route("tasks.subtasks.checklist.store", checklistRouteParams.value),
    { name },
    {
      preserveScroll: true,
      onSuccess: () => {
        newChecklistName.value = "";
        router.reload({ only: ["tasksByStatus"] });
      },
      onFinish: () => {
        checklistAddLoading.value = false;
      },
    }
  );
};

const onChecklistReloaded = () => {
  router.reload({ only: ["tasksByStatus"] });
};

// Subtasks (when viewing a depth < MAX_DEPTH subtask)
const newChildSubtaskName = ref("");
const addChildSubtaskLoading = ref(false);

const addChildSubtask = () => {
  const name = newChildSubtaskName.value.trim();
  if (!name || !props.parentTask) return;
  addChildSubtaskLoading.value = true;
  router.post(
    route("tasks.subtasks.store", [
      props.workspace.id,
      props.space.id,
      props.list.id,
      props.parentTask.id,
    ]),
    { name, parent_id: props.task.id, task_id: props.parentTask.id },
    {
      preserveScroll: true,
      onSuccess: () => {
        newChildSubtaskName.value = "";
        router.reload({ only: ["tasksByStatus"] });
      },
      onFinish: () => {
        addChildSubtaskLoading.value = false;
      },
    }
  );
};

const toggleChildSubtask = (child) => {
  if (!props.canOperateTasks || !props.parentTask) return;
  const done = !!child.completed_at;
  const targetStatusId = getStoredSubtaskCompletionTarget(
    props.space?.id,
    props.statuses
  );
  const payload =
    !done && targetStatusId ? { target_status_id: targetStatusId } : {};
  router.post(
    route(done ? "tasks.subtasks.reopen" : "tasks.subtasks.complete", [
      props.workspace.id,
      props.space.id,
      props.list.id,
      props.parentTask.id,
      child.id,
    ]),
    payload,
    {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({ only: ["tasksByStatus"] });
      },
      onError: (errors) => {
        if (errors.dependency)
          showSnackbar(errors.dependency, "error");
      },
    }
  );
};
</script>

<template>
  <div class="pa-5">
    <!-- Properties Section -->
    <TaskPropertiesSection :local-task="localTask" :task="task" :is-subtask="isSubtask" :workspace="workspace"
      :space="space" :list="list" :parent-task="parentTask" :statuses="statuses" :members="members" :labels="labels"
      :sprints="sprints" :sibling-subtasks="siblingSubtasks" :is-tracking="isTracking"
      :format-tracking-duration="formatTrackingDuration" :is-timer-loading="isTimerLoading"
      :can-operate-tasks="canOperateTasks" :can-manage-task-structure="canManageTaskStructure"
      @start-tracking="$emit('start-tracking')" @stop-tracking="$emit('stop-tracking')" @updated="$emit('updated')" />

    <!-- Subtasks Section (Tasks only) -->
    <div v-if="!isSubtask" class="section-card mt-4">
      <div class="subtask-header">
        <div class="d-flex align-center ga-2">
          <v-icon size="18">mdi-file-tree-outline</v-icon>
          <span class="subtask-title">SUBTASKS</span>
          <span v-if="topLevelSubtasks.length" class="subtask-count">
            {{topLevelSubtasks.filter((s) => s.completed_at).length}}/{{
              topLevelSubtasks.length
            }}
          </span>
        </div>
        <v-btn variant="text" size="small" class="board-btn" @click="$emit('view-subtasks', localTask)">
          <v-icon start size="16">mdi-view-dashboard-outline</v-icon>
          BOARD
        </v-btn>
      </div>

      <div v-if="topLevelSubtasks.length" class="subtask-list">
        <SubtaskRow v-for="sub in topLevelSubtasks" :key="sub.id" :item="sub" :can-operate="canOperateTasks"
          @toggle="toggleSubtask(sub)" @open="$emit('open-subtask', sub)" />
      </div>
      <div v-else class="subtask-empty">No subtasks yet</div>
    </div>

    <!-- Checklist Section (subtasks only) -->
    <div v-if="isSubtask" class="section-card mt-4">
      <div class="d-flex align-center justify-space-between pa-3 pb-2">
        <div class="d-flex align-center ga-2">
          <v-icon size="18" color="grey">mdi-format-list-checks</v-icon>
          <span class="text-body-2 font-weight-medium">Checklist</span>
          <v-chip v-if="(localTask.checklist_total ?? 0) > 0" size="x-small" variant="tonal"
            :color="(localTask.progress ?? 0) >= 100 ? 'success' : 'primary'">
            {{ localTask.checklist_checked ?? 0 }}/{{
              localTask.checklist_total ?? 0
            }}
          </v-chip>
        </div>
      </div>

      <!-- Progress bar (only when items exist) -->
      <div v-if="(localTask.checklist_total ?? 0) > 0" class="px-3 pb-1">
        <v-progress-linear :model-value="localTask.progress ?? 0"
          :color="(localTask.progress ?? 0) >= 100 ? 'success' : 'primary'" height="4" rounded />
      </div>

      <v-divider />

      <!-- Checklist items tree -->
      <div v-if="checklistTree.length" class="py-1">
        <ChecklistItemRow v-for="item in checklistTree" :key="item.id" :item="item" :route-params="checklistRouteParams"
          :can-edit="canOperateTasks" :indent-level="0" @reloaded="onChecklistReloaded" />
      </div>
      <div v-else class="pa-3 text-center text-caption text-grey">
        No checklist items yet — add one below
      </div>

      <!-- Add root-level item input -->
      <div v-if="canOperateTasks" class="checklist-add-root pa-3 pt-2">
        <div class="d-flex align-center ga-2">
          <v-icon size="16" color="grey">mdi-plus</v-icon>
          <input v-model="newChecklistName" class="checklist-add-input" placeholder="Add checklist item…"
            @keydown.enter.prevent="addChecklistItem" />
          <v-btn v-if="newChecklistName.trim()" size="x-small" color="primary" variant="flat"
            :loading="checklistAddLoading" @click="addChecklistItem">Add</v-btn>
        </div>
      </div>
    </div>

    <!-- Subtasks Section (subtask only, when depth < MAX) -->
    <div v-if="
      isSubtask &&
      (localTask.can_add_children || (localTask.children ?? []).length > 0)
    " class="section-card mt-4">
      <div class="subtask-header">
        <div class="d-flex align-center ga-2">
          <v-icon size="18">mdi-file-tree-outline</v-icon>
          <span class="subtask-title">SUBTASKS</span>
          <span v-if="(localTask.children ?? []).length" class="subtask-count">
            {{
              (localTask.children ?? []).filter((c) => c.completed_at).length
            }}/{{ (localTask.children ?? []).length }}
          </span>
        </div>
      </div>

      <div v-if="(localTask.children ?? []).length" class="subtask-list">
        <SubtaskRow v-for="child in localTask.children ?? []" :key="child.id" :item="child"
          :can-operate="canOperateTasks" @toggle="toggleChildSubtask(child)" @open="$emit('open-subtask', child)" />
      </div>
      <div v-else class="subtask-empty">No subtasks yet</div>

      <!-- Add child subtask input -->
      <div v-if="canManageTaskStructure && localTask.can_add_children" class="subtask-add">
        <div class="subtask-add-inner">
          <v-icon size="18" color="grey-lighten-1">mdi-plus</v-icon>
          <input v-model="newChildSubtaskName" class="subtask-add-input" placeholder="Add subtask…"
            @keydown.enter.prevent="addChildSubtask" />
          <v-btn v-if="newChildSubtaskName.trim()" size="x-small" color="primary" variant="flat"
            :loading="addChildSubtaskLoading" @click="addChildSubtask">Add</v-btn>
        </div>
      </div>
    </div>

    <!-- Description Section -->
    <TaskDescriptionSection v-model="editedDescription" :can-edit="canOperateTasks" class="mt-4"
      @save="saveDescription" />
  </div>
</template>

<style scoped>
.section-card {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 10px;
  overflow: hidden;
}

/* ClickUp-style Subtask List */
.subtask-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px 8px;
}

.subtask-title {
  font-size: 12px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.55);
  letter-spacing: 0.5px;
}

.subtask-count {
  font-size: 11px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.45);
  background: rgba(255, 255, 255, 0.07);
  padding: 1px 7px;
  border-radius: 10px;
  margin-left: 2px;
}

.board-btn {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.4px;
  color: rgba(255, 255, 255, 0.45) !important;
}

.board-btn:hover {
  color: rgba(255, 255, 255, 0.8) !important;
}

.subtask-list {
  display: flex;
  flex-direction: column;
}

.subtask-empty {
  padding: 24px 16px;
  text-align: center;
  color: rgba(255, 255, 255, 0.3);
  font-size: 13px;
}

.subtask-add {
  border-top: 1px solid rgba(255, 255, 255, 0.04);
  padding: 9px 16px;
}

.subtask-add-inner {
  display: flex;
  align-items: center;
  gap: 10px;
}

.subtask-add-input {
  flex: 1;
  min-width: 0;
  background: transparent;
  border: none;
  padding: 4px 0;
  font-size: 14px;
  color: rgba(255, 255, 255, 0.85);
  outline: none;
}

.subtask-add-input::placeholder {
  color: rgba(255, 255, 255, 0.3);
}

.checklist-add-root {
  border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.checklist-add-input {
  flex: 1;
  min-width: 0;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  padding: 4px 8px;
  font-size: 13px;
  color: rgba(255, 255, 255, 0.85);
  outline: none;
}

.checklist-add-input:focus {
  border-color: rgba(99, 102, 241, 0.5);
  background: rgba(255, 255, 255, 0.06);
}

.checklist-add-input::placeholder {
  color: rgba(255, 255, 255, 0.3);
}
</style>
