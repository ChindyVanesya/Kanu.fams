<script setup>
import { onMounted, ref } from "vue";
import { api } from "../../lib/api";
import { useFeedback } from "../../lib/feedback";

const { notify } = useFeedback();

const summary = ref(null);
const loading = ref(true);

function formatDate(date) {
    if (!date) return "—";

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeZone: "UTC",
    }).format(new Date(`${date.slice(0, 10)}T00:00:00Z`));
}

function formatWeekdayDate(value) {
    return new Intl.DateTimeFormat("id-ID", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
        timeZone: "UTC",
    }).format(new Date(`${value.slice(0, 10)}T00:00:00Z`));
}

function sessionState(session) {
    if (session.status === "closed")
        return { label: "Ditutup", classes: "bg-slate-100 text-slate-600" };

    const sessionDate = session.training_date.slice(0, 10);
    const today = new Intl.DateTimeFormat("en-CA", {
        timeZone: "Asia/Jayapura",
    }).format(new Date());

    if (sessionDate > today)
        return { label: "Terjadwal", classes: "bg-blue-100 text-blue-800" };
    if (sessionDate === today)
        return {
            label: "Absensi terbuka",
            classes: "bg-emerald-100 text-emerald-800",
        };

    return { label: "Belum ditutup", classes: "bg-amber-100 text-amber-800" };
}

async function load() {
    try {
        summary.value = await api("/admin/absensi/api/dashboard");
    } catch (requestError) {
        notify("error", requestError.message);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div v-if="loading" class="rounded-2xl bg-white p-6 text-sm text-slate-500">
        Memuat ringkasan...
    </div>
    <div
        v-else-if="!summary"
        class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600"
    >
        Ringkasan tidak dapat dimuat. Silakan muat ulang halaman.
    </div>

    <div v-else class="space-y-6">
        <div>
            <h2 class="font-bold">Own/Pj/Head coach : Stifen Piter</h2>
            <h2 class="font-bold">Coaching Staff : Echa Elly</h2>
            <h2 class="font-bold">Admin : Evelin Debeturu</h2>
        </div>
        <section class="grid gap-4 sm:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-sm text-slate-500">Sesi latihan minggu ini</p>
                <p class="mt-3 text-3xl font-bold">
                    {{ summary.weekly_session_count }}
                </p>
                <p class="mt-1 text-xs text-slate-400">
                    {{ formatDate(summary.week_start) }} –
                    {{ formatDate(summary.week_end) }}
                </p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-sm text-slate-500">Total kehadiran minggu ini</p>
                <p class="mt-3 text-3xl font-bold">
                    {{ summary.weekly_attendance_count }}
                </p>
                <p class="mt-1 text-xs text-slate-400">
                    Dihitung per pertemuan
                </p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-sm text-slate-500">Anggota aktif</p>
                <p class="mt-3 text-3xl font-bold">
                    {{ summary.active_member_count }}
                </p>
                <a
                    href="/admin/absensi/anggota"
                    class="mt-1 inline-block text-xs font-semibold text-orange-700 hover:text-orange-800"
                    >Kelola data anggota →</a
                >
            </article>
        </section>

        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-bold">Absensi saat ini</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Admin dapat mencatat kehadiran pada tanggal sesi yang
                        dijadwalkan.
                    </p>
                </div>
                <a
                    href="/admin/absensi/sesi"
                    class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700"
                    >Kelola sesi</a
                >
            </div>
            <div
                v-if="summary.open_session"
                class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4"
            >
                <div>
                    <p class="font-semibold text-emerald-950">
                        {{ summary.open_session.title || "Latihan Basket" }}
                    </p>
                    <p class="mt-1 text-sm text-emerald-800">
                        {{ formatDate(summary.open_session.training_date) }} ·
                        {{ summary.open_session.attendances_count }} hadir
                    </p>
                </div>
                <a
                    :href="`/admin/absensi/sesi/${summary.open_session.id}`"
                    class="text-sm font-bold text-emerald-800 hover:text-emerald-950"
                    >Lihat sesi →</a
                >
            </div>
            <div
                v-else
                class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500"
            >
                Belum ada sesi yang terbuka.
            </div>
        </section>

        <section
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
        >
            <div
                class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"
            >
                <div>
                    <h2 class="font-bold">Sesi terbaru</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Riwayat latihan yang baru dicatat.
                    </p>
                </div>
                <a
                    href="/admin/absensi/sesi"
                    class="text-sm font-semibold text-orange-700"
                    >Semua sesi</a
                >
            </div>
            <div
                v-if="summary.recent_sessions.length === 0"
                class="p-6 text-sm text-slate-500"
            >
                Belum ada riwayat sesi latihan.
            </div>
            <div v-else class="divide-y divide-slate-100">
                <a
                    v-for="session in summary.recent_sessions"
                    :key="session.id"
                    :href="`/admin/absensi/sesi/${session.id}`"
                    class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition hover:bg-slate-50"
                >
                    <div>
                        <p class="font-semibold">
                            {{ session.title || "Latihan Basket" }}
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ formatDate(session.training_date) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="text-slate-600"
                            >{{ session.attendances_count }} hadir</span
                        >
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-bold"
                            :class="sessionState(session).classes"
                            >{{ sessionState(session).label }}</span
                        >
                    </div>
                </a>
            </div>
        </section>
    </div>
</template>
