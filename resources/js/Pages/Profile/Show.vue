<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});
</script>

<template>
    <MainLayout title="Profile Settings">
        <div class="profile-page">
            <v-card variant="outlined" rounded="xl" class="profile-hero">
                <v-card-text class="d-flex flex-wrap align-center ga-4 pa-6">
                    <v-avatar size="72" :color="page.props.auth.user.avatar_color || 'primary'">
                        <img
                            v-if="page.props.auth.user.profile_photo_url"
                            :src="page.props.auth.user.profile_photo_url"
                            :alt="page.props.auth.user.name"
                        >
                        <span v-else class="text-h6">{{ page.props.auth.user.initials }}</span>
                    </v-avatar>

                    <div class="flex-grow-1 min-w-0">
                        <div class="text-h5 font-weight-bold text-truncate">{{ page.props.auth.user.name }}</div>
                        <div class="text-body-2 text-medium-emphasis text-truncate">{{ page.props.auth.user.email }}</div>
                        <div class="d-flex align-center ga-2 mt-2 flex-wrap">
                            <v-chip
                                size="small"
                                :color="page.props.auth.user.email_verified_at ? 'success' : 'warning'"
                                variant="tonal"
                            >
                                {{ page.props.auth.user.email_verified_at ? 'Email Verified' : 'Email Unverified' }}
                            </v-chip>
                            <v-chip size="small" color="primary" variant="tonal">
                                Account Settings
                            </v-chip>
                        </div>
                    </div>
                </v-card-text>
            </v-card>

            <div class="profile-sections mt-6">
                <UpdateProfileInformationForm
                    v-if="$page.props.jetstream.canUpdateProfileInformation"
                    :user="$page.props.auth.user"
                />

                <UpdatePasswordForm v-if="$page.props.jetstream.canUpdatePassword" />

                <!-- <TwoFactorAuthenticationForm
                    v-if="$page.props.jetstream.canManageTwoFactorAuthentication"
                    :requires-confirmation="confirmsTwoFactorAuthentication"
                /> -->

                <!-- <LogoutOtherBrowserSessionsForm :sessions="sessions" /> -->

                <DeleteUserForm v-if="$page.props.jetstream.hasAccountDeletionFeatures" />
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.profile-page {
    max-width: 960px;
    margin: 0 auto;
    padding: 24px;
}

.profile-hero {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(16, 185, 129, 0.08));
    border-color: rgba(148, 163, 184, 0.35);
}

.profile-sections {
    display: grid;
    gap: 20px;
}

@media (max-width: 768px) {
    .profile-page {
        padding: 16px;
    }
}
</style>
