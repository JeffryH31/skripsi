<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
        default: true,
    },
    status: {
        type: String,
        default: '',
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const isPasswordVisible = ref(false);

const emailRules = [
    (v) => !!v || 'Email is required',
    (v) => /.+@.+\..+/.test(v) || 'Please enter a valid email',
];

const passwordRules = [
    (v) => !!v || 'Password is required',
    (v) => v.length >= 6 || 'Password must be at least 6 characters',
];

const handleSubmit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>

    <Head title="Sign In" />

    <v-app theme="darkTheme">
        <v-main class="d-flex align-center justify-center" style="min-height: 100vh;">
            <v-container class="pa-4" fluid>
                <v-row align="center" justify="center">
                    <v-col cols="12" sm="10" md="6" lg="5" xl="4">
                        <!-- Login Card -->
                        <v-card class="mx-auto" max-width="440" rounded="xl" elevation="12" color="surface">
                            <v-card-text class="pa-8 pa-sm-10">
                                <!-- Header -->
                                <div class="text-center mb-8">
                                    <v-icon color="primary" size="48" class="mb-4">mdi-view-dashboard-outline</v-icon>
                                    <h1 class="text-h4 font-weight-bold mb-2">
                                        Sign In
                                    </h1>
                                    <p class="text-body-1 text-medium-emphasis">
                                        Enter your credentials to continue
                                    </p>
                                </div>

                                <!-- Status Alert -->
                                <v-alert v-if="status" type="success" variant="tonal" class="mb-6" rounded="lg"
                                    closable>
                                    {{ status }}
                                </v-alert>

                                <!-- Login Form -->
                                <v-form @submit.prevent="handleSubmit" validate-on="blur">
                                    <!-- Email Field -->
                                    <v-text-field v-model="form.email" label="Email Address" type="email" name="email"
                                        autocomplete="email" prepend-inner-icon="mdi-email-outline"
                                        placeholder="name@example.com" variant="outlined" density="comfortable"
                                        rounded="lg" :rules="emailRules" :disabled="form.processing" autofocus class="mb-4"
                                        color="primary" bg-color="surface-variant" 
                                        :error-messages="form.errors.email" />

                                    <!-- Password Field -->
                                    <v-text-field v-model="form.password" label="Password"
                                        :type="isPasswordVisible ? 'text' : 'password'" name="password"
                                        autocomplete="current-password" prepend-inner-icon="mdi-lock-outline"
                                        :append-inner-icon="isPasswordVisible ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                                        placeholder="Enter your password" variant="outlined" density="comfortable"
                                        rounded="lg" :rules="passwordRules" :disabled="form.processing"
                                        @click:append-inner="isPasswordVisible = !isPasswordVisible" class="mb-4"
                                        color="primary" bg-color="surface-variant" 
                                        :error-messages="form.errors.password" />

                                    <!-- Remember Me -->
                                    <v-checkbox v-model="form.remember" label="Remember me" color="primary" 
                                        class="mb-4" hide-details />

                                    <!-- Submit Button -->
                                    <v-btn type="submit" color="primary" size="x-large" block rounded="lg"
                                        :loading="form.processing" :disabled="form.processing"
                                        class="mb-6 text-none font-weight-medium" elevation="2">
                                        Sign In
                                    </v-btn>
                                </v-form>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </v-container>
        </v-main>
    </v-app>
</template>

<style scoped>
/* Button letter spacing */
:deep(.v-btn) {
    letter-spacing: normal !important;
}

:deep(.v-btn--loading .v-btn__content) {
    opacity: 0;
}
</style>
