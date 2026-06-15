import { ref } from 'vue';

const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

export function useSnackbar() {
    const showSnackbar = (message, color = 'success') => {
        snackbarText.value = message;
        snackbarColor.value = color;
        snackbar.value = true;
    };

    return {
        snackbar,
        snackbarText,
        snackbarColor,
        showSnackbar,
    };
}
