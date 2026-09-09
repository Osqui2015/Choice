<template>
    <div class="min-h-screen bg-slate-900 text-slate-100">
        <Navbar />
        <main class="max-w-4xl mx-auto px-4 sm:px-6 py-8">
            <h1 class="text-3xl font-bold text-white mb-1">Mi suscripción</h1>
            <p class="text-slate-400 mb-8">Estado de tu plan y solicitudes</p>

            <div v-if="sub.loading" class="text-slate-400">Cargando…</div>

            <template v-else>
                <!-- Plan activo -->
                <section
                    v-if="sub.hasActive && sub.active"
                    class="mb-6 p-6 rounded-2xl border-2 border-emerald-500/40 bg-gradient-to-br from-emerald-500/10 to-cyan-500/5"
                >
                    <div class="flex items-start justify-between flex-wrap gap-4">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-2xl">✅</span>
                                <h2 class="text-xl font-bold text-white">
                                    Plan {{ sub.active.plan.name }} activo
                                </h2>
                                <span
                                    v-if="sub.active.is_unlimited"
                                    class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/30 text-emerald-200 border border-emerald-500/40"
                                >
                                    Acceso total
                                </span>
                            </div>
                            <p class="text-emerald-200/80 text-sm">
                                <span v-if="!sub.active.is_unlimited">
                                    Materias habilitadas: <strong>{{ sub.active.specialties.map(s => s.name).join(', ') }}</strong>
                                </span>
                                <span v-else>Todas las especialidades + flashcards</span>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-3xl font-bold text-white">{{ sub.active.days_remaining }}</p>
                            <p class="text-xs text-emerald-300/80">días restantes</p>
                        </div>
                    </div>

                    <!-- Barra de progreso -->
                    <div class="mt-4">
                        <div class="h-2 bg-slate-700/60 rounded-full overflow-hidden">
                            <div
                                class="h-full bg-gradient-to-r from-emerald-400 to-cyan-400 transition-all"
                                :style="{ width: `${progressPct}%` }"
                            />
                        </div>
                        <div class="flex justify-between text-xs text-slate-400 mt-1">
                            <span>Activado: {{ formatDate(sub.active.started_at) }}</span>
                            <span>Vence: {{ formatDate(sub.active.expires_at) }}</span>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <router-link
                            to="/study"
                            class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold text-sm transition"
                        >
                            📖 Empezar a estudiar
                        </router-link>
                        <router-link
                            v-if="sub.active.plan.includes_flashcards"
                            to="/flashcards"
                            class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white text-sm transition"
                        >
                            📇 Flashcards
                        </router-link>
                    </div>
                </section>

                <!-- Solicitud pendiente -->
                <section
                    v-else-if="sub.pending"
                    class="mb-6 p-6 rounded-2xl border-2 border-amber-500/40 bg-gradient-to-br from-amber-500/10 to-orange-500/5"
                >
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">⏳</span>
                        <div class="flex-1">
                            <h2 class="text-xl font-bold text-white">
                                Solicitud del plan {{ sub.pending.plan.name }} en revisión
                            </h2>
                            <p class="text-amber-200/80 text-sm mt-1">
                                Coordiná el pago por WhatsApp. Apenas confirmemos la transferencia,
                                activamos tu plan.
                            </p>
                            <p class="text-xs text-amber-300/60 mt-2">
                                Solicitado: {{ formatDate(sub.pending.requested_at) }}
                                · Solicitud #{{ sub.pending.id }}
                            </p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <a
                                    v-if="whatsappUrl"
                                    :href="whatsappUrl"
                                    target="_blank"
                                    rel="noopener"
                                    class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold text-sm transition"
                                >
                                    💬 Abrir WhatsApp
                                </a>
                                <router-link
                                    to="/pricing"
                                    class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white text-sm transition"
                                >
                                    Ver otros planes
                                </router-link>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Solicitud rechazada -->
                <section
                    v-else-if="lastRejected"
                    class="mb-6 p-6 rounded-2xl border-2 border-rose-500/40 bg-gradient-to-br from-rose-500/10 to-pink-500/5"
                >
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">❌</span>
                        <div class="flex-1">
                            <h2 class="text-xl font-bold text-white">
                                Tu última solicitud fue rechazada
                            </h2>
                            <p class="text-rose-200/90 text-sm mt-1">
                                <strong>Motivo:</strong> {{ lastRejected.rejection_reason }}
                            </p>
                            <p class="text-xs text-rose-300/60 mt-2">
                                Solicitud #{{ lastRejected.id }} · {{ formatDate(lastRejected.approved_at) }}
                            </p>
                            <div class="mt-4">
                                <router-link
                                    to="/pricing"
                                    class="px-4 py-2 rounded-lg bg-rose-500 hover:bg-rose-400 text-white font-semibold text-sm transition"
                                >
                                    Intentar de nuevo
                                </router-link>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Free plan -->
                <section
                    v-else
                    class="mb-6 p-6 rounded-2xl border border-slate-700 bg-slate-800/50"
                >
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">⚡</span>
                        <div class="flex-1">
                            <h2 class="text-xl font-bold text-white">Plan Gratuito</h2>
                            <p class="text-slate-300 text-sm mt-1">
                                <span v-if="sub.freeQuota?.chosen_specialty">
                                    Materia elegida:
                                    <strong class="text-emerald-300">{{ chosenSpecialtyName }}</strong>
                                </span>
                                <span v-else>
                                    Todavía no elegiste materia. Empezá con una para usar tus 5 preguntas diarias.
                                </span>
                            </p>
                            <div class="mt-3 flex items-center gap-3">
                                <div class="flex-1 max-w-xs h-2 bg-slate-700 rounded-full overflow-hidden">
                                    <div
                                        class="h-full bg-emerald-400 transition-all"
                                        :style="{ width: `${freeProgressPct}%` }"
                                    />
                                </div>
                                <span class="text-xs text-slate-400 tabular-nums">
                                    {{ sub.freeQuota?.questions_answered ?? 0 }} / {{ sub.freeQuota?.daily_limit ?? 5 }}
                                </span>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button
                                    v-if="!sub.freeQuota?.chosen_specialty"
                                    @click="showFreeModal = true"
                                    class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold text-sm transition"
                                >
                                    Elegir materia gratis
                                </button>
                                <router-link
                                    to="/pricing"
                                    class="px-4 py-2 rounded-lg bg-indigo-500 hover:bg-indigo-400 text-white font-semibold text-sm transition"
                                >
                                    Desbloquear más
                                </router-link>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Historial -->
                <section v-if="requests.length > 0" class="mt-10">
                    <h3 class="text-lg font-semibold text-white mb-3">Historial de solicitudes</h3>
                    <div class="space-y-2">
                        <div
                            v-for="r in requests"
                            :key="r.id"
                            class="p-3 rounded-lg border bg-slate-800/40 border-slate-700 flex items-center justify-between gap-3 flex-wrap"
                        >
                            <div>
                                <p class="text-white text-sm font-semibold">
                                    #{{ r.id }} · Plan {{ r.plan.name }}
                                    <span class="text-slate-400 font-normal">· {{ r.plan.formatted_price }}</span>
                                </p>
                                <p class="text-xs text-slate-400">
                                    {{ formatDate(r.requested_at) }}
                                </p>
                                <p v-if="r.rejection_reason" class="text-xs text-rose-300 mt-1">
                                    Rechazado: {{ r.rejection_reason }}
                                </p>
                            </div>
                            <span
                                class="text-xs px-2 py-1 rounded-full font-semibold"
                                :class="statusClass(r.status)"
                            >
                                {{ statusLabel(r.status) }}
                            </span>
                        </div>
                    </div>
                </section>
            </template>
        </main>

        <FreeSpecialtyModal
            v-if="showFreeModal"
            :specialties="sub.specialties"
            :submitting="sub.submitting"
            @close="showFreeModal = false"
            @confirm="onConfirmFreeSpecialty"
        />
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import Navbar from '@/components/Navbar.vue';
import FreeSpecialtyModal from '@/components/FreeSpecialtyModal.vue';
import { useSubscriptionStore, type PlanRequest } from '@/stores/subscription';

const sub = useSubscriptionStore();
const showFreeModal = ref(false);
const requests = ref<PlanRequest[]>([]);

const lastRejected = computed(() =>
    requests.value.find(r => r.status === 'rejected')
);

const chosenSpecialtyName = computed(() => {
    const id = sub.freeQuota?.chosen_specialty;
    if (!id) return '';
    return sub.specialties.find(s => s.id === id)?.name ?? '';
});

const progressPct = computed(() => {
    if (!sub.active) return 0;
    const start = sub.active.started_at ? new Date(sub.active.started_at).getTime() : Date.now();
    const end = sub.active.expires_at ? new Date(sub.active.expires_at).getTime() : Date.now();
    const total = end - start;
    const elapsed = Date.now() - start;
    if (total <= 0) return 100;
    return Math.max(0, Math.min(100, 100 - (elapsed / total) * 100));
});

const freeProgressPct = computed(() => {
    const used = sub.freeQuota?.questions_answered ?? 0;
    const limit = sub.freeQuota?.daily_limit ?? 5;
    return Math.min(100, (used / limit) * 100);
});

const whatsappUrl = computed(() => {
    if (!sub.pending || !sub.whatsapp) return null;
    const msg = sub.whatsapp.base_message + '\n\n'
        + `• Solicitud #${sub.pending.id}\n`
        + `• Plan: ${sub.pending.plan.name} (${sub.pending.plan.formatted_price})`;
    return `https://wa.me/${sub.whatsapp.number}?text=${encodeURIComponent(msg)}`;
});

onMounted(async () => {
    await sub.fetchMySubscription();
    if (sub.plans.length === 0) await sub.fetchPlans();
    try {
        const { data } = await axios.get('/me/subscription-requests');
        requests.value = data.requests;
    } catch {
        // ignore
    }
});

async function onConfirmFreeSpecialty(specialtyId: number) {
    try {
        await sub.chooseFreeSpecialty(specialtyId);
        showFreeModal.value = false;
    } catch {
        // error en store
    }
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
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
