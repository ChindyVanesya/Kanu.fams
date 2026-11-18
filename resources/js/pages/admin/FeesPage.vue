<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../../lib/api';
import { useFeedback } from '../../lib/feedback';

const { notify } = useFeedback();
const members = ref([]);
const loading = ref(true);

function formatDate(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('id-ID', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC',
    }).format(new Date(`${value.slice(0, 10)}T00:00:00Z`));
}

async function load() {
    try {
        const data = await api('/admin/absensi/api/iuran');
        members.value = data.unpaid_contributions;
    } catch (error) {
        notify('error', error.message);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h2 class="font-bold">Anggota dengan iuran belum dibayar</h2>
            <p class="mt-1 text-sm text-slate-500">Pilih anggota atau salah satu tanggal pertemuan untuk membuka sesi dan mencatat pembayarannya.</p>
        </section>

        <div v-if="loading" class="rounded-2xl bg-white p-6 text-sm text-slate-500">Memuat daftar iuran...</div>
        <section v-else-if="members.length === 0" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-sm text-emerald-800">
            Tidak ada iuran sesi yang belum dibayar.
        </section>
        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="member in members" :key="member.member_id" class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm">
                <a :href="`/admin/absensi/sesi/${member.sessions[0].id}?member=${member.member_id}`" class="block rounded-xl outline-none focus:ring-2 focus:ring-orange-300">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-bold text-slate-900">{{ member.member_name }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ member.member_code || 'Tanpa kode anggota' }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">{{ member.unpaid_session_count }} sesi</span>
                    </div>
                    <p class="mt-4 text-sm font-semibold text-orange-700">Buka sesi untuk catat pembayaran →</p>
                </a>
                <div class="mt-4 border-t border-slate-100 pt-3">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Pertemuan belum dibayar</p>
                    <ul class="space-y-2">
                        <li v-for="session in member.sessions" :key="session.id">
                            <a :href="`/admin/absensi/sesi/${session.id}?member=${member.member_id}`" class="text-sm text-slate-600 hover:text-orange-700">{{ formatDate(session.training_date) }} · {{ session.title || 'Latihan Basket' }}</a>
                        </li>
                    </ul>
                </div>
            </article>
        </div>
    </div>
</template>
