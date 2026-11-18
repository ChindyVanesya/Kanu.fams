<script setup>
import { useFeedback } from '../lib/feedback';

const { notification, confirmation, dismissNotification, resolveConfirmation } = useFeedback();

const notificationStyles = {
    success: 'border-emerald-200 bg-white text-emerald-950',
    error: 'border-rose-200 bg-white text-rose-950',
    info: 'border-slate-200 bg-white text-slate-900',
};
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed inset-x-0 top-4 z-[100] flex justify-center px-4 sm:justify-end sm:px-6" aria-live="polite" aria-atomic="true">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="-translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="-translate-y-2 opacity-0"
            >
                <div v-if="notification" :key="notification.id" class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-2xl border px-4 py-3.5 shadow-lg shadow-slate-900/10" :class="notificationStyles[notification.type] ?? notificationStyles.info" role="status">
                    <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full text-sm font-bold" :class="notification.type === 'success' ? 'bg-emerald-100 text-emerald-800' : notification.type === 'error' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700'">
                        {{ notification.type === 'success' ? '✓' : notification.type === 'error' ? '!' : 'i' }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">{{ notification.type === 'success' ? 'Berhasil' : notification.type === 'error' ? 'Gagal' : 'Informasi' }}</p>
                        <p class="mt-0.5 text-sm leading-5 text-slate-600">{{ notification.message }}</p>
                    </div>
                    <button type="button" class="rounded-md px-1 text-lg leading-5 text-slate-400 hover:text-slate-700" aria-label="Tutup notifikasi" @click="dismissNotification">×</button>
                </div>
            </Transition>
        </div>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="confirmation" class="fixed inset-0 z-[110] grid place-items-center bg-slate-950/45 p-4 backdrop-blur-[2px]" role="presentation" @click.self="resolveConfirmation(false)" @keydown.esc="resolveConfirmation(false)">
                <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl shadow-slate-950/20 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="confirmation-title" aria-describedby="confirmation-message">
                    <h2 id="confirmation-title" class="text-lg font-semibold text-slate-900">{{ confirmation.title }}</h2>
                    <p id="confirmation-message" class="mt-2 text-sm leading-6 text-slate-600">{{ confirmation.message }}</p>
                    <div class="mt-6 flex justify-end gap-2.5">
                        <button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" @click="resolveConfirmation(false)">{{ confirmation.cancelLabel }}</button>
                        <button type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition" :class="confirmation.variant === 'danger' ? 'bg-rose-700 hover:bg-rose-800' : 'bg-slate-900 hover:bg-slate-800'" @click="resolveConfirmation(true)">{{ confirmation.confirmLabel }}</button>
                    </div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>
