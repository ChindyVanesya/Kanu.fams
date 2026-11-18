<script setup>
import { computed, onMounted, ref } from "vue";
import { api } from "../lib/api";
import { useFeedback } from "../lib/feedback";

const { notify } = useFeedback();

const session = ref(null);
const members = ref([]);
const memberId = ref("");
const loading = ref(true);
const submitting = ref(false);

const sessionDate = computed(() => {
    const trainingDate = session.value?.training_date;

    if (!trainingDate) return "";

    // Laravel may serialize a date cast as either YYYY-MM-DD or an ISO
    // timestamp. Keep only the calendar date before constructing a UTC date.
    const dateOnly = String(trainingDate).slice(0, 10);

    if (!/^\d{4}-\d{2}-\d{2}$/.test(dateOnly)) return "Tanggal latihan";

    const date = new Date(`${dateOnly}T00:00:00Z`);

    if (Number.isNaN(date.getTime())) return "Tanggal latihan";

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "full",
        timeZone: "UTC",
    }).format(date);
});

async function loadAttendancePage() {
    loading.value = true;
    try {
        const data = await api("/absensi/data");
        session.value = data.session;
        members.value = data.members;
    } catch (requestError) {
        notify("error", requestError.message);
    } finally {
        loading.value = false;
    }
}

async function confirmAttendance() {
    if (!memberId.value) return;

    submitting.value = true;
    try {
        const data = await api("/absensi/konfirmasi", {
            method: "POST",
            body: { member_id: Number(memberId.value) },
        });

        notify("success", data.message);
        memberId.value = "";

        if (session.value) {
            session.value.attendances_count += 1;
        }
    } catch (requestError) {
        notify("error", requestError.message);
    } finally {
        submitting.value = false;
    }
}

onMounted(loadAttendancePage);
</script>

<template>
    <main
        class="min-h-screen bg-slate-950 px-4 py-8 text-slate-100 sm:px-6 sm:py-14"
    >
        <div class="mx-auto max-w-lg">
            <header class="mb-8 text-center">
                <div
                    class="mx-auto mb-4 grid size-14 place-items-center rounded-2xl bg-orange-400 text-xl font-black text-slate-950"
                >
                    KN
                </div>
                <p
                    class="text-xs font-bold uppercase tracking-[0.28em] text-orange-300"
                >
                    Kapita Nusantara
                </p>
                <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                    Absensi latihan
                </h1>
                <p class="mt-3 text-sm leading-6 text-slate-400">
                    Pilih nama Anda dan konfirmasi kehadiran untuk sesi yang
                    sedang dibuka.
                </p>
            </header>

            <section
                class="rounded-3xl border border-white/10 bg-white/[0.06] p-5 shadow-2xl shadow-black/20 sm:p-7"
            >
                <div v-if="loading" class="space-y-4" aria-live="polite">
                    <div
                        class="h-5 w-2/3 animate-pulse rounded bg-white/10"
                    ></div>
                    <div
                        class="h-12 animate-pulse rounded-xl bg-white/10"
                    ></div>
                    <div
                        class="h-12 animate-pulse rounded-xl bg-white/10"
                    ></div>
                </div>

                <template v-else-if="!session">
                    <div
                        class="rounded-2xl border border-amber-300/20 bg-amber-300/10 p-5 text-center"
                    >
                        <span class="text-2xl" aria-hidden="true">◷</span>
                        <h2 class="mt-2 font-semibold text-amber-100">
                            Absensi belum dibuka
                        </h2>
                        <p class="mt-1 text-sm leading-6 text-amber-100/70">
                            Silakan minta admin membuka sesi latihan terlebih
                            dahulu.
                        </p>
                    </div>
                    <button
                        class="mt-4 w-full rounded-xl border border-white/10 px-4 py-3 text-sm font-semibold text-slate-300 transition hover:bg-white/5"
                        @click="loadAttendancePage"
                    >
                        Periksa lagi
                    </button>
                </template>

                <template v-else>
                    <div
                        class="mb-6 rounded-2xl border border-emerald-300/20 bg-emerald-300/10 p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p
                                    class="text-xs font-semibold uppercase tracking-wider text-emerald-200/70"
                                >
                                    Sesi latihan terbuka
                                </p>
                                <p class="mt-1 font-semibold text-emerald-100">
                                    {{ session.title || "Latihan Basket" }}
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-emerald-300/15 px-3 py-1 text-xs font-bold text-emerald-200"
                                >BUKA</span
                            >
                        </div>
                        <p class="mt-3 text-sm text-emerald-100/80">
                            {{ sessionDate }}
                        </p>
                        <p
                            v-if="session.notes"
                            class="mt-1 text-sm text-emerald-100/60"
                        >
                            {{ session.notes }}
                        </p>
                    </div>

                    <div
                        v-if="members.length === 0"
                        class="rounded-xl border border-white/10 p-4 text-sm leading-6 text-slate-300"
                    >
                        Belum ada anggota aktif di daftar. Minta admin
                        menambahkan data anggota terlebih dahulu.
                    </div>
                    <form
                        v-else
                        class="space-y-4"
                        @submit.prevent="confirmAttendance"
                    >
                        <label class="block">
                            <span
                                class="mb-2 block text-sm font-semibold text-slate-200"
                                >Nama anggota</span
                            >
                            <select
                                v-model="memberId"
                                required
                                class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3.5 text-base text-white outline-none transition focus:border-orange-300 focus:ring-2 focus:ring-orange-300/20"
                            >
                                <option disabled value="">
                                    Pilih nama Anda
                                </option>
                                <option
                                    v-for="member in members"
                                    :key="member.id"
                                    :value="member.id"
                                >
                                    {{ member.name }}
                                </option>
                            </select>
                        </label>

                        <button
                            type="submit"
                            :disabled="!memberId || submitting"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-orange-400 px-4 py-3.5 font-bold text-slate-950 transition hover:bg-orange-300 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span
                                v-if="submitting"
                                class="size-4 animate-spin rounded-full border-2 border-slate-950/30 border-t-slate-950"
                            ></span>
                            {{
                                submitting
                                    ? "Menyimpan kehadiran..."
                                    : "Konfirmasi Kehadiran"
                            }}
                        </button>
                    </form>

                    <p class="mt-5 text-center text-xs text-slate-500">
                        Jumlah kehadiran sesi ini:
                        {{ session.attendances_count ?? 0 }} anggota
                    </p>
                </template>
            </section>

            <footer class="mt-6 text-center">
                <a
                    href="/admin/login"
                    class="text-xs text-slate-500 transition hover:text-slate-300"
                    >Masuk sebagai admin</a
                >
            </footer>
        </div>
    </main>
</template>
