<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import ConfirmsPassword from '@/Components/ConfirmsPassword.vue';

const props = defineProps({
    requiresConfirmation: Boolean,
});

const page = usePage();
const enabling = ref(false);
const confirming = ref(false);
const disabling = ref(false);
const qrCode = ref(null);
const setupKey = ref(null);
const recoveryCodes = ref([]);

const confirmationForm = useForm({
    code: '',
});

const twoFactorEnabled = computed(
    () => ! enabling.value && page.props.auth.user?.two_factor_enabled,
);

watch(twoFactorEnabled, () => {
    if (! twoFactorEnabled.value) {
        confirmationForm.reset();
        confirmationForm.clearErrors();
    }
});

const enableTwoFactorAuthentication = () => {
    enabling.value = true;

    router.post(route('two-factor.enable'), {}, {
        preserveScroll: true,
        onSuccess: () => Promise.all([
            showQrCode(),
            showSetupKey(),
            showRecoveryCodes(),
        ]),
        onFinish: () => {
            enabling.value = false;
            confirming.value = props.requiresConfirmation;
        },
    });
};

const showQrCode = () => {
    return axios.get(route('two-factor.qr-code')).then(response => {
        qrCode.value = response.data.svg;
    });
};

const showSetupKey = () => {
    return axios.get(route('two-factor.secret-key')).then(response => {
        setupKey.value = response.data.secretKey;
    });
}

const showRecoveryCodes = () => {
    return axios.get(route('two-factor.recovery-codes')).then(response => {
        recoveryCodes.value = response.data;
    });
};

const confirmTwoFactorAuthentication = () => {
    confirmationForm.post(route('two-factor.confirm'), {
        errorBag: "confirmTwoFactorAuthentication",
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            confirming.value = false;
            qrCode.value = null;
            setupKey.value = null;
        },
    });
};

const regenerateRecoveryCodes = () => {
    axios
        .post(route('two-factor.recovery-codes'))
        .then(() => showRecoveryCodes());
};

const disableTwoFactorAuthentication = () => {
    disabling.value = true;

    router.delete(route('two-factor.disable'), {
        preserveScroll: true,
        onSuccess: () => {
            disabling.value = false;
            confirming.value = false;
        },
    });
};
</script>

<template>
    <v-card variant="outlined" rounded="xl" class="profile-section-card">
        <v-card-title class="d-flex align-center ga-2">
            <v-icon color="primary">mdi-shield-lock-outline</v-icon>
            Two-Factor Authentication
        </v-card-title>
        <v-card-subtitle>
            Add an extra security layer to your account.
        </v-card-subtitle>
        <v-divider class="mt-3" />

        <v-card-text class="pt-6">
            <v-alert
                :type="twoFactorEnabled ? 'success' : 'info'"
                variant="tonal"
                border="start"
                class="mb-4"
            >
                <strong v-if="twoFactorEnabled && !confirming">Two-factor authentication is enabled.</strong>
                <strong v-else-if="twoFactorEnabled && confirming">Finish enabling two-factor authentication.</strong>
                <strong v-else>Two-factor authentication is disabled.</strong>
                <div class="mt-2">
                    Use an authenticator app to generate one-time passcodes for login.
                </div>
            </v-alert>

            <div v-if="twoFactorEnabled && qrCode" class="mb-4">
                <div class="text-body-2 text-medium-emphasis mb-3">
                    <span v-if="confirming">Scan the QR code, then enter your generated OTP code below.</span>
                    <span v-else>Scan this QR code with your authenticator app.</span>
                </div>

                <div class="two-factor-qr-wrapper mb-3" v-html="qrCode" />

                <v-alert v-if="setupKey" type="info" variant="tonal" density="compact" class="mb-3">
                    Setup Key: <strong v-html="setupKey"></strong>
                </v-alert>

                <v-text-field
                    v-if="confirming"
                    v-model="confirmationForm.code"
                    label="Authentication Code"
                    variant="outlined"
                    maxlength="6"
                    autocomplete="one-time-code"
                    :error-messages="confirmationForm.errors.code"
                    @keyup.enter="confirmTwoFactorAuthentication"
                />
            </div>

            <div v-if="recoveryCodes.length > 0 && !confirming" class="mb-4">
                <div class="text-body-2 text-medium-emphasis mb-2">
                    Save these recovery codes securely. They help you regain access if your authenticator is unavailable.
                </div>
                <div class="recovery-codes-grid">
                    <div v-for="code in recoveryCodes" :key="code" class="recovery-code-item">
                        {{ code }}
                    </div>
                </div>
            </div>

            <div class="d-flex ga-2 flex-wrap">
                <ConfirmsPassword v-if="!twoFactorEnabled" @confirmed="enableTwoFactorAuthentication">
                    <v-btn type="button" color="primary" :loading="enabling" :disabled="enabling">
                        Enable
                    </v-btn>
                </ConfirmsPassword>

                <template v-else>
                    <ConfirmsPassword @confirmed="confirmTwoFactorAuthentication">
                        <v-btn
                            v-if="confirming"
                            type="button"
                            color="primary"
                            :loading="confirmationForm.processing"
                            :disabled="enabling || confirmationForm.processing"
                        >
                            Confirm
                        </v-btn>
                    </ConfirmsPassword>

                    <ConfirmsPassword @confirmed="regenerateRecoveryCodes">
                        <v-btn
                            v-if="recoveryCodes.length > 0 && !confirming"
                            type="button"
                            color="secondary"
                            variant="tonal"
                        >
                            Regenerate Recovery Codes
                        </v-btn>
                    </ConfirmsPassword>

                    <ConfirmsPassword @confirmed="showRecoveryCodes">
                        <v-btn
                            v-if="recoveryCodes.length === 0 && !confirming"
                            type="button"
                            color="secondary"
                            variant="tonal"
                        >
                            Show Recovery Codes
                        </v-btn>
                    </ConfirmsPassword>

                    <ConfirmsPassword @confirmed="disableTwoFactorAuthentication">
                        <v-btn
                            v-if="confirming"
                            type="button"
                            color="secondary"
                            variant="text"
                            :loading="disabling"
                            :disabled="disabling"
                        >
                            Cancel
                        </v-btn>
                    </ConfirmsPassword>

                    <ConfirmsPassword @confirmed="disableTwoFactorAuthentication">
                        <v-btn
                            v-if="!confirming"
                            type="button"
                            color="error"
                            variant="tonal"
                            :loading="disabling"
                            :disabled="disabling"
                        >
                            Disable
                        </v-btn>
                    </ConfirmsPassword>
                </template>
            </div>
        </v-card-text>
    </v-card>
</template>

<style scoped>
.profile-section-card {
    border-color: rgba(148, 163, 184, 0.35);
}

.two-factor-qr-wrapper {
    display: inline-block;
    padding: 10px;
    border-radius: 12px;
    background: white;
}

.recovery-codes-grid {
    display: grid;
    gap: 6px;
    padding: 12px;
    border-radius: 12px;
    background: rgba(148, 163, 184, 0.12);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, Courier New, monospace;
    font-size: 0.85rem;
}

.recovery-code-item {
    word-break: break-all;
}
</style>
