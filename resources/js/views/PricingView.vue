<template>
    <div class="min-h-screen bg-slate-900 text-slate-100">
        <Navbar />
        <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
            <!-- Header -->
            <div class="text-center mb-10">
                <h1 class="text-3xl sm:text-4xl font-bold text-slate-100">Elegí tu plan</h1>
                <p class="text-slate-400 mt-2 max-w-2xl mx-auto">
                    Accedé a las <strong class="text-emerald-400">+3.230 preguntas MCQ</strong> y
                    <strong class="text-emerald-400">328 flashcards clínicas</strong>.
                    Pagás por WhatsApp y se activa en minutos.
                </p>
            </div>

            <!-- Banner: si tiene plan activo o pending -->
            <div
                v-if="sub.hasActive && sub.active"
                class="mb-6 p-4 rounded-xl border bg-emerald-500/10 border-emerald-500/30 flex items-start gap-3"
            >
                <span class="text-2xl">✅</span>
                <div class="flex-1">
                    <p class="text-emerald-300 font-semibold">
                        Tenés el plan {{ sub.active.plan.name }} activo
                        <span v-if="!sub.active.is_unlimited">
                            con {{ sub.active.specialties.length }}
                            {{ sub.active.specialties.length === 1 ? 'materia' : 'materias' }}
                        </span>
                        · {{ sub.active.days_remaining }} días restantes
                    </p>
                    <p class="text-emerald-200/70 text-sm mt-0.5">
                        Vence el {{ formatDate(sub.active.expires_at) }}.
                        <router-link to="/my-subscription" class="underline hover:text-slate-100">Ver detalle</router-link>
                    </p>
                </div>
            </div>

            <div
                v-else-if="sub.pending"
                class="mb-6 p-4 rounded-xl border bg-amber-500/10 border-amber-500/30 flex items-start gap-3"
            >
                <span class="text-2xl">⏳</span>
                <div class="flex-1">
                    <p class="text-amber-300 font-semibold">
                        Tu solicitud del plan {{ sub.pending.plan.name }} está esperando aprobación
                    </p>
                    <p class="text-amber-200/70 text-sm mt-0.5">
                        Te avisamos por WhatsApp cuando esté activa.
                        <router-link to="/my-subscription" class="underline hover:text-slate-100">Ver detalle</router-link>
                    </p>
                </div>
            </div>

            <!-- Catálogo de planes -->
            <div v-if="sub.loading" class="text-center py-12 text-slate-400">Cargando planes…</div>

            <div v-else class="grid md:grid-cols-3 gap-4 sm:gap-6">
                <!-- Free -->
                <div class="border border-slate-700 rounded-2xl p-5 sm:p-6 bg-slate-800/40 flex flex-col">
                    <p class="text-xs uppercase tracking-wider text-slate-400">Gratuito</p>
                    <p class="text-3xl sm:text-4xl font-bold text-slate-100 mt-1">$0</p>
                    <p class="text-xs text-slate-500">Para probar la app</p>
                    <ul class="mt-5 space-y-2.5 text-sm text-slate-300 flex-1">
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> 5 preguntas diarias de 1 materia a elección</li>
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> Acceso a las 3 especialidades</li>
                        <li class="flex gap-2 text-rose-400"><span class="font-bold">✗</span> Banco de fallos</li>
                        <li class="flex gap-2 text-rose-400"><span class="font-bold">✗</span> Flashcards clínicas</li>
                        <li class="flex gap-2 text-slate-500"><span>✗</span> Sin simulacros cronometrados</li>
                    </ul>
                    <button
                        v-if="!sub.hasActive && !sub.pending"
                        @click="onChooseFreeSpecialty"
                        class="mt-6 w-full py-2.5 rounded-lg border border-slate-600 text-slate-200 hover:bg-slate-700 font-semibold transition"
                    >
                        Empezar gratis
                    </button>
                    <p v-else-if="sub.hasActive" class="mt-6 text-center text-xs text-slate-500">Plan actual</p>
                </div>

                <!-- Básico -->
                <div class="border border-slate-700 rounded-2xl p-5 sm:p-6 bg-slate-800/60 flex flex-col">
                    <p class="text-xs uppercase tracking-wider text-indigo-300">Básico</p>
                    <p class="text-3xl sm:text-4xl font-bold text-slate-100 mt-1">{{ planBasico?.formatted_price ?? '—' }}</p>
                    <p class="text-xs text-slate-400">por mes · 1 materia a elección</p>
                    <ul class="mt-5 space-y-2.5 text-sm text-slate-200 flex-1">
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> <strong>Preguntas ilimitadas</strong> de 1 materia</li>
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> Las 328 flashcards clínicas</li>
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> Banco de fallos</li>
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> Estadísticas detalladas</li>
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> Soporte por WhatsApp</li>
                    </ul>
                    <button
                        @click="openAcquisition(planBasico!)"
                        :disabled="sub.hasActive || sub.submitting"
                        class="mt-6 w-full py-2.5 rounded-lg bg-indigo-500 hover:bg-indigo-400 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold transition"
                    >
                        {{ sub.submitting ? 'Procesando…' : 'Elegir Básico' }}
                    </button>
                </div>

                <!-- Estudiante (destacado) -->
                <div class="border-2 border-indigo-500 rounded-2xl p-5 sm:p-6 bg-gradient-to-br from-indigo-600/15 to-purple-600/10 relative flex flex-col">
                    <span class="absolute -top-2.5 right-4 px-2.5 py-0.5 rounded-full bg-indigo-500 text-white text-xs font-bold">RECOMENDADO</span>
                    <p class="text-xs uppercase tracking-wider text-indigo-300">Estudiante</p>
                    <p class="text-3xl sm:text-4xl font-bold text-slate-100 mt-1">{{ planEstudiante?.formatted_price ?? '—' }}</p>
                    <p class="text-xs text-slate-400">por mes · 3 materias a elección</p>
                    <ul class="mt-5 space-y-2.5 text-sm text-slate-100 flex-1">
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> <strong>Preguntas ilimitadas</strong> de 3 materias</li>
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> Las 328 flashcards clínicas</li>
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> Banco de fallos</li>
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> Estadísticas detalladas</li>
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> Soporte prioritario por WhatsApp</li>
                    </ul>
                    <button
                        @click="openAcquisition(planEstudiante!)"
                        :disabled="sub.hasActive || sub.submitting"
                        class="mt-6 w-full py-2.5 rounded-lg bg-indigo-500 hover:bg-indigo-400 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold transition"
                    >
                        {{ sub.submitting ? 'Procesando…' : 'Elegir Estudiante' }}
                    </button>
                </div>

                <!-- Full -->
                <div class="border border-emerald-500/50 rounded-2xl p-5 sm:p-6 bg-gradient-to-br from-emerald-600/10 to-cyan-600/5 flex flex-col md:col-span-3 lg:col-span-1 md:max-w-sm md:mx-auto md:w-full">
                    <p class="text-xs uppercase tracking-wider text-emerald-300">Full</p>
                    <p class="text-3xl sm:text-4xl font-bold text-slate-100 mt-1">{{ planFull?.formatted_price ?? '—' }}</p>
                    <p class="text-xs text-slate-400">por mes · TODAS las materias</p>
                    <ul class="mt-5 space-y-2.5 text-sm text-slate-200 flex-1">
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> <strong>TODO Choice desbloqueado</strong></li>
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> Las 3 especialidades + futuras</li>
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> Las 328 flashcards clínicas</li>
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> Banco de fallos</li>
                        <li class="flex gap-2"><span class="text-emerald-400">✓</span> Soporte prioritario por WhatsApp</li>
                    </ul>
                    <button
                        @click="openAcquisition(planFull!)"
                        :disabled="sub.hasActive || sub.submitting"
                        class="mt-6 w-full py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 disabled:opacity-40 disabled:cursor-not-allowed text-slate-900 font-bold transition"
                    >
                        {{ sub.submitting ? 'Procesando…' : 'Elegir Full' }}
                    </button>
                </div>
            </div>

            <!-- Comparación -->
            <div class="mt-12 p-6 rounded-2xl border border-slate-700 bg-slate-800/40">
                <h2 class="text-lg font-semibold text-slate-100 mb-3">¿Cómo funciona?</h2>
                <ol class="space-y-2.5 text-sm text-slate-300">
                    <li class="flex gap-3"><span class="text-indigo-400 font-bold">1.</span> Elegís tu plan y las materias que querés estudiar.</li>
                    <li class="flex gap-3"><span class="text-indigo-400 font-bold">2.</span> Te enviamos un link directo a nuestro WhatsApp con tu solicitud ya armada.</li>
                    <li class="flex gap-3"><span class="text-indigo-400 font-bold">3.</span> Coordinás el pago por transferencia y, una vez confirmado, activamos tu plan en minutos.</li>
                </ol>
            </div>

            <p class="text-center text-xs text-slate-500 mt-8">
                ¿Dudas? Escribinos por WhatsApp al
                <a :href="`https://wa.me/${sub.whatsapp?.number}`" target="_blank" rel="noopener" class="text-emerald-400 hover:underline">
                    +{{ formatPhone(sub.whatsapp?.number) }}
                </a>.
            </p>
        </main>

        <!-- Modal de adquisición -->
        <PlanAcquireModal
            v-if="selectedPlan"
            :plan="selectedPlan"
            :specialties="sub.specialties"
            :submitting="sub.submitting"
            @close="selectedPlan = null"
            @confirm="onConfirmAcquire"
        />

        <!-- Modal elegir materia free -->
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
import { useRouter } from 'vue-router';
import Navbar from '@/components/Navbar.vue';
import PlanAcquireModal from '@/components/PlanAcquireModal.vue';
import FreeSpecialtyModal from '@/components/FreeSpecialtyModal.vue';
import { useSubscriptionStore, type Plan } from '@/stores/subscription';

const sub = useSubscriptionStore();
const router = useRouter();

const selectedPlan = ref<Plan | null>(null);
const showFreeModal = ref(false);

const planBasico = computed(() => sub.plans.find(p => p.slug === 'basico') ?? null);
const planEstudiante = computed(() => sub.plans.find(p => p.slug === 'estudiante') ?? null);
const planFull = computed(() => sub.plans.find(p => p.slug === 'full') ?? null);

onMounted(async () => {
    if (sub.plans.length === 0) {
        await sub.fetchPlans();
    }
    await sub.fetchMySubscription();
});

function openAcquisition(plan: Plan) {
    if (sub.hasActive) {
        router.push('/my-subscription');
        return;
    }
    if (sub.pending) {
        router.push('/my-subscription');
        return;
    }
    selectedPlan.value = plan;
}

function onChooseFreeSpecialty() {
    if (sub.freeQuota?.chosen_specialty) {
        // Ya tenía una specialty, le llevamos al flujo de estudio
        router.push('/study');
        return;
    }
    showFreeModal.value = true;
}

async function onConfirmFreeSpecialty(specialtyId: number) {
    try {
        await sub.chooseFreeSpecialty(specialtyId);
        showFreeModal.value = false;
        router.push('/study');
    } catch {
        // el error ya está en el store
    }
}

async function onConfirmAcquire(payload: { specialty_ids: number[]; notes?: string }) {
    if (!selectedPlan.value) return;
    try {
        const { whatsapp_url } = await sub.requestPlan(
            selectedPlan.value.id,
            payload.specialty_ids,
            payload.notes,
        );
        selectedPlan.value = null;
        // Abrimos WhatsApp en una pestaña nueva y llevamos al user a "Mi suscripción"
        window.open(whatsapp_url, '_blank', 'noopener');
        router.push('/my-subscription');
    } catch {
        // el error está en el store
    }
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatPhone(raw?: string): string {
    if (!raw) return '';
    return raw.replace(/^54/, '').replace(/(\d{2})(\d{4})(\d{4})/, '$1 $2-$3');
}
</script>
