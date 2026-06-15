/**
 * Notification Composable
 * 
 * Handles snackbar notifications
 */
import { ref } from 'vue';

export function useNotification() {
    const snackbar = ref({ show: false, text: '', color: 'success' });

    const showNotification = (text, color = 'success') => {
        snackbar.value = { show: true, text, color };
    };

    const hideNotification = () => {
        snackbar.value.show = false;
    };

    return {
        snackbar,
        showNotification,
        hideNotification
    };
}
