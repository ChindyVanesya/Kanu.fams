<script setup>
import { onMounted, ref } from "vue";
import { api } from "../../lib/api";
import { useFeedback } from "../../lib/feedback";

const { notify, confirmAction } = useFeedback();

const sessions = ref([]);
const months = ref([]);
const selectedMonth = ref('');
const pagination = ref(null);
const openSession = ref(null);
const trainingDate = ref(localDate());
const title = ref("");
const notes = ref("");
const loading = ref(true);
const submitting = ref(false);

function localDate() {
    return new Intl.DateTimeFormat("en-CA", {
        timeZone: "Asia/Jayapura",
    }).format(new Date());
}

function formatDate(value) {
    if (!value) return "—";

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "long",
        timeZone: "UTC",
    }).format(new Date(`${value.slice(0, 10)}T00:00:00Z`));
}

function formatDateTime(value) {
    if (!value) return "—";

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "short",
        timeStyle: "short",
        timeZone: "Asia/Jayapura",
    }).format(new Date(value));
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

function monthLabel(value) {
    if (!value) return '';
    const [year, month] = value.split('-').map(Number);
    return new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric', timeZone: 'UTC' })
        .format(new Date(Date.UTC(year, month - 1, 1)));
}

async function load(month = selectedMonth.value, page = 1) {
    loading.value = true;
    try {
        const query = new URLSearchParams({ page: String(page) });
        if (month) query.set('month', month);
        const [sessionData, dashboard] = await Promise.all([
            api(`/admin/absensi/api/sesi?${query.toString()}`),
            api("/admin/absensi/api/dashboard"),
        ]);
        months.value = sessionData.months;
        openSession.value = dashboard.open_session;
        if (!month && months.value.length) {
            selectedMonth.value = months.value[0];
            return await load(selectedMonth.value, 1);
        }
        selectedMonth.value = month || '';
        sessions.value = sessionData.sessions.data;
        pagination.value = sessionData.sessions;
    } catch (requestError) {
        notify("error", requestError.message);
    } finally {
        loading.value = false;
    }
}

async function openAttendance() {
    submitting.value = true;

    try {
        const data = await api("/admin/absensi/api/sesi", {
            method: "POST",
            body: {
                training_date: trainingDate.value,
                title: title.value || null,
                notes: notes.value || null,
            },
        });
        notify("success", data.message);
        selectedMonth.value = trainingDate.value.slice(0, 7);
        await load(selectedMonth.value);
    } catch (requestError) {
        notify("error", requestError.message);
    } finally {
        submitting.value = false;
    }
}

async function deleteSession(session) {
    const attendanceWarning =
        session.attendances_count > 0
            ? ` Semua ${session.attendances_count} catatan kehadiran sesi ini juga akan dihapus.`
            : "";

    const confirmed = await confirmAction({
        title: "Hapus sesi latihan?",
        message: `Sesi “${session.title || "Latihan Basket"}” pada ${formatDate(session.training_date)} akan dihapus.${attendanceWarning} Tindakan ini tidak dapat dibatalkan.`,
        confirmLabel: "Hapus sesi",
        variant: "danger",
    });

    if (!confirmed) return;

    try {
        const data = await api(`/admin/absensi/api/sesi/${session.id}`, {
            method: "DELETE",
        });
        notify("success", data.message);
        await load();
    } catch (requestError) {
        notify("error", requestError.message);
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold">Jadwalkan sesi latihan</h2>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                        Jadwalkan sesi lebih awal. Admin dapat mengonfirmasi
                        kehadiran pada tanggal latihan (WIT).
                    </p>
                </div>
                <span
                    v-if="openSession"
                    class="rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-bold text-emerald-800"
                    >Absensi hari ini tersedia</span
                >
            </div>

            <div
                v-if="openSession"
                class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4"
            >
                <p class="text-sm text-emerald-900">
                    <strong>{{ openSession.title || "Latihan Basket" }}</strong>
                    · {{ formatDate(openSession.training_date) }}
                </p>
                <a
                    :href="`/admin/absensi/sesi/${openSession.id}`"
                    class="text-sm font-bold text-emerald-800 hover:underline"
                    >Buka detail sesi →</a
                >
            </div>

            <form
                class="mt-5 grid gap-4 sm:grid-cols-2"
                @submit.prevent="openAttendance"
            >
                <label class="block">
                    <span class="mb-2 block text-sm font-semibold"
                        >Tanggal latihan
                        <span class="text-rose-600">*</span></span
                    >
                    <input
                        v-model="trainingDate"
                        :min="localDate()"
                        type="date"
                        required
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100"
                    />
                </label>
                <label class="block">
                    <span class="mb-2 block text-sm font-semibold"
                        >Judul latihan
                        <span class="font-normal text-slate-400"
                            >(opsional)</span
                        ></span
                    >
                    <input
                        v-model.trim="title"
                        type="text"
                        maxlength="255"
                        placeholder="Contoh: Latihan rutin"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100"
                    />
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-2 block text-sm font-semibold"
                        >Catatan
                        <span class="font-normal text-slate-400"
                            >(opsional)</span
                        ></span
                    >
                    <textarea
                        v-model.trim="notes"
                        rows="2"
                        maxlength="5000"
                        placeholder="Informasi tambahan untuk anggota"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100"
                    ></textarea>
                </label>
                <div class="sm:col-span-2">
                    <button
                        type="submit"
                        :disabled="submitting || loading"
                        class="rounded-xl cursor-pointer bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-45"
                    >
                        {{
                            submitting
                                ? "Menyimpan jadwal..."
                                : "Jadwalkan Sesi"
                        }}
                    </button>
                </div>
            </form>
        </section>

        <section
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
        >
            <div class="border-b border-slate-100 px-5 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold">Riwayat sesi latihan</h2>
                        <p class="mt-1 text-sm text-slate-500">Satu baris mewakili satu pertemuan latihan.</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-600">
                        <span>Bulan</span>
                        <select v-model="selectedMonth" class="rounded-lg border border-slate-300 bg-white px-3 py-2" @change="load(selectedMonth)">
                            <option v-for="month in months" :key="month" :value="month">{{ monthLabel(month) }}</option>
                        </select>
                    </label>
                </div>
            </div>
            <div v-if="loading" class="p-6 text-sm text-slate-500">
                Memuat riwayat...
            </div>
            <div
                v-else-if="sessions.length === 0"
                class="p-6 text-sm text-slate-500"
            >
                Belum ada sesi latihan. Jadwalkan sesi pertama menggunakan
                formulir di atas.
            </div>
            <div v-else class="divide-y divide-slate-100">
                <div
                    v-for="session in sessions"
                    :key="session.id"
                    class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition hover:bg-slate-50"
                >
                    <a
                        :href="`/admin/absensi/sesi/${session.id}`"
                        class="min-w-0 flex-1"
                    >
                        <p class="font-semibold">
                            {{ session.title || "Latihan Basket" }}
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ formatDate(session.training_date) }} ·
                            {{ session.attendances_count }} hadir
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            Dibuka {{ formatDateTime(session.opened_at) }} ·
                            Ditutup {{ formatDateTime(session.closed_at) }}
                        </p>
                    </a>
                    <div class="flex items-center gap-3">
                        <span
                            class="rounded-full px-3 py-1 text-xs font-bold"
                            :class="sessionState(session).classes"
                            >{{ sessionState(session).label }}</span
                        >
                        <a
                            :href="`/admin/absensi/sesi/${session.id}`"
                            class="text-sm font-semibold text-orange-700 hover:text-orange-800"
                            >Detail →</a
                        >
                        <button
                            type="button"
                            :disabled="session.attendances_count > 0"
                            :title="session.attendances_count > 0 ? 'Sesi dengan catatan kehadiran atau iuran tidak dapat dihapus.' : ''"
                            class="rounded-lg px-2.5 py-1.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40"
                            @click="deleteSession(session)"
                        >
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
            <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm text-slate-500">
                <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
                <div class="flex gap-2">
                    <button :disabled="!pagination.prev_page_url" class="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40" @click="load(selectedMonth, pagination.current_page - 1)">Sebelumnya</button>
                    <button :disabled="!pagination.next_page_url" class="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40" @click="load(selectedMonth, pagination.current_page + 1)">Berikutnya</button>
                </div>
            </div>
        </section>
    </div>
</template>
