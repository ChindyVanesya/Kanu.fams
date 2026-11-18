<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../../lib/api';
import { useFeedback } from '../../lib/feedback';

const { notify } = useFeedback();
const sessions = ref([]);
const pagination = ref(null);
const trainingDate = ref(localDate());
const title = ref('');
const notes = ref('');
const pricePerMember = ref(125000);
const loading = ref(true);
const submitting = ref(false);

function localDate() {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jayapura' }).format(new Date());
}

function formatDate(value, style = 'long') {
    if (!value) return '—';
    return new Intl.DateTimeFormat('id-ID', { dateStyle: style, timeZone: 'UTC' })
        .format(new Date(`${value.slice(0, 10)}T00:00:00Z`));
}

function formatRupiah(amount) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount || 0);
}

function status(session) {
    if (session.status === 'closed') return { label: 'Ditutup', style: 'bg-slate-100 text-slate-600' };
    if (session.training_date.slice(0, 10) > localDate()) return { label: 'Terjadwal', style: 'bg-blue-100 text-blue-800' };
    return { label: 'Terbuka', style: 'bg-emerald-100 text-emerald-800' };
}

async function load(page = 1) {
    loading.value = true;
    try {
        const data = await api(`/admin/absensi/api/latihan-private?page=${page}`);
        sessions.value = data.sessions.data;
        pagination.value = data.sessions;
    } catch (error) {
        notify('error', error.message);
    } finally {
        loading.value = false;
    }
}

async function createSession() {
    submitting.value = true;
    try {
        const data = await api('/admin/absensi/api/latihan-private', {
            method: 'POST',
            body: {
                training_date: trainingDate.value,
                title: title.value || null,
                notes: notes.value || null,
                price_per_member: Number(pricePerMember.value),
            },
        });
        notify('success', data.message);
        title.value = '';
        notes.value = '';
        await load(1);
    } catch (error) {
        notify('error', error.message);
    } finally {
        submitting.value = false;
    }
}

onMounted(() => load());
</script>

<template>
    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h2 class="text-lg font-bold">Jadwalkan latihan private</h2>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">Peserta latihan private dipilih dari data anggota yang sudah ada. Biaya berlaku per anggota dan dapat dicicil pada halaman detail sesi.</p>

            <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="createSession">
                <label class="block">
                    <span class="mb-2 block text-sm font-semibold">Tanggal latihan <span class="text-rose-600">*</span></span>
                    <input v-model="trainingDate" :min="localDate()" type="date" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100">
                </label>
                <label class="block">
                    <span class="mb-2 block text-sm font-semibold">Biaya per anggota <span class="text-rose-600">*</span></span>
                    <div class="flex items-center rounded-xl border border-slate-300 focus-within:border-orange-500 focus-within:ring-2 focus-within:ring-orange-100">
                        <span class="border-r border-slate-200 px-3 py-2.5 text-sm text-slate-500">Rp</span>
                        <input v-model.number="pricePerMember" type="number" min="1" max="100000000" step="1" required class="min-w-0 flex-1 rounded-r-xl px-3 py-2.5 outline-none">
                    </div>
                    <span class="mt-1 block text-xs text-slate-400">Nilai awal Rp125.000, dapat disesuaikan untuk setiap sesi.</span>
                </label>
                <label class="block">
                    <span class="mb-2 block text-sm font-semibold">Nama sesi <span class="font-normal text-slate-400">(opsional)</span></span>
                    <input v-model.trim="title" maxlength="255" placeholder="Contoh: Private skill development" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100">
                </label>
                <label class="block">
                    <span class="mb-2 block text-sm font-semibold">Catatan <span class="font-normal text-slate-400">(opsional)</span></span>
                    <input v-model.trim="notes" maxlength="5000" placeholder="Informasi tambahan sesi" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100">
                </label>
                <div class="sm:col-span-2">
                    <button type="submit" :disabled="submitting" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50">{{ submitting ? 'Menyimpan sesi...' : 'Buat sesi private' }}</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-bold">Riwayat latihan private</h2>
                <p class="mt-1 text-sm text-slate-500">Pilih sesi untuk mengatur peserta, absensi, dan cicilan.</p>
            </div>
            <div v-if="loading" class="p-6 text-sm text-slate-500">Memuat sesi private...</div>
            <div v-else-if="sessions.length === 0" class="p-6 text-sm text-slate-500">Belum ada sesi latihan private.</div>
            <div v-else class="divide-y divide-slate-100">
                <a v-for="session in sessions" :key="session.id" :href="`/admin/absensi/latihan-private/${session.id}`" class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50">
                    <div>
                        <p class="font-semibold">{{ session.title || 'Latihan Private' }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ formatDate(session.training_date) }} · {{ session.participants_count }} peserta · {{ session.attendances_count }} hadir</p>
                        <p class="mt-1 text-sm text-slate-500">Biaya per anggota: {{ formatRupiah(session.price_per_member) }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="status(session).style">{{ status(session).label }}</span>
                        <span class="text-sm font-semibold text-orange-700">Kelola sesi →</span>
                    </div>
                </a>
            </div>
            <div v-if="pagination?.last_page > 1" class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm text-slate-500">
                <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
                <div class="flex gap-2">
                    <button :disabled="!pagination.prev_page_url" class="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40" @click="load(pagination.current_page - 1)">Sebelumnya</button>
                    <button :disabled="!pagination.next_page_url" class="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40" @click="load(pagination.current_page + 1)">Berikutnya</button>
                </div>
            </div>
        </section>
    </div>
</template>
