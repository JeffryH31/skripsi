<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('user-password.update'), {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }

            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <v-card variant="outlined" rounded="xl" class="profile-section-card">
        <v-card-title class="d-flex align-center ga-2">
            <v-icon color="primary">mdi-lock-reset</v-icon>
            Update Password
        </v-card-title>
        <v-card-subtitle>
            Use a strong password to secure your account.
        </v-card-subtitle>
        <v-divider class="mt-3" />

        <v-form @submit.prevent="updatePassword">
            <v-card-text class="pt-6">
                <v-text-field
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    label="Current Password"
                    type="password"
                    variant="outlined"
                    autocomplete="current-password"
                    :error-messages="form.errors.current_password"
                    class="mb-3"
                />

                <v-text-field
                    ref="passwordInput"
                    v-model="form.password"
                    label="New Password"
                    type="password"
                    variant="outlined"
                    autocomplete="new-password"
                    :error-messages="form.errors.password"
                    class="mb-3"
                />

                <v-text-field
                    v-model="form.password_confirmation"
                    label="Confirm Password"
                    type="password"
                    variant="outlined"
                    autocomplete="new-password"
                    :error-messages="form.errors.password_confirmation"
                />
            </v-card-text>

            <v-divider />
            <v-card-actions class="pa-4">
                <v-chip v-if="form.recentlySuccessful" color="success" variant="tonal" size="small">
                    Password updated
                </v-chip>
                <v-spacer />
                <v-btn type="submit" color="primary" :loading="form.processing" :disabled="form.processing">
                    Save Password
                </v-btn>
            </v-card-actions>
        </v-form>
    </v-card>
</template>

<style scoped>
.profile-section-card {
    border-color: rgba(148, 163, 184, 0.35);
}
</style>
