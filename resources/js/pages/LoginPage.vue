<script setup>
import { ref } from "vue";
import { api } from "../lib/api";
import { useFeedback } from "../lib/feedback";

const { notify } = useFeedback();

const email = ref("");
const password = ref("");
const loading = ref(false);

async function login() {
    loading.value = true;
    try {
        const data = await api("/admin/login", {
            method: "POST",
            body: { email: email.value, password: password.value },
        });
        window.location.assign(data.redirect);
    } catch (requestError) {
        notify("error", requestError.message);
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <main
        class="flex min-h-screen items-center justify-center bg-slate-950 px-4 py-10 text-slate-100"
    >
        <section
            class="w-full max-w-md rounded-3xl border border-white/10 bg-white/[0.06] p-6 shadow-2xl shadow-black/30 sm:p-8"
        >
            <!-- <a
                href="/absensi"
                class="text-sm font-semibold text-orange-300 hover:text-orange-200"
                >← Halaman absensi</a
            > -->
            <p
                class="mt-8 text-xs font-bold uppercase tracking-[0.25em] text-orange-300"
            >
                Kapita Nusantara
            </p>
            <h1 class="mt-2 text-3xl font-bold">Login admin</h1>
            <p class="mt-2 text-sm leading-6 text-slate-400">
                Masuk untuk mengelola sesi latihan dan data kehadiran.
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="login">
                <label class="block">
                    <span
                        class="mb-2 block text-sm font-semibold text-slate-200"
                        >Email</span
                    >
                    <input
                        v-model.trim="email"
                        type="email"
                        autocomplete="username"
                        required
                        class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none focus:border-orange-300 focus:ring-2 focus:ring-orange-300/20"
                    />
                </label>
                <label class="block">
                    <span
                        class="mb-2 block text-sm font-semibold text-slate-200"
                        >Kata sandi</span
                    >
                    <input
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none focus:border-orange-300 focus:ring-2 focus:ring-orange-300/20"
                    />
                </label>
                <button
                    type="submit"
                    :disabled="loading"
                    class="flex w-full items-center justify-center gap-2 cursor-pointer rounded-xl bg-orange-400 px-4 py-3 font-bold text-slate-950 transition hover:bg-orange-300 disabled:opacity-50"
                >
                    <span
                        v-if="loading"
                        class="size-4 animate-spin rounded-full border-2 cursor-pointer border-slate-950/30 border-t-slate-950"
                    ></span>
                    {{ loading ? "Memeriksa akun..." : "Masuk" }}
                </button>
            </form>
        </section>
    </main>
</template>
