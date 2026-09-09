<template>
    <div class="min-h-screen bg-slate-900 text-slate-100">
        <AppShell title="Gestión de Suscripciones" accent="emerald">
            <!-- Tabs de filtro -->
            <div class="flex flex-wrap gap-2 mb-6">
                <button
                    v-for="t in tabs"
                    :key="t.value"
                    @click="setStatus(t.value)"
                    :class="[
                        'px-3.5 py-1.5 rounded-lg text-sm font-semibold border transition',
                        currentStatus === t.value
                            ? 'bg-indigo-500 border-indigo-500 text-white'
                            : 'bg-slate-800 border-slate-700 text-slate-300 hover:bg-slate-700'
                    ]"
                >
                    {{ t.label }}
                    <span v-if="counts[t.value] !== undefined" class="ml-1.5 text-xs opacity-80">
                        ({{ counts[t.value] }})
                    </span>
                </button>
                <button
                    @click="loadAll"
                    class="ml-auto px-3.5 py-1.5 rounded-lg text-sm font-semibold bg-slate-800 border border-slate-700 text-slate-300 hover:bg-slate-700"
                >
                    ↻ Refrescar
                </button>
            </div>

            <div v-if="loading" class="text-slate-400">Cargando solicitudes…</div>

            <div v-else-if="requests.length === 0" class="p-8 text-center text-slate-400 border border-dashed border-slate-700 rounded-2xl">
                No hay solicitudes {{ currentStatus ? `con estado "${currentStatus}"` : '' }}.
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="r in requests"
                    :key="r.id"
                    class="p-5 rounded-2xl border bg-slate-800/50"
                    :class="r.status === 'pending' ? 'border-amber-500/40' : 'border-slate-700'"
                >
                    <div class="flex items-start justify-between flex-wrap gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs text-slate-400 tabular-nums">#{{ r.id }}</span>
                                <span
                                    class="text-xs px-2 py-0.5 rounded-full font-semibold"
                                    :class="statusClass(r.status)"
                                >
                                    {{ statusLabel(r.status) }}
                                </span>
                                <h3 class="text-base font-bold text-white">
                                    Plan {{ r.plan.name }} · {{ r.plan.formatted_price }}
                                </h3>
                            </div>

                            <div class="mt-2 grid sm:grid-cols-2 gap-x-6 gap-y-1 text-sm">
                                <p>
                                    <span class="text-slate-400">Usuario:</span>
                                    <span class="text-white font-semibold">{{ r.user?.name }}</span>
                                    <span class="text-slate-500 ml-1">({{ r.user?.email }})</span>
                                </p>
                                <p>
                                    <span class="text-slate-400">Solicitado:</span>
                                    <span class="text-white">{{ formatDate(r.requested_at) }}</span>
                                </p>
                                <p v-if="r.specialties.length > 0" class="sm:col-span-2">
                                    <span class="text-slate-400">Materias pedidas:</span>
                                    <span class="text-emerald-300">{{ r.specialties.map(s => s.name).join(', ') }}</span>
                                </p>
                                <p v-else-if="r.plan.is_unlimited" class="sm:col-span-2">
                                    <span class="text-emerald-300">Full: acceso a todas las materias</span>
                                </p>
                                <p v-if="r.notes" class="sm:col-span-2">
                                    <span class="text-slate-400">Nota del usuario:</span>
                                    <span class="text-slate-200 italic">"{{ r.notes }}"</span>
                                </p>
                                <p v-if="r.rejection_reason" class="sm:col-span-2">
                                    <span class="text-slate-400">Motivo de rechazo:</span>
                                    <span class="text-rose-300">{{ r.rejection_reason }}</span>
                                </p>
                                <p v-if="r.approved_by" class="sm:col-span-2">
                                    <span class="text-slate-400">{{ r.status === 'rejected' ? 'Rechazado' : 'Aprobado' }} por:</span>
                                    <span class="text-white">{{ r.approved_by.name }}</span>
                                    <span class="text-slate-500 ml-1">el {{ formatDate(r.approved_at) }}</span>
                                </p>
                            </div>
                        </div>

                        <!-- Acciones según estado -->
                        <div v-if="r.status === 'pending'" class="flex flex-col gap-2 min-w-[180px]">
                            <button
                                @click="openApprove(r)"
                                class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-bold text-sm transition"
                            >
                                ✅ Aprobar
                            </button>
                            <button
                                @click="openReject(r)"
                                class="px-4 py-2 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 text-rose-200 border border-rose-500/40 font-semibold text-sm transition"
                            >
                                ❌ Rechazar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </AppShell>

        <!-- Modal aprobar -->
        <Teleport to="body">
            <div
                v-if="approveRequest"
                class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
                @click.self="closeApprove"
            >
                <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-lg w-full p-6 shadow-2xl">
                    <h3 class="text-xl font-bold text-white mb-1">Aprobar solicitud #{{ approveRequest.id }}</h3>
                    <p class="text-sm text-slate-400 mb-4">
                        {{ approveRequest.user?.name }} · Plan {{ approveRequest.plan.name }}
                    </p>

                    <!-- Si el plan no es Full, elegir specialties -->
                    <div v-if="!approveRequest.plan.is_unlimited" class="mb-4">
                        <label class="block text-sm font-semibold text-slate-300 mb-2">
                            Materias a habilitar (máx. {{ approveRequest.plan.max_specialties }})
                        </label>
                        <div class="space-y-2 max-h-60 overflow-y-auto">
                            <label
                                v-for="sp in sub.specialties"
                                :key="sp.id"
                                class="flex items-center gap-2 p-2.5 rounded-lg border bg-slate-900/50 cursor-pointer hover:bg-slate-900"
                                :class="approveForm.specialty_ids.includes(sp.id) ? 'border-emerald-500/60 bg-emerald-500/10' : 'border-slate-700'"
                            >
                                <input
                                    type="checkbox"
                                    :value="sp.id"
                                    v-model="approveForm.specialty_ids"
                                    class="rounded text-emerald-500 focus:ring-emerald-500 focus:ring-offset-0 bg-slate-700 border-slate-600"
                                />
                                <span class="text-sm text-white">{{ sp.name }}</span>
                                <span class="text-xs text-slate-500 ml-auto">{{ sp.code }}</span>
                            </label>
                        </div>
                    </div>
                    <p v-else class="mb-4 text-sm text-emerald-300">
                        Plan Full: el usuario tendrá acceso a TODAS las especialidades automáticamente.
                    </p>

                    <label class="block text-sm font-semibold text-slate-300 mb-2">Monto cobrado (ARS)</label>
                    <input
                        v-model.number="approveForm.amount_paid"
                        type="number"
                        min="0"
                        class="w-full mb-4 px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none"
                    />

                    <label class="block text-sm font-semibold text-slate-300 mb-2">Nota interna (opcional)</label>
                    <textarea
                        v-model="approveForm.notes"
                        rows="2"
                        maxlength="500"
                        class="w-full mb-4 px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none"
                        placeholder="Ej: Transferencia confirmada por mail"
                    />

                    <div v-if="sub.error" class="mb-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm">
                        {{ sub.error }}
                    </div>

                    <div class="flex gap-2 justify-end">
                        <button
                            @click="closeApprove"
                            class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm font-semibold transition"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="confirmApprove"
                            :disabled="!canApprove || sub.submitting"
                            class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 disabled:opacity-40 disabled:cursor-not-allowed text-slate-900 font-bold text-sm transition"
                        >
                            {{ sub.submitting ? 'Aprobando…' : 'Confirmar aprobación' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal rechazar -->
        <Teleport to="body">
            <div
                v-if="rejectRequest"
                class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
                @click.self="closeReject"
            >
                <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl">
                    <h3 class="text-xl font-bold text-white mb-1">Rechazar solicitud #{{ rejectRequest.id }}</h3>
                    <p class="text-sm text-slate-400 mb-4">
                        {{ rejectRequest.user?.name }} · Plan {{ rejectRequest.plan.name }}
                    </p>
                    <label class="block text-sm font-semibold text-slate-300 mb-2">Motivo (se le muestra al usuario)</label>
                    <textarea
                        v-model="rejectForm.reason"
                        rows="3"
                        minlength="3"
                        maxlength="500"
                        class="w-full mb-4 px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white focus:border-rose-500 focus:ring-1 focus:ring-rose-500 outline-none"
                        placeholder="Ej: Esperá a confirmar la transferencia por mail antes de enviar el comprobante"
                    />
                    <div v-if="sub.error" class="mb-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm">
                        {{ sub.error }}
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button
                            @click="closeReject"
                            class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm font-semibold transition"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="confirmReject"
                            :disabled="rejectForm.reason.length < 3 || sub.submitting"
                            class="px-4 py-2 rounded-lg bg-rose-500 hover:bg-rose-400 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold text-sm transition"
                        >
                            {{ sub.submitting ? 'Rechazando…' : 'Confirmar rechazo' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import AppShell from '@/components/AppShell.vue';
import { useSubscriptionStore, type PlanRequest } from '@/stores/subscription';

const sub = useSubscriptionStore();

const tabs = [
    { label: 'Pendientes', value: 'pending' },
    { label: 'Activas',    value: 'active' },
    { label: 'Rechazadas', value: 'rejected' },
    { label: 'Todas',      value: '' },
];
const currentStatus = ref<string>('pending');
const loading = ref(false);
const requests = ref<any[]>([]); // con .user incluido
const counts = reactive<Record<string, number>>({});

const approveRequest = ref<any | null>(null);
const approveForm = reactive({ specialty_ids: [] as number[], amount_paid: 0, notes: '' });

const rejectRequest = ref<any | null>(null);
const rejectForm = reactive({ reason: '' });

const canApprove = computed(() => {
    if (!approveRequest.value) return false;
    if (approveRequest.value.plan.is_unlimited) return true;
    return approveForm.specialty_ids.length > 0
        && approveForm.specialty_ids.length <= approveRequest.value.plan.max_specialties;
});

onMounted(async () => {
    if (sub.plans.length === 0) await sub.fetchPlans();
    await loadAll();
});

async function loadAll() {
    loading.value = true;
    try {
        // Carga todos los estados para los counts
        const all = await sub.adminListRequests();
        counts.pending  = all.filter((r: any) => r.status === 'pending').length;
        counts.active   = all.filter((r: any) => r.status === 'active').length;
        counts.rejected = all.filter((r: any) => r.status === 'rejected').length;
        counts['']       = all.length;

        // Lista filtrada
        await loadCurrent();
    } finally {
        loading.value = false;
    }
}

async function loadCurrent() {
    loading.value = true;
    try {
        const data = await sub.adminListRequests(currentStatus.value || undefined);
        requests.value = data;
    } finally {
        loading.value = false;
    }
}

function setStatus(value: string) {
    currentStatus.value = value;
    loadCurrent();
}

function openApprove(r: any) {
    approveRequest.value = r;
    approveForm.specialty_ids = r.specialties.map((s: any) => s.id);
    approveForm.amount_paid = r.plan.price;
    approveForm.notes = '';
    sub.error = null;
}

function closeApprove() {
    approveRequest.value = null;
}

async function confirmApprove() {
    if (!approveRequest.value) return;
    try {
        await sub.adminApprove(approveRequest.value.id, {
            specialty_ids: approveRequest.value.plan.is_unlimited ? undefined : approveForm.specialty_ids,
            amount_paid: approveForm.amount_paid,
            notes: approveForm.notes || undefined,
        });
        closeApprove();
        await loadAll();
    } catch {
        // error en el store
    }
}

function openReject(r: any) {
    rejectRequest.value = r;
    rejectForm.reason = '';
    sub.error = null;
}

function closeReject() {
    rejectRequest.value = null;
}

async function confirmReject() {
    if (!rejectRequest.value) return;
    try {
        await sub.adminReject(rejectRequest.value.id, rejectForm.reason);
        closeReject();
        await loadAll();
    } catch {
        // error en el store
    }
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function statusClass(status: string): string {
    switch (status) {
        case 'active':    return 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
        case 'pending':   return 'bg-amber-500/20 text-amber-300 border border-amber-500/30';
        case 'rejected':  return 'bg-rose-500/20 text-rose-300 border border-rose-500/30';
        case 'cancelled': return 'bg-slate-600/30 text-slate-300 border border-slate-500/30';
        case 'expired':   return 'bg-slate-600/30 text-slate-400 border border-slate-500/30';
        default:          return 'bg-slate-600/30 text-slate-300 border border-slate-500/30';
    }
}

function statusLabel(status: string): string {
    switch (status) {
        case 'active':    return '✅ Activa';
        case 'pending':   return '⏳ Pendiente';
        case 'rejected':  return '❌ Rechazada';
        case 'cancelled': return 'Cancelada';
        case 'expired':   return 'Vencida';
        default:          return status;
    }
}
</script>
