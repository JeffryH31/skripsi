<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    setTimeout(() => passwordInput.value.focus(), 250);
};

const deleteUser = () => {
    form.delete(route('current-user.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.reset();
};
</script>

<template>
    <v-card variant="outlined" rounded="xl" class="profile-danger-card">
        <v-card-title class="d-flex align-center ga-2 text-error">
            <v-icon color="error">mdi-alert-circle-outline</v-icon>
            Delete Account
        </v-card-title>
        <v-card-subtitle>
            Permanently remove your account and all related data.
        </v-card-subtitle>
        <v-divider class="mt-3" />

        <v-card-text class="pt-6">
            <v-alert type="error" variant="tonal" border="start" class="mb-4">
                This action is irreversible. All your workspaces, tasks, comments, and activity history tied to this account will be deleted.
            </v-alert>

            <v-btn color="error" variant="tonal" @click="confirmUserDeletion">
                Delete My Account
            </v-btn>
        </v-card-text>
    </v-card>

    <v-dialog v-model="confirmingUserDeletion" max-width="540">
        <v-card>
            <v-card-title class="text-error">Confirm Account Deletion</v-card-title>
            <v-card-text>
                Enter your password to permanently delete your account.
                <v-text-field
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    label="Password"
                    variant="outlined"
                    autocomplete="current-password"
                    class="mt-4"
                    :error-messages="form.errors.password"
                    @keyup.enter="deleteUser"
                />
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn variant="text" @click="closeModal">Cancel</v-btn>
                <v-btn color="error" :loading="form.processing" :disabled="form.processing" @click="deleteUser">
                    Delete Permanently
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.profile-danger-card {
    border-color: rgba(239, 68, 68, 0.4);
}
</style>
