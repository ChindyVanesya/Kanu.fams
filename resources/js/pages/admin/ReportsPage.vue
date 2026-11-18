<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../../lib/api';
import { useFeedback } from '../../lib/feedback';

const { notify } = useFeedback();

const type = ref('weekly');
const weeklyPeriod = ref(localDate());
const monthlyPeriod = ref(localDate().slice(0, 7));
const search = ref('');
const report = ref(null);
const loading = ref(false);

function localDate() {
    const date = new Date();
    return new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);
}

function formatDate(value) {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeZone: 'UTC' })
        .format(new Date(`${value}T00:00:00Z`));
}

async function load() {
    loading.value = true;
    const period = type.value === 'weekly' ? weeklyPeriod.value : monthlyPeriod.value;
    const params = new URLSearchParams({ type: type.value, period, search: search.value.trim() });

    try {
        report.value = await api(`/admin/absensi/api/rekap?${params}`);
    } catch (requestError) {
        notify('error', requestError.message);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h2 class="font-bold">Pilih periode rekap</h2>
            <form class="mt-4 grid gap-4 sm:grid-cols-[180px_minmax(180px,1fr)_minmax(180px,1fr)_auto] sm:items-end" @submit.prevent="load">
                <label class="block"><span class="mb-2 block text-sm font-semibold">Jenis rekap</span><select v-model="type" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500"><option value="weekly">Mingguan</option><option value="monthly">Bulanan</option></select></label>
                <label v-if="type === 'weekly'" class="block"><span class="mb-2 block text-sm font-semibold">Pilih tanggal dalam minggu</span><input v-model="weeklyPeriod" type="date" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500"></label>
                <label v-else class="block"><span class="mb-2 block text-sm font-semibold">Pilih bulan</span><input v-model="monthlyPeriod" type="month" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500"></label>
                <label class="block"><span class="mb-2 block text-sm font-semibold">Cari anggota</span><input v-model.trim="search" type="search" placeholder="Nama anggota" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500"></label>
                <button type="submit" :disabled="loading" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white hover:bg-slate-700 disabled:opacity-50">{{ loading ? 'Memuat...' : 'Tampilkan' }}</button>
            </form>
        </section>

        <section v-if="report" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div><h2 class="font-bold">{{ report.type === 'weekly' ? 'Rekap mingguan' : 'Rekap bulanan' }}</h2><p class="mt-1 text-sm text-slate-500">{{ formatDate(report.period_start) }} – {{ formatDate(report.period_end) }}</p></div>
                <span class="rounded-xl bg-orange-50 px-3 py-2 text-sm font-semibold text-orange-900">{{ report.session_count }} sesi latihan</span>
            </div>
            <div v-if="loading" class="p-6 text-sm text-slate-500">Memuat rekap...</div>
            <div v-else-if="report.members.length === 0" class="p-6 text-sm text-slate-500">Tidak ada anggota yang cocok dengan pencarian.</div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[600px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Nama anggota</th><th class="px-5 py-3">Kehadiran</th><th class="px-5 py-3">Persentase</th><th class="px-5 py-3">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100"><tr v-for="member in report.members" :key="member.id"><td class="px-5 py-3 font-semibold">{{ member.name }}</td><td class="px-5 py-3">{{ member.attendance_count }} dari {{ member.session_count }} sesi</td><td class="px-5 py-3">{{ member.attendance_percentage === null ? '—' : `${member.attendance_percentage}%` }}</td><td class="px-5 py-3 text-slate-500">{{ member.status === 'active' ? 'Aktif' : 'Nonaktif' }}</td></tr></tbody>
                </table>
            </div>
        </section>
    </div>
</template>
