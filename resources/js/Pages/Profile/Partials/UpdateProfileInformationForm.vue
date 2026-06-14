<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: Object,
});

const form = useForm({
    _method: 'PUT',
    name: props.user.name,
    email: props.user.email,
    photo: null,
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);

const updateProfileInformation = () => {
    if (photoInput.value) {
        form.photo = photoInput.value.files[0];
    }

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => clearPhotoFileInput(),
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];

    if (! photo) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };

    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};
</script>

<template>
    <v-card variant="outlined" rounded="xl" class="profile-section-card">
        <v-card-title class="d-flex align-center ga-2">
            <v-icon color="primary">mdi-account-circle-outline</v-icon>
            Profile Information
        </v-card-title>
        <v-card-subtitle>
            Update your account details and email address.
        </v-card-subtitle>
        <v-divider class="mt-3" />

        <v-form @submit.prevent="updateProfileInformation">
            <v-card-text class="pt-6">
                <input
                    id="photo"
                    ref="photoInput"
                    type="file"
                    class="hidden"
                    accept="image/*"
                    @change="updatePhotoPreview"
                >

                <div v-if="$page.props.jetstream.managesProfilePhotos" class="mb-6">
                    <div class="text-subtitle-2 mb-3">Profile Photo</div>
                    <div class="d-flex align-center ga-4 flex-wrap">
                        <v-avatar size="84" :color="user.avatar_color || 'primary'">
                            <img
                                v-if="photoPreview || user.profile_photo_url"
                                :src="photoPreview || user.profile_photo_url"
                                :alt="user.name"
                            >
                            <span v-else class="text-h6">{{ user.initials }}</span>
                        </v-avatar>

                        <div class="d-flex ga-2 flex-wrap">
                            <v-btn color="primary" variant="tonal" @click.prevent="selectNewPhoto">
                                <v-icon start>mdi-image-edit-outline</v-icon>
                                Change Photo
                            </v-btn>
                            <v-btn
                                v-if="user.profile_photo_path"
                                color="error"
                                variant="text"
                                @click.prevent="deletePhoto"
                            >
                                Remove
                            </v-btn>
                        </div>
                    </div>
                    <v-alert v-if="form.errors.photo" type="error" variant="tonal" density="compact" class="mt-3">
                        {{ form.errors.photo }}
                    </v-alert>
                </div>

                <v-row>
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.name"
                            label="Full Name"
                            variant="outlined"
                            autocomplete="name"
                            :error-messages="form.errors.name"
                            required
                        />
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.email"
                            label="Email"
                            type="email"
                            variant="outlined"
                            autocomplete="username"
                            :error-messages="form.errors.email"
                            required
                        />
                    </v-col>
                </v-row>

                <v-alert
                    v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null"
                    type="warning"
                    variant="tonal"
                    border="start"
                    class="mt-2"
                >
                    Your email is not verified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="verify-link"
                        @click.prevent="sendEmailVerification"
                    >
                        Resend verification email
                    </Link>
                    <div v-if="verificationLinkSent" class="mt-2 text-success">Verification link sent.</div>
                </v-alert>
            </v-card-text>

            <v-divider />
            <v-card-actions class="pa-4">
                <v-chip v-if="form.recentlySuccessful" color="success" variant="tonal" size="small">
                    Saved
                </v-chip>
                <v-spacer />
                <v-btn type="submit" color="primary" :loading="form.processing" :disabled="form.processing">
                    Save Changes
                </v-btn>
            </v-card-actions>
        </v-form>
    </v-card>
</template>

<style scoped>
.profile-section-card {
    border-color: rgba(148, 163, 184, 0.35);
}

.verify-link {
    margin-left: 6px;
    text-decoration: underline;
    color: inherit;
}
</style>
