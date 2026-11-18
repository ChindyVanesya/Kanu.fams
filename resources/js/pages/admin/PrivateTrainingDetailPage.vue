<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '../../lib/api';
import { useFeedback } from '../../lib/feedback';

const props = defineProps({ sessionId: { type: Number, required: true } });
const { notify, confirmAction } = useFeedback();
const session = ref(null);
const participants = ref([]);
const availableMembers = ref([]);
const selectedMemberId = ref('');
const paymentAmounts = ref({});
const loading = ref(true);
const saving = ref(false);
const addingParticipant = ref(false);
const closing = ref(false);

const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jayapura' }).format(new Date());
const canMarkAttendance = computed(() => session.value?.status === 'open' && session.value.training_date.slice(0, 10) === today);

function formatDate(value, style = 'long') {
    if (!value) return '—';
    return new Intl.DateTimeFormat('id-ID', { dateStyle: style, timeZone: 'UTC' })
        .format(new Date(`${value.slice(0, 10)}T00:00:00Z`));
}

function formatDateTime(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jayapura' })
        .format(new Date(value));
}

function formatRupiah(amount) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount || 0);
}

function paymentStatus(participant) {
    if (participant.payment_status === 'paid') return { label: 'Lunas', style: 'bg-emerald-100 text-emerald-800' };
    if (participant.payment_status === 'partial') return { label: 'Cicilan', style: 'bg-blue-100 text-blue-800' };
    return { label: 'Belum bayar', style: 'bg-amber-100 text-amber-800' };
}

async function load() {
    loading.value = true;
    try {
        const data = await api(`/admin/absensi/api/latihan-private/${props.sessionId}`);
        session.value = data.session;
        participants.value = data.participants;
        availableMembers.value = data.available_members;
    } catch (error) {
        notify('error', error.message);
    } finally {
        loading.value = false;
    }
}

async function addParticipant() {
    if (!selectedMemberId.value) return;
    addingParticipant.value = true;
    try {
        const data = await api(`/admin/absensi/api/latihan-private/${props.sessionId}/peserta`, {
            method: 'POST',
            body: { member_id: Number(selectedMemberId.value) },
        });
        notify('success', data.message);
        selectedMemberId.value = '';
        await load();
    } catch (error) {
        notify('error', error.message);
    } finally {
        addingParticipant.value = false;
    }
}

async function markAttendance(participant) {
    try {
        const data = await api(`/admin/absensi/api/latihan-private/${props.sessionId}/peserta/${participant.id}/hadir`, { method: 'POST' });
        participant.attended_at = data.attended_at;
        session.value.attendances_count = (session.value.attendances_count || 0) + 1;
        notify('success', data.message);
    } catch (error) {
        notify('error', error.message);
    }
}

async function recordPayment(participant) {
    const amount = Number(paymentAmounts.value[participant.id]);
    if (!Number.isInteger(amount) || amount < 1 || amount > participant.balance) {
        notify('error', `Masukkan cicilan antara Rp1 dan ${formatRupiah(participant.balance)}.`);
        return;
    }

    saving.value = true;
    try {
        const data = await api(`/admin/absensi/api/latihan-private-peserta/${participant.id}/pembayaran`, {
            method: 'POST',
            body: { amount },
        });
        participant.amount_paid = data.amount_paid;
        participant.balance = data.balance;
        participant.payment_status = data.balance === 0 ? 'paid' : 'partial';
        participant.payments.unshift(data.payment);
        paymentAmounts.value[participant.id] = '';
        notify('success', data.message);
    } catch (error) {
        notify('error', error.message);
    } finally {
        saving.value = false;
    }
}

async function closeSession() {
    const confirmed = await confirmAction({
        title: 'Tutup sesi private?',
        message: 'Peserta tidak dapat ditambahkan atau melakukan absensi setelah sesi ditutup. Pencatatan cicilan yang belum lunas tetap tersedia.',
        confirmLabel: 'Tutup sesi',
        variant: 'danger',
    });
    if (!confirmed) return;

    closing.value = true;
    try {
        const data = await api(`/admin/absensi/api/latihan-private/${props.sessionId}/tutup`, { method: 'PATCH' });
        session.value = data.session;
        notify('success', data.message);
    } catch (error) {
        notify('error', error.message);
    } finally {
        closing.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div v-if="loading" class="rounded-2xl bg-white p-6 text-sm text-slate-500">Memuat detail latihan private...</div>
    <div v-else-if="session" class="space-y-5">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <a href="/admin/absensi/latihan-private" class="text-sm font-semibold text-orange-700 hover:underline">← Kembali ke latihan private</a>
                    <h2 class="mt-4 text-xl font-bold">{{ session.title || 'Latihan Private' }}</h2>
                    <p class="mt-1 text-slate-600">{{ formatDate(session.training_date) }}</p>
                    <p v-if="session.notes" class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-500">{{ session.notes }}</p>
                </div>
                <div class="flex flex-col items-start gap-3 sm:items-end">
                    <span class="rounded-full px-3 py-1.5 text-xs font-bold" :class="session.status === 'open' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'">{{ session.status === 'open' ? 'Sesi terbuka' : 'Sesi ditutup' }}</span>
                    <button v-if="session.status === 'open'" type="button" :disabled="closing" class="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50" @click="closeSession">{{ closing ? 'Menutup...' : 'Tutup sesi' }}</button>
                </div>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-3"><span class="block text-sm text-slate-500">Biaya per peserta</span><strong class="mt-1 block">{{ formatRupiah(session.price_per_member) }}</strong></div>
                <div class="rounded-xl bg-slate-50 p-3"><span class="block text-sm text-slate-500">Peserta private</span><strong class="mt-1 block">{{ session.participants_count || 0 }}</strong></div>
                <div class="rounded-xl bg-slate-50 p-3"><span class="block text-sm text-slate-500">Hadir</span><strong class="mt-1 block">{{ session.attendances_count || 0 }}</strong></div>
            </div>
        </section>

        <section v-if="session.status === 'open'" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h2 class="font-bold">Tambahkan anggota ke sesi private</h2>
            <p class="mt-1 text-sm text-slate-500">Pilih dari data anggota aktif. Anggota yang sama tidak dapat ditambahkan dua kali dalam satu sesi.</p>
            <form class="mt-4 flex flex-col gap-3 sm:flex-row" @submit.prevent="addParticipant">
                <select v-model="selectedMemberId" required class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2.5">
                    <option value="" disabled>Pilih anggota</option>
                    <option v-for="member in availableMembers" :key="member.id" :value="String(member.id)">{{ member.name }}{{ member.member_code ? ` · ${member.member_code}` : '' }}</option>
                </select>
                <button type="submit" :disabled="addingParticipant || availableMembers.length === 0" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50">{{ addingParticipant ? 'Menambahkan...' : 'Tambah peserta' }}</button>
            </form>
            <p v-if="availableMembers.length === 0" class="mt-3 text-sm text-slate-500">Semua anggota aktif sudah ditambahkan atau belum ada anggota aktif.</p>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-bold">Peserta, absensi, dan pembayaran private</h2>
                <p class="mt-1 text-sm text-slate-500">Biaya dicatat per peserta. Pembayaran dapat ditambahkan beberapa kali sampai lunas.</p>
            </div>
            <div v-if="participants.length === 0" class="p-6 text-sm text-slate-500">Belum ada peserta pada sesi ini.</div>
            <div v-else class="divide-y divide-slate-100">
                <article v-for="(participant, index) in participants" :key="participant.id" class="p-5 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-bold">{{ index + 1 }}. {{ participant.name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ participant.member_code || 'Tanpa kode anggota' }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="paymentStatus(participant).style">{{ paymentStatus(participant).label }}</span>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div class="rounded-xl bg-slate-50 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold">Absensi private</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ participant.attended_at ? `Hadir · ${formatDateTime(participant.attended_at)}` : 'Belum dikonfirmasi hadir' }}</p>
                                </div>
                                <button v-if="!participant.attended_at" type="button" :disabled="!canMarkAttendance" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40" @click="markAttendance(participant)">{{ canMarkAttendance ? 'Konfirmasi hadir' : 'Tersedia saat tanggal sesi' }}</button>
                            </div>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4">
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div><span class="block text-slate-500">Sudah dibayar</span><strong class="mt-1 block">{{ formatRupiah(participant.amount_paid) }}</strong></div>
                                <div><span class="block text-slate-500">Sisa cicilan</span><strong class="mt-1 block">{{ formatRupiah(participant.balance) }}</strong></div>
                            </div>
                            <form v-if="participant.balance > 0" class="mt-4 flex flex-wrap gap-2" @submit.prevent="recordPayment(participant)">
                                <input v-model="paymentAmounts[participant.id]" type="number" min="1" :max="participant.balance" step="1" required placeholder="Nominal cicilan" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <button type="submit" :disabled="saving" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:opacity-50">{{ saving ? 'Menyimpan...' : 'Catat cicilan' }}</button>
                            </form>
                            <p v-else class="mt-3 text-sm font-semibold text-emerald-700">Biaya sesi sudah lunas.</p>
                            <details v-if="participant.payments.length" class="mt-3 text-xs text-slate-500">
                                <summary class="cursor-pointer font-semibold">Riwayat cicilan ({{ participant.payments.length }})</summary>
                                <ul class="mt-2 space-y-1">
                                    <li v-for="payment in participant.payments" :key="payment.id">{{ formatRupiah(payment.amount) }} · {{ formatDateTime(payment.paid_at) }}<span v-if="payment.recorded_by"> · {{ payment.recorded_by.name }}</span></li>
                                </ul>
                            </details>
                        </div>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>
