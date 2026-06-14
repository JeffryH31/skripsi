<script setup>
/**
 * MemberModal Component
 * 
 * Modal for managing board members
 */
import { ref, watch } from 'vue';
import { useDisplay } from 'vuetify';

const { smAndDown } = useDisplay();

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    members: {
        type: Array,
        default: () => []
    },
    allUsers: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['update:modelValue', 'add-member', 'remove-member']);

const searchQuery = ref('');

const availableUsers = ref([]);

watch(() => props.modelValue, (isOpen) => {
    if (isOpen) {
        searchQuery.value = '';
        // Filter out users already in the board
        const memberIds = props.members.map(m => m.id);
        availableUsers.value = props.allUsers.filter(u => !memberIds.includes(u.id));
    }
});

const filteredAvailableUsers = () => {
    if (!searchQuery.value.trim()) return availableUsers.value;
    const query = searchQuery.value.toLowerCase();
    return availableUsers.value.filter(u => 
        u.name.toLowerCase().includes(query) || 
        u.email.toLowerCase().includes(query)
    );
};

const handleAddMember = (user) => {
    emit('add-member', user);
    // Remove from available users
    availableUsers.value = availableUsers.value.filter(u => u.id !== user.id);
};

const handleRemoveMember = (member) => {
    emit('remove-member', member);
    // Add back to available users
    availableUsers.value.push(member);
};

const handleClose = () => {
    emit('update:modelValue', false);
};
</script>

<template>
    <v-dialog 
        :model-value="modelValue" 
        @update:model-value="$emit('update:modelValue', $event)" 
        :max-width="smAndDown ? undefined : 500"
        :fullscreen="smAndDown"
        :transition="smAndDown ? 'dialog-bottom-transition' : 'dialog-transition'"
        scrollable
    >
        <v-card color="surface">
            <v-card-title class="d-flex align-center pa-4">
                <v-icon start>mdi-account-multiple-plus</v-icon>
                Manage Board Members
                <v-spacer />
                <v-btn icon variant="text" size="small" @click="handleClose">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-4">
                <!-- Current Members -->
                <div class="text-subtitle-2 text-medium-emphasis mb-2">
                    Current Members ({{ members.length }})
                </div>
                <div class="member-list mb-4">
                    <v-chip
                        v-for="member in members"
                        :key="member.id"
                        class="mr-2 mb-2"
                        closable
                        @click:close="handleRemoveMember(member)"
                    >
                        <v-avatar start size="24" :color="member.avatarColor || 'primary'">
                            <span class="text-caption">{{ member.name?.charAt(0).toUpperCase() }}</span>
                        </v-avatar>
                        {{ member.name }}
                    </v-chip>
                    <p v-if="members.length === 0" class="text-body-2 text-medium-emphasis">
                        No members added yet
                    </p>
                </div>

                <v-divider class="my-4" />

                <!-- Add Members -->
                <div class="text-subtitle-2 text-medium-emphasis mb-2">
                    Add Members
                </div>
                <v-text-field
                    v-model="searchQuery"
                    placeholder="Search users by name or email..."
                    variant="outlined"
                    bg-color="surface-variant"
                    density="compact"
                    prepend-inner-icon="mdi-magnify"
                    hide-details
                    class="mb-3"
                />

                <v-list density="compact" max-height="200" class="overflow-y-auto bg-transparent">
                    <v-list-item
                        v-for="user in filteredAvailableUsers()"
                        :key="user.id"
                        @click="handleAddMember(user)"
                    >
                        <template #prepend>
                            <v-avatar size="32" :color="user.avatarColor || 'primary'">
                                <span class="text-caption">{{ user.name?.charAt(0).toUpperCase() }}</span>
                            </v-avatar>
                        </template>
                        <v-list-item-title>{{ user.name }}</v-list-item-title>
                        <v-list-item-subtitle>{{ user.email }}</v-list-item-subtitle>
                        <template #append>
                            <v-btn icon variant="text" size="small" color="primary">
                                <v-icon>mdi-plus</v-icon>
                            </v-btn>
                        </template>
                    </v-list-item>
                    <v-list-item v-if="filteredAvailableUsers().length === 0">
                        <v-list-item-title class="text-medium-emphasis text-center">
                            {{ searchQuery ? 'No users found' : 'No available users to add' }}
                        </v-list-item-title>
                    </v-list-item>
                </v-list>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>
