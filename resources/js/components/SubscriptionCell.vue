<template>
    <div class="min-w-[160px] max-w-[220px]">
        <!-- Suscripción activa: nombre + días restantes + barra -->
        <div
            v-if="user.active_subscription"
            :title="activeTooltip"
            class="cursor-help"
        >
            <div class="flex items-center gap-1.5">
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 uppercase">
                    {{ user.active_subscription.plan?.name ?? 'Activo' }}
                </span>
                <span
                    v-if="renewsBadge"
                    :class="['px-1.5 py-0.5 rounded text-[10px] font-semibold', renewsBadge.class]"
                >{{ renewsBadge.text }}</span>
            </div>
            <p class="text-[11px] text-slate-300 mt-1 leading-tight">
                <span class="font-semibold" :class="daysLeftClass">
                    {{ daysLeftText }}
                </span>
                <span class="text-slate-500"> · vence </span>
                <span class="text-slate-300">{{ formatShortDate(user.active_subscription.expires_at) }}</span>
            </p>
            <!-- Barra de progreso del periodo contratado -->
            <div class="mt-1 h-1 rounded-full bg-slate-700/80 overflow-hidden">
                <div
                    :class="['h-full transition-all', progressBarClass]"
                    :style="{ width: progressPct + '%' }"
                ></div>
            </div>
        </div>

        <!-- Solicitud pendiente -->
        <div
            v-else-if="user.latest_subscription?.status === 'pending'"
            :title="`Solicitud pendiente desde ${formatDate(user.latest_subscription.requested_at)}`"
            class="cursor-help"
        >
            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 border border-amber-500/40 text-amber-300 uppercase">
                ⏳ Pendiente
            </span>
            <p class="text-[11px] text-slate-400 mt-1">
                {{ user.latest_subscription.plan?.name ?? 'Plan' }} · desde {{ formatShortDate(user.latest_subscription.requested_at) }}
            </p>
        </div>

        <!-- Suscripción expirada -->
        <div
            v-else-if="user.latest_subscription && ['expired', 'cancelled'].includes(user.latest_subscription.status)"
            :title="`${user.latest_subscription.status === 'cancelled' ? 'Cancelado' : 'Expirado'} el ${formatDate(user.latest_subscription.expires_at)}`"
            class="cursor-help"
        >
            <span :class="['px-1.5 py-0.5 rounded text-[10px] font-bold uppercase border', expiredBadgeClass]">
                {{ user.latest_subscription.status === 'cancelled' ? '✕ Cancelado' : '⌛ Expirado' }}
            </span>
            <p class="text-[11px] text-slate-500 mt-1">
                {{ user.latest_subscription.plan?.name ?? 'Plan' }} · {{ formatShortDate(user.latest_subscription.expires_at) }}
            </p>
        </div>

        <!-- Sin plan (Free legacy / premium sin subscripcion) -->
        <div v-else :title="user.is_premium ? 'Flag premium legacy sin subscripcion real' : 'Sin plan contratado'">
            <span :class="['px-1.5 py-0.5 rounded text-[10px] font-bold uppercase border', freeBadgeClass]">
                {{ user.is_premium ? '⚡ Free' : 'Free' }}
            </span>
            <p v-if="user.is_premium" class="text-[10px] text-amber-400 mt-1">⚠ sin subscripcion activa</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

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
    amount_paid: number | null;
    currency: string;
    cancelled_at: string | null;
    notes: string | null;
    specialties: Array<{ id: number; name: string; code: string }>;
}

interface User {
    id: number;
    name: string;
    is_premium: boolean;
    active_subscription: SubInfo | null;
    latest_subscription: SubInfo | null;
}

const props = defineProps<{ user: User }>();

const active = computed(() => props.user.active_subscription);

const daysLeftText = computed(() => {
    if (!active.value) return '';
    const d = active.value.days_remaining;
    if (d === 0) return 'Vence hoy';
    if (d === 1) return '1 día restante';
    return `${d} días restantes`;
});

const daysLeftClass = computed(() => {
    if (!active.value) return '';
    const d = active.value.days_remaining;
    if (d <= 3) return 'text-rose-300';
    if (d <= 7) return 'text-amber-300';
    return 'text-slate-100';
});

const progressPct = computed(() => active.value?.progress_pct ?? 0);

const progressBarClass = computed(() => {
    if (!active.value) return 'bg-slate-500';
    const d = active.value.days_remaining;
    if (d <= 3) return 'bg-rose-500';
    if (d <= 7) return 'bg-amber-500';
    return 'bg-emerald-500';
});

const renewsBadge = computed(() => {
    if (!active.value) return null;
    const days = active.value.days_remaining;
    const total = active.value.days_total ?? 0;
    // Si queda menos del 20% del periodo contratado, marcar como "renueva pronto"
    if (total > 0 && days / total < 0.2) {
        return { text: 'renueva pronto', class: 'bg-amber-500/20 border border-amber-500/30 text-amber-300' };
    }
    return null;
});

const expiredBadgeClass = computed(() =>
    'bg-slate-700 border-slate-600 text-slate-400',
);

const freeBadgeClass = computed(() => {
    if (props.user.is_premium) {
        return 'bg-yellow-500/20 border border-yellow-500/30 text-yellow-300';
    }
    return 'bg-slate-700 border-slate-600 text-slate-400';
});

const activeTooltip = computed(() => {
    if (!active.value) return '';
    const a = active.value;
    const lines: string[] = [];
    if (a.plan) {
        lines.push(`Plan: ${a.plan.name}`);
        if (a.plan.is_unlimited) {
            lines.push('Acceso: todas las especialidades');
        } else if (a.specialties?.length) {
            lines.push(`Especialidades: ${a.specialties.map(s => s.name).join(', ')}`);
        }
        lines.push(`Duración: ${a.plan.duration_days} días`);
    }
    if (a.started_at) lines.push(`Inicio: ${formatDate(a.started_at)}`);
    if (a.expires_at) lines.push(`Vence: ${formatDate(a.expires_at)}`);
    lines.push(`Días restantes: ${a.days_remaining}`);
    if (a.amount_paid && a.currency) {
        lines.push(`Pagado: ${a.currency} ${a.amount_paid.toLocaleString('es-AR')}`);
    }
    if (a.payment_provider) {
        lines.push(`Proveedor: ${a.payment_provider}`);
    }
    if (a.notes) lines.push(`Nota: ${a.notes}`);
    return lines.join('\n');
});

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatShortDate(iso: string | null): string {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit' });
}
</script>
