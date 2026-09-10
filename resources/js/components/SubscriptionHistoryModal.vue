<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
            @click.self="$emit('close')"
        >
            <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-3xl w-full p-6 max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between mb-4 gap-3">
                    <div>
                        <h2 class="text-xl font-bold text-slate-100">Historial de suscripciones</h2>
                        <p v-if="user" class="text-sm text-slate-400 mt-0.5">
                            {{ user.name }} · <span class="font-mono">{{ user.email }}</span>
                        </p>
                    </div>
                    <button
                        @click="$emit('close')"
                        class="text-slate-400 hover:text-slate-200 text-2xl leading-none p-1"
                    >×</button>
                </div>

                <div v-if="loading" class="text-center py-8 text-slate-400">Cargando…</div>

                <div
                    v-else-if="!subscriptions.length"
                    class="text-center py-8 text-slate-500 border border-dashed border-slate-700 rounded-xl"
                >
                    Este usuario no tiene suscripciones registradas.
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="s in subscriptions"
                        :key="s.id"
                        class="p-4 rounded-xl border bg-slate-900/40"
                        :class="rowBorderClass(s)"
                    >
                        <div class="flex items-start justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold uppercase', statusBadgeClass(s)]">
                                    {{ statusLabel(s) }}
                                </span>
                                <h3 class="text-base font-bold text-slate-100">
                                    {{ s.plan?.name ?? '—' }}
                                </h3>
                                <span
                                    v-if="s.plan?.is_unlimited"
                                    class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 font-semibold"
                                >TODAS</span>
                            </div>
                            <div v-if="s.amount_paid && s.currency" class="text-sm text-slate-200 font-semibold">
                                {{ s.currency }} {{ s.amount_paid.toLocaleString('es-AR') }}
                            </div>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-x-6 gap-y-1 mt-2 text-sm">
                            <p v-if="s.started_at">
                                <span class="text-slate-400">Inicio:</span>
                                <span class="text-slate-200">{{ formatDate(s.started_at) }}</span>
                            </p>
                            <p v-if="s.expires_at">
                                <span class="text-slate-400">Vence:</span>
                                <span class="text-slate-200">{{ formatDate(s.expires_at) }}</span>
                            </p>
                            <p v-if="s.status === 'active' && s.days_remaining !== undefined">
                                <span class="text-slate-400">Restantes:</span>
                                <span :class="daysLeftClass(s.days_remaining)">
                                    {{ s.days_remaining }} días
                                </span>
                            </p>
                            <p v-if="s.cancelled_at">
                                <span class="text-slate-400">Cancelada:</span>
                                <span class="text-rose-300">{{ formatDate(s.cancelled_at) }}</span>
                            </p>
                            <p v-if="s.payment_provider">
                                <span class="text-slate-400">Proveedor:</span>
                                <span class="text-slate-200">{{ s.payment_provider }}</span>
                                <span v-if="s.payment_reference" class="text-slate-500 text-xs ml-1">#{{ s.payment_reference }}</span>
                            </p>
                            <p v-if="s.approved_by">
                                <span class="text-slate-400">Aprobada por:</span>
                                <span class="text-slate-200">{{ s.approved_by.name }}</span>
                            </p>
                        </div>

                        <p
                            v-if="s.specialties && s.specialties.length"
                            class="text-xs text-slate-400 mt-2"
                        >
                            <span class="text-slate-500">Materias:</span>
                            {{ s.specialties.map(sp => sp.name).join(', ') }}
                        </p>

                        <p v-if="s.notes" class="text-xs italic text-slate-400 mt-2">
                            "{{ s.notes }}"
                        </p>
                    </div>
                </div>

                <div class="flex justify-end pt-4 mt-4 border-t border-slate-700">
                    <button
                        @click="$emit('close')"
                        class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-100 text-sm font-semibold"
                    >Cerrar</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';

interface PlanInfo {
    id: number;
    name: string;
    slug: string;
    duration_days: number;
    is_unlimited: boolean;
    includes_flashcards: boolean;
    price: number;
    currency: string;
}

interface SubInfo {
    id: number;
    status: 'active' | 'pending' | 'cancelled' | 'expired';
    plan: PlanInfo | null;
    started_at: string | null;
    expires_at: string | null;
    days_remaining: number;
    days_total: number | null;
    progress_pct: number;
    payment_provider: string | null;
    payment_reference: string | null;
    amount_paid: number | null;
    currency: string;
    cancelled_at: string | null;
    notes: string | null;
    specialties: Array<{ id: number; name: string; code: string }>;
    approved_by?: { id: number; name: string } | null;
}

interface UserInfo {
    id: number;
    name: string;
    email: string;
}

const props = defineProps<{
    open: boolean;
    user: UserInfo | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const subscriptions = ref<SubInfo[]>([]);
const loading = ref(false);

watch(
    () => [props.open, props.user?.id],
    async ([isOpen, userId]) => {
        if (isOpen && userId) {
            await load();
        } else {
            subscriptions.value = [];
        }
    },
    { immediate: true },
);

async function load() {
    if (!props.user) return;
    loading.value = true;
    try {
        const { data } = await axios.get(`/admin/users/${props.user.id}/subscriptions`);
        subscriptions.value = data.data ?? [];
    } catch (e) {
        subscriptions.value = [];
    } finally {
        loading.value = false;
    }
}

function statusLabel(s: SubInfo): string {
    switch (s.status) {
        case 'active':    return '✓ Activa';
        case 'pending':   return '⏳ Pendiente';
        case 'cancelled': return '✕ Cancelada';
        case 'expired':   return '⌛ Expirada';
        default:          return s.status;
    }
}

function statusBadgeClass(s: SubInfo): string {
    switch (s.status) {
        case 'active':    return 'bg-emerald-500/20 border border-emerald-500/40 text-emerald-300';
        case 'pending':   return 'bg-amber-500/20 border border-amber-500/40 text-amber-300';
        case 'cancelled': return 'bg-rose-500/20 border border-rose-500/40 text-rose-300';
        case 'expired':   return 'bg-slate-700 border border-slate-600 text-slate-400';
        default:          return 'bg-slate-700 text-slate-300';
    }
}

function rowBorderClass(s: SubInfo): string {
    switch (s.status) {
        case 'active':    return 'border-emerald-500/30';
        case 'pending':   return 'border-amber-500/30';
        case 'cancelled': return 'border-rose-500/30';
        case 'expired':   return 'border-slate-700';
        default:          return 'border-slate-700';
    }
}

function daysLeftClass(d: number): string {
    if (d <= 3) return 'text-rose-300 font-semibold';
    if (d <= 7) return 'text-amber-300 font-semibold';
    return 'text-emerald-300 font-semibold';
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
</script>
