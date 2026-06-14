<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useSnackbar } from '@/composables/useSnackbar';

const { showSnackbar } = useSnackbar();

const props = defineProps({
    comments: { type: Array, default: () => [] },
    isSubtask: { type: Boolean, default: false },
    workspace: { type: Object, default: null },
    space: { type: Object, default: null },
    list: { type: Object, default: null },
    mainTaskId: { type: [Number, String], default: null },   // parent task ID (for comments route)
    subtaskId: { type: [Number, String], default: null },    // subtask ID (if isSubtask)
});

const emit = defineEmits(['updated']);

const newComment = ref('');
const isSubmitting = ref(false);

const submitComment = () => {
    if (!newComment.value.trim() || isSubmitting.value) return;
    isSubmitting.value = true;

    const commentData = { content: newComment.value };
    if (props.isSubtask && props.subtaskId) {
        commentData.subtask_id = props.subtaskId;
    }

    router.post(
        route('tasks.comments.store', [props.workspace.id, props.space.id, props.list.id, props.mainTaskId]),
        commentData,
        {
            preserveScroll: true,
            onSuccess: () => {
                newComment.value = '';
                emit('updated');
            },
            onError: () => {
                showSnackbar('Failed to add comment', 'error');
            },
            onFinish: () => { isSubmitting.value = false; },
        }
    );
};
</script>

<template>
    <div class="pa-5">
        <!-- Comment Input -->
        <div class="section-card mb-4">
            <div class="pa-3">
                <v-textarea v-model="newComment" placeholder="Write a comment..." variant="outlined" rows="3"
                    hide-details auto-grow :disabled="isSubmitting" />
                <div class="d-flex justify-end mt-2">
                    <v-btn color="primary" size="small" variant="flat" :disabled="!newComment.trim() || isSubmitting"
                        :loading="isSubmitting" @click="submitComment">
                        <v-icon start size="14">mdi-send</v-icon>
                        Comment
                    </v-btn>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!comments.length" class="d-flex flex-column align-center py-10 text-grey">
            <v-icon size="48" class="mb-2" color="grey-darken-1">mdi-comment-outline</v-icon>
            <div class="text-body-2">No comments yet</div>
            <div class="text-caption">Be the first to leave a comment</div>
        </div>

        <!-- Comments list -->
        <div v-else class="comment-list">
            <div v-for="comment in comments" :key="comment.id" class="comment-item">
                <v-avatar :color="comment.user?.avatar_color" size="32">
                    <span class="text-xs font-weight-medium">{{ comment.user?.initials }}</span>
                </v-avatar>
                <div class="flex-1 min-w-0">
                    <div class="d-flex align-center ga-2 mb-1">
                        <span class="text-body-2 font-weight-medium">{{ comment.user?.name }}</span>
                        <span class="text-caption text-grey">
                            {{ new Date(comment.created_at).toLocaleDateString('en-US', {
                                month: 'short', day:
                                    'numeric', year: 'numeric'
                            }) }} at
                            {{ new Date(comment.created_at).toLocaleTimeString('en-US', {
                                hour: '2-digit', minute:
                                    '2-digit'
                            }) }}
                        </span>
                    </div>
                    <div class="text-body-2 comment-content">{{ comment.content }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.section-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 10px;
    overflow: hidden;
}

.comment-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.comment-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.comment-content {
    white-space: pre-wrap;
    word-break: break-word;
    line-height: 1.5;
}
</style>
