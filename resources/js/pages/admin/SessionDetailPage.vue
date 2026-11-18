<script setup>
import { nextTick, onMounted, ref } from 'vue';
import { api } from '../../lib/api';
import { useFeedback } from '../../lib/feedback';

const { notify, confirmAction } = useFeedback();

const props = defineProps({ sessionId: { type: Number, required: true } });
const session = ref(null);
const members = ref([]);
const feeAmounts = ref({});
const evaluationDraft = ref('');
const loading = ref(true);
const loadFailed = ref(false);
const submitting = ref(false);
const savingEvaluation = ref(false);
const focusedMemberId = Number(new URLSearchParams(window.location.search).get('member')) || null;

function formatDate(value, dateStyle = 'long') {
    if (!value) return '—';

    return new Intl.DateTimeFormat('id-ID', { dateStyle, timeZone: 'UTC' })
        .format(new Date(`${value.slice(0, 10)}T00:00:00Z`));
}

function formatDateTime(value) {
    if (!value) return '—';

    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jayapura' })
        .format(new Date(value));
}

function formatRupiah(amount) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount ?? 0);
}

function formatWeekdayDate(value) {
    return new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })
        .format(new Date(`${value.slice(0, 10)}T00:00:00Z`));
}

function canMarkAttendance() {
    return session.value?.status === 'open'
        && session.value.training_date.slice(0, 10) === new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jayapura' }).format(new Date());
}

function sessionState() {
    if (session.value.status === 'closed') return { label: 'Absensi ditutup', classes: 'bg-slate-100 text-slate-600' };

    const sessionDate = session.value.training_date.slice(0, 10);
    const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jayapura' }).format(new Date());

    if (sessionDate > today) return { label: 'Terjadwal', classes: 'bg-blue-100 text-blue-800' };
    if (sessionDate === today) return { label: 'Absensi terbuka', classes: 'bg-emerald-100 text-emerald-800' };

    return { label: 'Belum ditutup', classes: 'bg-amber-100 text-amber-800' };
}

function isFutureSession() {
    const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jayapura' }).format(new Date());

    return session.value.training_date.slice(0, 10) > today;
}

async function load() {
    try {
        const data = await api(`/admin/absensi/api/sesi/${props.sessionId}`);
        session.value = data.session;
        members.value = data.members;
        evaluationDraft.value = data.session.evaluation || '';
        if (focusedMemberId) {
            await nextTick();
            document.querySelector(`[data-member-id="${focusedMemberId}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    } catch (requestError) {
        loadFailed.value = true;
        notify('error', requestError.message);
    } finally {
        loading.value = false;
    }
}

async function markAttendance(member) {
    const confirmed = await confirmAction({
        title: 'Konfirmasi kehadiran?',
        message: `Catat ${member.name} hadir pada sesi ini?`,
        confirmLabel: 'Konfirmasi hadir',
    });

    if (!confirmed) return;

    try {
        const data = await api(`/admin/absensi/api/sesi/${props.sessionId}/anggota/${member.id}/hadir`, { method: 'POST' });
        member.attendance = { ...data.attendance, fee_paid_amount: 0, has_paid_fee: false, fee_payments: [] };
        session.value.attendances_count += 1;
        notify('success', data.message);
    } catch (requestError) {
        notify('error', requestError.message);
    }
}

async function recordSessionFee(member) {
    const amount = Number(feeAmounts.value[member.id]);
    if (!Number.isInteger(amount) || amount < 1) {
        notify('error', 'Masukkan nominal iuran yang valid.');
        return;
    }

    try {
        const data = await api(`/admin/absensi/api/kehadiran/${member.attendance.id}/iuran`, {
            method: 'POST',
            body: { amount },
        });
        member.attendance.fee_paid_amount += amount;
        member.attendance.has_paid_fee = true;
        member.attendance.fee_payments.unshift(data.payment);
        feeAmounts.value[member.id] = '';
        notify('success', data.message);
    } catch (requestError) {
        notify('error', requestError.message);
    }
}

async function saveEvaluation() {
    savingEvaluation.value = true;
    try {
        const data = await api(`/admin/absensi/api/sesi/${props.sessionId}/evaluasi`, {
            method: 'PATCH',
            body: { evaluation: evaluationDraft.value.trim() || null },
        });
        session.value.evaluation = data.evaluation;
        evaluationDraft.value = data.evaluation || '';
        notify('success', data.message);
    } catch (requestError) {
        notify('error', requestError.message);
    } finally {
        savingEvaluation.value = false;
    }
}

function wrapCanvasText(context, value, maxWidth) {
    const lines = [];

    for (const paragraph of String(value || '').split('\n')) {
        const words = paragraph.split(/\s+/).filter(Boolean);
        if (words.length === 0) {
            lines.push('');
            continue;
        }

        let line = '';
        for (const word of words) {
            const candidate = line ? `${line} ${word}` : word;
            if (line && context.measureText(candidate).width > maxWidth) {
                lines.push(line);
                line = word;
            } else {
                line = candidate;
            }
        }
        lines.push(line);
    }

    return lines;
}

async function downloadAttendancePng() {
    try {
        if ((evaluationDraft.value.trim() || null) !== (session.value.evaluation || null)) {
            const saved = await api(`/admin/absensi/api/sesi/${props.sessionId}/evaluasi`, {
                method: 'PATCH',
                body: { evaluation: evaluationDraft.value.trim() || null },
            });
            session.value.evaluation = saved.evaluation;
        }

        await document.fonts?.ready;
        const presentMembers = members.value.filter((member) => member.attendance);
        const width = 1080;
        const padding = 64;
        const contentWidth = width - padding * 2;
        const measure = document.createElement('canvas').getContext('2d');
        measure.font = '400 24px Poppins, sans-serif';
        const evaluationLines = wrapCanvasText(measure, session.value.evaluation || 'Belum ada evaluasi latihan.', contentWidth);
        const height = 455 + presentMembers.length * 76 + evaluationLines.length * 34;
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext('2d');

        context.fillStyle = '#f8fafc';
        context.fillRect(0, 0, width, height);
        context.fillStyle = '#0f172a';
        context.fillRect(0, 0, width, 220);
        context.fillStyle = '#f97316';
        context.fillRect(0, 0, 14, 220);
        context.fillStyle = '#fdba74';
        context.font = '700 20px Poppins, sans-serif';
        context.fillText('BASKET NUSANTARA · HALMAHERA UTARA', padding, 54);
        context.fillStyle = '#ffffff';
        context.font = '700 38px Poppins, sans-serif';
        context.fillText('REKAP LATIHAN', padding, 111);
        context.font = '500 25px Poppins, sans-serif';
        context.fillText(session.value.title || 'Latihan Basket', padding, 158);
        context.fillStyle = '#cbd5e1';
        context.font = '400 20px Poppins, sans-serif';
        context.fillText(formatWeekdayDate(session.value.training_date), padding, 193);

        let y = 278;
        context.fillStyle = '#0f172a';
        context.font = '700 28px Poppins, sans-serif';
        context.fillText('DAFTAR HADIR', padding, y);
        context.fillStyle = '#c2410c';
        context.font = '600 20px Poppins, sans-serif';
        context.textAlign = 'right';
        context.fillText(`${presentMembers.length} anggota`, width - padding, y);
        context.textAlign = 'left';
        y += 28;

        if (presentMembers.length === 0) {
            context.fillStyle = '#64748b';
            context.font = '400 22px Poppins, sans-serif';
            context.fillText('Belum ada kehadiran yang dicatat.', padding, y + 34);
            y += 65;
        } else {
            presentMembers.forEach((member, index) => {
                context.fillStyle = index % 2 === 0 ? '#ffffff' : '#f1f5f9';
                context.fillRect(padding, y, contentWidth, 70);
                context.fillStyle = '#f97316';
                context.font = '700 19px Poppins, sans-serif';
                context.fillText(String(index + 1).padStart(2, '0'), padding + 14, y + 32);
                context.fillStyle = '#0f172a';
                context.font = '600 21px Poppins, sans-serif';
                context.fillText(member.name, padding + 64, y + 32);
                context.fillStyle = '#64748b';
                context.font = '400 17px Poppins, sans-serif';
                context.textAlign = 'right';
                context.fillText(member.member_code || '', width - padding - 14, y + 31);
                context.fillStyle = member.attendance.has_paid_fee ? '#047857' : '#b45309';
                context.font = '600 17px Poppins, sans-serif';
                const contribution = member.attendance.has_paid_fee
                    ? `Iuran dibayar ${formatRupiah(member.attendance.fee_paid_amount)}`
                    : 'Iuran belum dibayar';
                context.fillText(contribution, width - padding - 14, y + 56);
                context.textAlign = 'left';
                y += 76;
            });
        }

        y += 25;
        context.fillStyle = '#0f172a';
        context.font = '700 28px Poppins, sans-serif';
        context.fillText('EVALUASI LATIHAN', padding, y);
        y += 42;
        context.fillStyle = '#334155';
        context.font = '400 24px Poppins, sans-serif';
        evaluationLines.forEach((line) => {
            context.fillText(line, padding, y);
            y += 34;
        });

        context.fillStyle = '#94a3b8';
        context.font = '400 15px Poppins, sans-serif';
        context.fillText('Basket Nusantara · Catatan sesi latihan', padding, height - 26);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        if (!blob) throw new Error('Gambar rekap tidak dapat dibuat.');

        const link = document.createElement('a');
        const imageUrl = URL.createObjectURL(blob);
        link.href = imageUrl;
        link.download = `rekap-latihan-${session.value.training_date.slice(0, 10)}.png`;
        link.click();
        window.setTimeout(() => URL.revokeObjectURL(imageUrl), 1000);
        notify('success', 'Rekap kehadiran berhasil diunduh dalam format PNG.');
    } catch (requestError) {
        notify('error', requestError.message || 'PNG rekap tidak dapat diunduh.');
    }
}

async function closeSession() {
    const cancelingSchedule = isFutureSession();
    const confirmed = await confirmAction({
        title: cancelingSchedule ? 'Batalkan jadwal latihan?' : 'Tutup absensi sesi?',
        message: cancelingSchedule
            ? 'Jadwal ini akan ditutup dan anggota tidak dapat melakukan absensi pada sesi tersebut.'
            : 'Anggota tidak dapat melakukan absensi lagi setelah sesi ditutup.',
        confirmLabel: cancelingSchedule ? 'Batalkan jadwal' : 'Tutup absensi',
        variant: 'danger',
    });

    if (!confirmed) return;

    submitting.value = true;

    try {
        const data = await api(`/admin/absensi/api/sesi/${props.sessionId}/tutup`, { method: 'PATCH' });
        session.value = data.session;
        notify('success', data.message);
    } catch (requestError) {
        notify('error', requestError.message);
    } finally {
        submitting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div v-if="loading" class="rounded-2xl bg-white p-6 text-sm text-slate-500">Memuat detail sesi...</div>
    <div v-else-if="loadFailed" class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600">Detail sesi tidak dapat dimuat. Silakan kembali ke daftar sesi dan coba lagi.</div>
    <div v-else-if="session" class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <a href="/admin/absensi/sesi" class="text-sm font-semibold text-orange-700 hover:underline">← Kembali ke riwayat sesi</a>
                    <h2 class="mt-4 text-xl font-bold">{{ session.title || 'Latihan Basket' }}</h2>
                    <p class="mt-1 text-slate-600">{{ formatDate(session.training_date) }}</p>
                    <p v-if="session.notes" class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-500">{{ session.notes }}</p>
                </div>
                <div class="flex flex-col items-start gap-3 sm:items-end">
                    <span class="rounded-full px-3 py-1.5 text-xs font-bold" :class="sessionState().classes">{{ sessionState().label }}</span>
                    <button type="button" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50" @click="downloadAttendancePng">Unduh rekap PNG</button>
                    <button v-if="session.status === 'open'" :disabled="submitting" class="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50" @click="closeSession">{{ submitting ? 'Menyimpan...' : (isFutureSession() ? 'Batalkan Jadwal' : 'Tutup Absensi') }}</button>
                </div>
            </div>
            <div v-if="session.training_focus" class="mt-5 rounded-xl border border-orange-100 bg-orange-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-orange-800">Program latihan · {{ session.training_focus }}</p>
                <p v-if="session.training_program" class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ session.training_program }}</p>
            </div>
            <div class="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-3"><span class="block text-slate-500">Jumlah hadir</span><strong class="mt-1 block text-lg">{{ session.attendances_count }}</strong></div>
                <div class="rounded-xl bg-slate-50 p-3"><span class="block text-slate-500">Dibuka</span><strong class="mt-1 block">{{ formatDateTime(session.opened_at) }}</strong></div>
                <div class="rounded-xl bg-slate-50 p-3"><span class="block text-slate-500">Ditutup</span><strong class="mt-1 block">{{ formatDateTime(session.closed_at) }}</strong></div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-bold">Absensi dan iuran anggota</h2>
                <p class="mt-1 text-sm text-slate-500">Konfirmasi anggota yang hadir, lalu catat iurannya. Anggota yang belum membayar tetap tercatat sebagai tunggakan sesi ini.</p>
            </div>
            <div v-if="members.length === 0" class="p-6 text-sm text-slate-500">Belum ada anggota aktif di daftar anggota.</div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">No.</th><th class="px-5 py-3">Nama anggota</th><th class="px-5 py-3">Kode anggota</th><th class="px-5 py-3">Kehadiran</th><th class="px-5 py-3">Iuran sesi</th><th class="px-5 py-3">Tindakan</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(member, index) in members" :key="member.id" :data-member-id="member.id" :class="focusedMemberId === member.id ? 'bg-amber-50' : ''">
                            <td class="px-5 py-3 text-slate-500">{{ index + 1 }}</td>
                            <td class="px-5 py-3 font-semibold">{{ member.name }}<span v-if="member.status !== 'active'" class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">Nonaktif</span></td>
                            <td class="px-5 py-3 text-slate-600">{{ member.member_code || '—' }}</td>
                            <td class="px-5 py-3"><span v-if="member.attendance" class="text-emerald-700">Hadir · {{ formatDateTime(member.attendance.attended_at) }}</span><span v-else class="text-slate-500">Belum dikonfirmasi</span></td>
                            <td class="px-5 py-3">
                                <template v-if="member.attendance">
                                    <p class="text-xs text-slate-600">Terkumpul {{ formatRupiah(member.attendance.fee_paid_amount) }}</p>
                                    <p class="mt-1 text-xs font-semibold" :class="member.attendance.has_paid_fee ? 'text-emerald-700' : 'text-amber-700'">{{ member.attendance.has_paid_fee ? 'Sudah membayar' : 'Belum membayar' }}</p>
                                    <details v-if="member.attendance.fee_payments?.length" class="mt-1 text-xs text-slate-500"><summary class="cursor-pointer">Riwayat pembayaran</summary><ul class="mt-1 space-y-1"><li v-for="payment in member.attendance.fee_payments" :key="payment.id">{{ formatRupiah(payment.amount) }} · {{ formatDateTime(payment.paid_at) }}</li></ul></details>
                                </template><span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="px-5 py-3">
                                <button v-if="!member.attendance" :disabled="!canMarkAttendance()" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40" @click="markAttendance(member)">{{ canMarkAttendance() ? 'Konfirmasi hadir' : 'Tersedia saat hari latihan' }}</button>
                                <form v-else class="flex items-center gap-2" @submit.prevent="recordSessionFee(member)"><input v-model="feeAmounts[member.id]" type="number" min="1" step="1" placeholder="Nominal" required class="w-28 rounded-lg border border-slate-300 px-2.5 py-2 text-xs"><button class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Catat iuran</button></form>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold">Evaluasi latihan hari ini</h2>
                    <p class="mt-1 text-sm text-slate-500">Catatan pelatih ini akan ikut tampil pada rekap PNG.</p>
                </div>
                <button type="button" :disabled="savingEvaluation" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50" @click="saveEvaluation">{{ savingEvaluation ? 'Menyimpan...' : 'Simpan evaluasi' }}</button>
            </div>
            <textarea v-model="evaluationDraft" rows="5" maxlength="10000" placeholder="Tuliskan evaluasi keseluruhan latihan, hal yang berjalan baik, dan yang perlu diperbaiki." class="mt-4 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm leading-6 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100"></textarea>
        </section>
    </div>
</template>
