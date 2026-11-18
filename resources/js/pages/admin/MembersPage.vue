<script setup>
import { onMounted, ref } from "vue";
import { api } from "../../lib/api";
import { useFeedback } from "../../lib/feedback";

const { notify, confirmAction } = useFeedback();

const members = ref(null);
const name = ref("");
const memberCode = ref("");
const birthDate = ref("");
const phone = ref("");
const hasJersey = ref("false");
const initialRegistrationPayment = ref("0");
const importFile = ref(null);
const importInput = ref(null);
const search = ref("");
const currentSearch = ref("");
const loading = ref(true);
const submitting = ref(false);
const importing = ref(false);
const paymentAmounts = ref({});

function formatRupiah(amount) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(amount ?? 0);
}

async function load(url = null) {
    loading.value = true;

    try {
        const query = currentSearch.value
            ? `?search=${encodeURIComponent(currentSearch.value)}`
            : "";
        const data = await api(url ?? `/admin/absensi/api/anggota${query}`);
        members.value = data.members;
    } catch (requestError) {
        notify("error", requestError.message);
    } finally {
        loading.value = false;
    }
}

function searchMembers() {
    currentSearch.value = search.value.trim();
    load();
}

async function addMember() {
    submitting.value = true;
    try {
        const data = await api("/admin/absensi/api/anggota", {
            method: "POST",
            body: {
                name: name.value,
                member_code: memberCode.value,
                birth_date: birthDate.value || null,
                phone: phone.value || null,
                has_jersey: hasJersey.value === "true",
                registration_payment_amount: Number(
                    initialRegistrationPayment.value || 0,
                ),
            },
        });
        notify("success", data.message);
        name.value = "";
        memberCode.value = "";
        birthDate.value = "";
        phone.value = "";
        hasJersey.value = "false";
        initialRegistrationPayment.value = "0";
        currentSearch.value = "";
        search.value = "";
        await load();
    } catch (requestError) {
        notify("error", requestError.message);
    } finally {
        submitting.value = false;
    }
}

function chooseImportFile(event) {
    importFile.value = event.target.files?.[0] ?? null;
}

async function importMembers() {
    if (!importFile.value) return;

    const confirmed = await confirmAction({
        title: "Impor daftar anggota?",
        message: `File ${importFile.value.name} akan diproses. Kode yang sudah digunakan atau baris yang tidak valid akan membatalkan seluruh impor.`,
        confirmLabel: "Impor sekarang",
    });

    if (!confirmed) return;

    importing.value = true;

    try {
        const formData = new FormData();
        formData.append("file", importFile.value);

        const data = await api("/admin/absensi/api/anggota/import", {
            method: "POST",
            body: formData,
        });

        notify("success", data.message);
        importFile.value = null;
        if (importInput.value) importInput.value.value = "";
        currentSearch.value = "";
        search.value = "";
        await load();
    } catch (requestError) {
        notify("error", requestError.message);
    } finally {
        importing.value = false;
    }
}

async function toggleStatus(member) {
    const activating = member.status !== "active";
    const confirmed = await confirmAction({
        title: `${activating ? "Aktifkan" : "Nonaktifkan"} anggota?`,
        message: activating
            ? `${member.name} dapat kembali dimasukkan pada pencatatan kehadiran admin.`
            : `${member.name} tidak dapat melakukan absensi selama statusnya nonaktif.`,
        confirmLabel: activating ? "Aktifkan" : "Nonaktifkan",
        variant: activating ? "primary" : "danger",
    });

    if (!confirmed) return;

    try {
        const status = member.status === "active" ? "inactive" : "active";
        const data = await api(
            `/admin/absensi/api/anggota/${member.id}/status`,
            {
                method: "PATCH",
                body: { status },
            },
        );
        notify("success", data.message);
        await load();
    } catch (requestError) {
        notify("error", requestError.message);
    }
}

async function updateJersey(member, value) {
    const hasJersey = value === "true";
    try {
        const data = await api(
            `/admin/absensi/api/anggota/${member.id}/jersey`,
            {
                method: "PATCH",
                body: { has_jersey: hasJersey },
            },
        );
        member.has_jersey = data.member.has_jersey;
        notify("success", data.message);
    } catch (requestError) {
        notify("error", requestError.message);
        await load();
    }
}

async function recordRegistrationPayment(member) {
    const amount = Number(paymentAmounts.value[member.id]);
    if (!Number.isInteger(amount) || amount < 1) {
        notify("error", "Masukkan jumlah cicilan yang valid.");
        return;
    }

    try {
        const data = await api(
            `/admin/absensi/api/anggota/${member.id}/pembayaran-pendaftaran`,
            {
                method: "POST",
                body: { amount },
            },
        );
        member.registration_paid = data.registration_paid;
        member.registration_balance = data.registration_balance;
        member.registration_fee_payments = data.registration_payments;
        paymentAmounts.value[member.id] = "";
        notify("success", data.message);
    } catch (requestError) {
        notify("error", requestError.message);
    }
}

onMounted(() => load());
</script>

<template>
    <div class="space-y-6">
        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"
        >
            <h2 class="text-lg font-bold">
                Tambahkan anggota ke daftar absensi
            </h2>
            <p class="mt-1 text-sm leading-6 text-slate-500">
                Isi data anggota resmi klub. Status jersey dapat diperbarui
                kapan saja dan cicilan pendaftaran dicatat sebagai riwayat
                terpisah.
            </p>

            <form
                class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                @submit.prevent="addMember"
            >
                <label class="block"
                    ><span class="mb-2 block text-sm font-semibold"
                        >Nama anggota <span class="text-rose-600">*</span></span
                    ><input
                        v-model.trim="name"
                        required
                        maxlength="255"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100"
                /></label>
                <label class="block"
                    ><span class="mb-2 block text-sm font-semibold"
                        >Kode anggota <span class="text-rose-600">*</span></span
                    ><input
                        v-model.trim="memberCode"
                        required
                        maxlength="255"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100"
                /></label>
                <label class="block"
                    ><span class="mb-2 block text-sm font-semibold"
                        >Tanggal lahir
                        <span class="font-normal text-slate-400"
                            >(opsional)</span
                        ></span
                    ><input
                        v-model="birthDate"
                        type="date"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100" /></label
                ><label class="block"
                    ><span class="mb-2 block text-sm font-semibold"
                        >Nomor telepon
                        <span class="font-normal text-slate-400"
                            >(opsional)</span
                        ></span
                    ><input
                        v-model.trim="phone"
                        maxlength="32"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100" /></label
                ><label class="block"
                    ><span class="mb-2 block text-sm font-semibold"
                        >Kepemilikan jersey
                        <span class="text-rose-600">*</span></span
                    ><select
                        v-model="hasJersey"
                        required
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100"
                    >
                        <option disabled value="">Pilih status jersey</option>
                        <option value="true">Sudah punya</option>
                        <option value="false">Belum punya</option>
                    </select></label
                >
                <label class="block"
                    ><span class="mb-2 block text-sm font-semibold"
                        >Cicilan pendaftaran awal
                        <span class="font-normal text-slate-400"
                            >(opsional, maks Rp100.000)</span
                        ></span
                    ><input
                        v-model="initialRegistrationPayment"
                        type="number"
                        min="0"
                        max="100000"
                        step="1"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100"
                /></label>
                <div class="sm:col-span-2 lg:col-span-3">
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white hover:bg-slate-700 disabled:opacity-50"
                    >
                        {{ submitting ? "Menyimpan..." : "Simpan anggota" }}
                    </button>
                </div>
            </form>
        </section>

        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold">Impor anggota dari Excel</h2>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                        Unggah file .xlsx atau .csv dengan header Nama Anggota,
                        Kode Anggota, Tanggal Lahir, No Telepon, dan Memiliki
                        Jersey. Isi kode sesuai daftar resmi; tanggal bisa
                        ditulis YYYY-MM-DD atau sebagai tanggal Excel.
                    </p>
                </div>
                <a
                    href="/admin/absensi/api/anggota/template"
                    class="text-sm font-semibold text-orange-700 hover:text-orange-800"
                    >Unduh template CSV</a
                >
            </div>
            <form
                class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center"
                @submit.prevent="importMembers"
            >
                <input
                    ref="importInput"
                    type="file"
                    accept=".xlsx,.csv"
                    required
                    class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700"
                    @change="chooseImportFile"
                />
                <button
                    type="submit"
                    :disabled="!importFile || importing"
                    class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ importing ? "Mengimpor..." : "Impor Anggota" }}
                </button>
            </form>
            <p class="mt-2 text-xs text-slate-400">
                Maksimal 5.000 baris dan ukuran file 2 MB. Jika satu baris tidak
                valid atau kode sudah dipakai, tidak ada baris yang akan
                disimpan.
            </p>
        </section>

        <section
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
        >
            <div
                class="flex flex-wrap items-end justify-between gap-4 border-b border-slate-100 px-5 py-4"
            >
                <div>
                    <h2 class="font-bold">Daftar anggota</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Anggota nonaktif tidak dapat melakukan absensi.
                    </p>
                </div>
                <form
                    class="flex w-full gap-2 sm:w-auto"
                    @submit.prevent="searchMembers"
                >
                    <input
                        v-model.trim="search"
                        type="search"
                        placeholder="Cari nama anggota"
                        class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-orange-500 sm:w-56"
                    /><button
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold hover:bg-slate-50"
                    >
                        Cari
                    </button>
                </form>
            </div>

            <div v-if="loading" class="p-6 text-sm text-slate-500">
                Memuat daftar anggota...
            </div>
            <div
                v-else-if="!members?.data?.length"
                class="p-6 text-sm text-slate-500"
            >
                {{
                    currentSearch
                        ? "Anggota tidak ditemukan."
                        : "Belum ada anggota yang dimasukkan."
                }}
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead
                        class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"
                    >
                        <tr>
                            <th class="px-5 py-3">Nama</th>
                            <th class="px-5 py-3">Kode</th>
                            <th class="px-5 py-3">Tanggal lahir</th>
                            <th class="px-5 py-3">Telepon</th>
                            <th class="px-5 py-3">Jersey</th>
                            <th class="px-5 py-3">
                                Pendaftaran
                                {{
                                    members?.data?.[0]?.registration_year
                                }}
                                (Rp100.000/tahun)
                            </th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="member in members.data" :key="member.id">
                            <td class="px-5 py-3 font-semibold">
                                {{ member.name }}
                            </td>
                            <td class="px-5 py-3 text-slate-600">
                                {{ member.member_code || "—" }}
                            </td>
                            <td class="px-5 py-3 text-slate-600">
                                {{ member.birth_date || "—" }}
                            </td>
                            <td class="px-5 py-3 text-slate-600">
                                {{ member.phone || "—" }}
                            </td>
                            <td class="px-5 py-3">
                                <select
                                    :value="
                                        member.has_jersey ? 'true' : 'false'
                                    "
                                    class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"
                                    @change="
                                        updateJersey(
                                            member,
                                            $event.target.value,
                                        )
                                    "
                                >
                                    <option value="true">Sudah punya</option>
                                    <option value="false">Belum punya</option>
                                </select>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-xs text-slate-700">
                                    Dibayar
                                    {{ formatRupiah(member.registration_paid) }}
                                </p>
                                <p
                                    class="mt-1 text-xs font-semibold"
                                    :class="
                                        member.registration_balance
                                            ? 'text-amber-700'
                                            : 'text-emerald-700'
                                    "
                                >
                                    Sisa
                                    {{
                                        formatRupiah(
                                            member.registration_balance,
                                        )
                                    }}
                                </p>
                                <form
                                    v-if="member.registration_balance"
                                    class="mt-2 flex gap-1"
                                    @submit.prevent="
                                        recordRegistrationPayment(member)
                                    "
                                >
                                    <input
                                        v-model="paymentAmounts[member.id]"
                                        type="number"
                                        min="1"
                                        :max="member.registration_balance"
                                        placeholder="Jumlah"
                                        required
                                        class="w-24 rounded-md border border-slate-300 px-2 py-1 text-xs"
                                    /><button
                                        class="rounded-md bg-slate-900 px-2 py-1 text-xs font-semibold text-white"
                                    >
                                        Catat cicilan
                                    </button>
                                </form>
                                <details
                                    v-if="
                                        member.registration_fee_payments?.length
                                    "
                                    class="mt-2 text-xs text-slate-500"
                                >
                                    <summary class="cursor-pointer">
                                        Riwayat cicilan
                                    </summary>
                                    <ul class="mt-1 space-y-1">
                                        <li
                                            v-for="payment in member.registration_fee_payments"
                                            :key="payment.id"
                                        >
                                            {{ formatRupiah(payment.amount) }} ·
                                            {{
                                                new Date(
                                                    payment.paid_at,
                                                ).toLocaleDateString("id-ID")
                                            }}
                                        </li>
                                    </ul>
                                </details>
                            </td>
                            <td class="px-5 py-3">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-bold"
                                    :class="
                                        member.status === 'active'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-slate-100 text-slate-600'
                                    "
                                    >{{
                                        member.status === "active"
                                            ? "Anggota Aktif"
                                            : "Anggota Nonaktif"
                                    }}</span
                                >
                            </td>
                            <td class="px-5 py-3">
                                <button
                                    class="text-xs font-bold"
                                    :class="
                                        member.status === 'active'
                                            ? 'text-rose-700 hover:text-rose-900'
                                            : 'text-emerald-700 hover:text-emerald-900'
                                    "
                                    @click="toggleStatus(member)"
                                >
                                    {{
                                        member.status === "active"
                                            ? "Nonaktifkan"
                                            : "Aktifkan"
                                    }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="members?.data?.length"
                class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm text-slate-500"
            >
                <span
                    >Halaman {{ members.current_page }} dari
                    {{ members.last_page }}</span
                >
                <div class="flex gap-2">
                    <button
                        :disabled="!members.prev_page_url"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40"
                        @click="load(members.prev_page_url)"
                    >
                        Sebelumnya</button
                    ><button
                        :disabled="!members.next_page_url"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40"
                        @click="load(members.next_page_url)"
                    >
                        Berikutnya
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
