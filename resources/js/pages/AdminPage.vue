<script setup>
import { computed } from "vue";
import { api } from "../lib/api";
import { useFeedback } from "../lib/feedback";
import DashboardPage from "./admin/DashboardPage.vue";
import FeesPage from "./admin/FeesPage.vue";
import MembersPage from "./admin/MembersPage.vue";
import PrivateTrainingDetailPage from "./admin/PrivateTrainingDetailPage.vue";
import PrivateTrainingPage from "./admin/PrivateTrainingPage.vue";
import ReportsPage from "./admin/ReportsPage.vue";
import SessionDetailPage from "./admin/SessionDetailPage.vue";
import SessionsPage from "./admin/SessionsPage.vue";
import TrainingProgramPage from "./admin/TrainingProgramPage.vue";

const path = window.location.pathname;
const adminName = document.body.dataset.adminName || "Admin";
const { notify, confirmAction } = useFeedback();
const match = path.match(/^\/admin\/absensi\/sesi\/(\d+)$/);
const sessionId = match ? Number(match[1]) : null;
const privateTrainingMatch = path.match(
    /^\/admin\/absensi\/latihan-private\/(\d+)$/,
);
const privateTrainingSessionId = privateTrainingMatch
    ? Number(privateTrainingMatch[1])
    : null;
const activeSessionId = privateTrainingSessionId ?? sessionId;

const pageComponent = computed(() => {
    if (privateTrainingSessionId !== null) return PrivateTrainingDetailPage;
    if (path === "/admin/absensi/latihan-private") return PrivateTrainingPage;
    if (path === "/admin/absensi/anggota") return MembersPage;
    if (path === "/admin/absensi/iuran") return FeesPage;
    if (path === "/admin/absensi/rekap") return ReportsPage;
    if (path === "/admin/absensi/program-latihan") return TrainingProgramPage;
    if (sessionId !== null) return SessionDetailPage;
    if (path === "/admin/absensi/sesi") return SessionsPage;

    return DashboardPage;
});

const pageTitle = computed(() => {
    if (privateTrainingSessionId !== null) return "Detail latihan private";
    if (path === "/admin/absensi/latihan-private") return "Latihan private";
    if (path === "/admin/absensi/anggota") return "Data anggota";
    if (path === "/admin/absensi/iuran") return "Iuran latihan";
    if (path === "/admin/absensi/rekap") return "Rekap kehadiran";
    if (path === "/admin/absensi/program-latihan") return "Program latihan";
    if (sessionId !== null) return "Detail sesi";
    if (path === "/admin/absensi/sesi") return "Sesi latihan";

    return "Ringkasan absensi";
});

async function logout() {
    const confirmed = await confirmAction({
        title: "Keluar dari panel admin?",
        message: "Anda harus masuk kembali untuk mengelola absensi.",
        confirmLabel: "Keluar",
    });

    if (!confirmed) return;

    try {
        const data = await api("/admin/logout", { method: "POST" });
        window.location.assign(data.redirect);
    } catch (requestError) {
        notify("error", requestError.message);
    }
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div
                class="mx-auto flex max-w-none items-center justify-between gap-4 px-5 py-4 sm:px-6 lg:px-8"
            >
                <a href="/admin/absensi" class="flex items-center gap-3">
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-slate-900 font-black text-orange-300"
                        >KN</span
                    >
                    <span>
                        <span class="block text-sm font-bold"
                            >Basket Nusantara</span
                        >
                        <span class="block text-xs text-slate-500"
                            >Panel administrasi</span
                        >
                    </span>
                </a>
                <div class="flex items-center gap-3">
                    <span class="hidden text-sm text-slate-600 sm:inline">{{
                        adminName
                    }}</span>
                    <button
                        class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="logout"
                    >
                        Keluar
                    </button>
                </div>
            </div>
        </header>

        <div
            class="mx-auto grid max-w-none gap-5 px-5 py-6 sm:px-6 lg:grid-cols-[240px_minmax(0,1fr)] lg:gap-7 lg:px-8 lg:py-7"
        >
            <nav
                class="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-slate-100/80 p-2 lg:sticky lg:top-6 lg:block lg:h-fit lg:space-y-1"
                aria-label="Menu admin"
            >
                <a
                    href="/admin/absensi"
                    class="block shrink-0 rounded-xl px-4 py-3 text-sm font-semibold transition"
                    :class="
                        path === '/admin/absensi'
                            ? 'bg-slate-900 text-white'
                            : 'text-slate-600 hover:bg-white'
                    "
                    >Ringkasan
                </a>
                <a
                    href="/admin/absensi/sesi"
                    class="block shrink-0 rounded-xl px-4 py-3 text-sm font-semibold transition"
                    :class="
                        path.includes('/sesi')
                            ? 'bg-slate-900 text-white'
                            : 'text-slate-600 hover:bg-white'
                    "
                    >Sesi latihan</a
                >
                <a
                    href="/admin/absensi/latihan-private"
                    class="block shrink-0 rounded-xl px-4 py-3 text-sm font-semibold transition"
                    :class="
                        path.includes('/latihan-private')
                            ? 'bg-slate-900 text-white'
                            : 'text-slate-600 hover:bg-white'
                    "
                    >Latihan private</a
                >
                <a
                    href="/admin/absensi/program-latihan"
                    class="block shrink-0 rounded-xl px-4 py-3 text-sm font-semibold transition"
                    :class="
                        path.includes('/program-latihan')
                            ? 'bg-slate-900 text-white'
                            : 'text-slate-600 hover:bg-white'
                    "
                    >Program latihan</a
                >
                <a
                    href="/admin/absensi/iuran"
                    class="block shrink-0 rounded-xl px-4 py-3 text-sm font-semibold transition"
                    :class="
                        path.includes('/iuran')
                            ? 'bg-slate-900 text-white'
                            : 'text-slate-600 hover:bg-white'
                    "
                    >Iuran</a
                >
                <a
                    href="/admin/absensi/anggota"
                    class="block shrink-0 rounded-xl px-4 py-3 text-sm font-semibold transition"
                    :class="
                        path.includes('/anggota')
                            ? 'bg-slate-900 text-white'
                            : 'text-slate-600 hover:bg-white'
                    "
                    >Data anggota</a
                >
                <a
                    href="/admin/absensi/rekap"
                    class="block shrink-0 rounded-xl px-4 py-3 text-sm font-semibold transition"
                    :class="
                        path.includes('/rekap')
                            ? 'bg-slate-900 text-white'
                            : 'text-slate-600 hover:bg-white'
                    "
                    >Rekap kehadiran</a
                >
            </nav>

            <main class="min-w-0">
                <div class="mb-6">
                    <p
                        class="text-xs font-bold uppercase tracking-[0.2em] text-orange-600"
                    >
                        Administrasi
                    </p>
                    <h1
                        class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl"
                    >
                        {{ pageTitle }}
                    </h1>
                </div>
                <component :is="pageComponent" :session-id="activeSessionId" />
            </main>
        </div>
    </div>
</template>
