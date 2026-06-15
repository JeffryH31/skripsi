import { ref } from 'vue';

const isOpen = ref(false);
const dialogTitle = ref('');
const dialogMessage = ref('');
const dialogColor = ref('error');
let resolvePromise = null;

export function useConfirmDialog() {
    const confirm = (message, title = 'Confirm', color = 'warning') => {
        dialogTitle.value = title;
        dialogMessage.value = message;
        dialogColor.value = color;
        isOpen.value = true;

        return new Promise((resolve) => {
            resolvePromise = resolve;
        });
    };

    const onConfirm = () => {
        isOpen.value = false;
        if (resolvePromise) resolvePromise(true);
    };

    const onCancel = () => {
        isOpen.value = false;
        if (resolvePromise) resolvePromise(false);
    };

    return {
        isOpen,
        dialogTitle,
        dialogMessage,
        dialogColor,
        confirm,
        onConfirm,
        onCancel,
    };
}
