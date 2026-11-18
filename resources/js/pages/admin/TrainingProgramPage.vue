<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '../../lib/api';
import { useFeedback } from '../../lib/feedback';

const { notify } = useFeedback();
const sessions = ref([]);
const selectedWeek = ref('');
const selectedSessionId = ref('');
const draft = ref({ training_focus: '', training_program: '' });
const loading = ref(true);
const saving = ref(false);

function parseDate(value) {
    const [year, month, day] = value.slice(0, 10).split('-').map(Number);
    return new Date(Date.UTC(year, month - 1, day));
}

function formatDate(value, style = 'long') {
    if (!value) return '—';
    return new Intl.DateTimeFormat('id-ID', { dateStyle: style, timeZone: 'UTC' }).format(parseDate(value));
}

const weeks = computed(() => {
    const groups = new Map();
    for (const session of sessions.value) {
        const date = parseDate(session.training_date);
        const monthStart = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1));
        const mondayOffset = (monthStart.getUTCDay() + 6) % 7;
        const weekNumber = Math.ceil((date.getUTCDate() + mondayOffset) / 7);
        const key = `${session.training_date.slice(0, 7)}-${weekNumber}`;
        if (!groups.has(key)) {
            const monthLabel = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(date);
            groups.set(key, { key, number: weekNumber, monthLabel, sessions: [] });
        }
        groups.get(key).sessions.push(session);
    }
    return [...groups.values()].map((week) => ({
        ...week,
        label: `${week.monthLabel} · Minggu ${week.number}`,
    }));
});

const weekSessions = computed(() => weeks.value.find((week) => week.key === selectedWeek.value)?.sessions || []);
const selectedSession = computed(() => sessions.value.find((session) => String(session.id) === String(selectedSessionId.value)) || null);

function selectWeek(weekKey) {
    selectedWeek.value = weekKey;
    const group = weeks.value.find((week) => week.key === weekKey);
    selectedSessionId.value = group?.sessions[0] ? String(group.sessions[0].id) : '';
}

async function load() {
    loading.value = true;
    try {
        const data = await api('/admin/absensi/api/program-sesi');
        sessions.value = data.sessions;
        const firstUnfilled = data.sessions.find((session) => !session.training_focus);
        if (firstUnfilled) {
            const date = parseDate(firstUnfilled.training_date);
            const monthStart = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1));
            const weekNumber = Math.ceil((date.getUTCDate() + (monthStart.getUTCDay() + 6) % 7) / 7);
            selectWeek(`${firstUnfilled.training_date.slice(0, 7)}-${weekNumber}`);
        } else if (weeks.value.length) {
            selectWeek(weeks.value[weeks.value.length - 1].key);
        }
    } catch (error) {
        notify('error', error.message);
    } finally {
        loading.value = false;
    }
}

watch(selectedSession, (session) => {
    draft.value = {
        training_focus: session?.training_focus || '',
        training_program: session?.training_program || '',
    };
});

async function saveProgram() {
    if (!selectedSession.value) return;
    saving.value = true;
    try {
        const data = await api(`/admin/absensi/api/sesi/${selectedSession.value.id}/program`, {
            method: 'PATCH',
            body: draft.value,
        });
        selectedSession.value.training_focus = data.session.training_focus;
        selectedSession.value.training_program = data.session.training_program;
        notify('success', data.message);
    } catch (error) {
        notify('error', error.message);
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h2 class="text-lg font-bold">Program latihan</h2>
            <p class="mt-1 text-sm leading-6 text-slate-500">Pilih minggu, lalu pilih pertemuannya. Fokus dan arahan hanya ditampilkan untuk sesi yang sedang dipilih.</p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <div v-if="loading" class="text-sm text-slate-500">Memuat sesi latihan...</div>
            <div v-else-if="weeks.length === 0" class="text-sm text-slate-500">Belum ada sesi latihan yang dijadwalkan. Buat sesi terlebih dahulu pada menu Sesi latihan.</div>
            <template v-else>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold">Minggu latihan</span>
                        <select :value="selectedWeek" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5" @change="selectWeek($event.target.value)">
                            <option v-for="week in weeks" :key="week.key" :value="week.key">{{ week.label }} · {{ week.sessions.length }} pertemuan</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold">Pertemuan</span>
                        <select v-model="selectedSessionId" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5">
                            <option v-for="session in weekSessions" :key="session.id" :value="String(session.id)">{{ formatDate(session.training_date, 'medium') }} · {{ session.title || 'Latihan Basket' }}</option>
                        </select>
                    </label>
                </div>

                <div v-if="selectedSession" class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="font-bold">{{ selectedSession.title || 'Latihan Basket' }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ formatDate(selectedSession.training_date) }}</p>
                        </div>
                        <a :href="`/admin/absensi/sesi/${selectedSession.id}`" class="text-sm font-semibold text-orange-700 hover:text-orange-800">Buka detail sesi →</a>
                    </div>
                </div>

                <form v-if="selectedSession" class="mt-5 space-y-4" @submit.prevent="saveProgram">
                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold">Fokus latihan <span class="text-rose-600">*</span></span>
                        <input v-model.trim="draft.training_focus" required maxlength="255" placeholder="Contoh: Dribble dan kontrol bola" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100">
                    </label>
                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold">Arahan program <span class="font-normal text-slate-400">(opsional)</span></span>
                        <textarea v-model.trim="draft.training_program" rows="5" maxlength="10000" placeholder="Tuliskan urutan latihan, durasi, atau arahan untuk pelatih yang menggantikan." class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100"></textarea>
                    </label>
                    <button type="submit" :disabled="saving" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50">{{ saving ? 'Menyimpan...' : 'Simpan program latihan' }}</button>
                </form>
            </template>
        </section>
    </div>
</template>
