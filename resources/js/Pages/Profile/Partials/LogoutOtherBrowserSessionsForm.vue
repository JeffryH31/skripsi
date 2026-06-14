<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

defineProps({
    sessions: Array,
});

const confirmingLogout = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmLogout = () => {
    confirmingLogout.value = true;

    setTimeout(() => passwordInput.value.focus(), 250);
};

const logoutOtherBrowserSessions = () => {
    form.delete(route('other-browser-sessions.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingLogout.value = false;

    form.reset();
};
</script>

<template>
    <v-card variant="outlined" rounded="xl" class="profile-section-card">
        <v-card-title class="d-flex align-center ga-2">
            <v-icon color="primary">mdi-laptop-account</v-icon>
            Browser Sessions
        </v-card-title>
        <v-card-subtitle>
            Review and sign out from other devices.
        </v-card-subtitle>
        <v-divider class="mt-3" />

        <v-card-text class="pt-6">
            <v-alert type="info" variant="tonal" border="start" class="mb-4">
                If your account was used on another device, you can force sign-out from all other active sessions.
            </v-alert>

            <v-list v-if="sessions.length > 0" lines="two" class="rounded-lg session-list pa-0 mb-4">
                <v-list-item v-for="(session, i) in sessions" :key="i" class="px-4 py-3">
                    <template #prepend>
                        <v-avatar size="36" color="surface-variant">
                            <v-icon>
                                {{ session.agent.is_desktop ? 'mdi-monitor' : 'mdi-cellphone' }}
                            </v-icon>
                        </v-avatar>
                    </template>

                    <v-list-item-title>
                        {{ session.agent.platform || 'Unknown platform' }} - {{ session.agent.browser || 'Unknown browser' }}
                    </v-list-item-title>
                    <v-list-item-subtitle>
                        {{ session.ip_address }}
                        <span v-if="session.is_current_device" class="text-success"> - This device</span>
                        <span v-else> - Last active {{ session.last_active }}</span>
                    </v-list-item-subtitle>
                </v-list-item>
            </v-list>

            <div class="d-flex align-center ga-3 flex-wrap">
                <v-btn color="primary" @click="confirmLogout">
                    Log Out Other Sessions
                </v-btn>
                <v-chip v-if="form.recentlySuccessful" color="success" variant="tonal" size="small">
                    Done
                </v-chip>
            </div>
        </v-card-text>
    </v-card>

    <v-dialog v-model="confirmingLogout" max-width="520">
        <v-card>
            <v-card-title>Confirm Session Logout</v-card-title>
            <v-card-text>
                Enter your password to sign out from all other browser sessions.
                <v-text-field
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    variant="outlined"
                    label="Password"
                    autocomplete="current-password"
                    class="mt-4"
                    :error-messages="form.errors.password"
                    @keyup.enter="logoutOtherBrowserSessions"
                />
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn variant="text" @click="closeModal">Cancel</v-btn>
                <v-btn color="primary" :loading="form.processing" :disabled="form.processing" @click="logoutOtherBrowserSessions">
                    Confirm Logout
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.profile-section-card {
    border-color: rgba(148, 163, 184, 0.35);
}

.session-list {
    background: rgba(148, 163, 184, 0.1);
}
</style>
