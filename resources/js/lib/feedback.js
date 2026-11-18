import { ref } from 'vue';

const notification = ref(null);
const confirmation = ref(null);
let notificationTimer;
let confirmationResolver;
let notificationId = 0;

export function useFeedback() {
    function notify(type, message) {
        if (notificationTimer) clearTimeout(notificationTimer);

        const id = ++notificationId;
        notification.value = { id, type, message };
        notificationTimer = setTimeout(() => {
            if (notification.value?.id === id) notification.value = null;
        }, 4500);
    }

    function confirmAction(options) {
        if (confirmationResolver) confirmationResolver(false);

        confirmation.value = {
            title: options.title ?? 'Konfirmasi tindakan',
            message: options.message ?? 'Apakah Anda yakin ingin melanjutkan?',
            confirmLabel: options.confirmLabel ?? 'Lanjutkan',
            cancelLabel: options.cancelLabel ?? 'Batal',
            variant: options.variant ?? 'primary',
        };

        return new Promise((resolve) => {
            confirmationResolver = resolve;
        });
    }

    function dismissNotification() {
        if (notificationTimer) clearTimeout(notificationTimer);
        notification.value = null;
    }

    function resolveConfirmation(accepted) {
        confirmation.value = null;

        const resolve = confirmationResolver;
        confirmationResolver = null;
        resolve?.(accepted);
    }

    return { notification, confirmation, notify, dismissNotification, confirmAction, resolveConfirmation };
}
