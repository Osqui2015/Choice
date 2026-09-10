<template>
    <div class="min-h-screen bg-slate-900 text-slate-100">
        <Navbar />
        <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-slate-100">Elegí qué estudiar</h1>
                <p class="text-slate-400 mt-1">
                    <span v-if="loading">Cargando catálogo…</span>
                    <span v-else>{{ totalQuestions.toLocaleString() }} preguntas MCQ · {{ totalFlashcards.toLocaleString() }} flashcards clínicas</span>
                </p>
            </div>

            <!-- Modo de estudio -->
            <div class="mb-6 grid sm:grid-cols-3 gap-3">
                <button
                    @click="mode = 'study'"
                    :class="[
                        'p-4 rounded-xl border text-left transition',
                        mode === 'study'
                            ? 'bg-indigo-600/20 border-indigo-500/50 text-slate-50'
                            : 'bg-slate-800/50 border-slate-700 text-slate-300 hover:border-slate-600'
                    ]"
                >
                    <div class="text-2xl mb-1">📖</div>
                    <div class="font-semibold flex items-center justify-between">
                        <span>Modo Estudio</span>
                        <span v-if="!quota?.is_premium" class="text-xs px-2 py-0.5 rounded-full bg-slate-700 text-slate-700 font-normal">4/esp</span>
                    </div>
                    <div class="text-xs opacity-70 mt-0.5">Preguntas con feedback inmediato</div>
                </button>
                <button
                    @click="onErrorsModeClick"
                    :class="[
                        'p-4 rounded-xl border text-left transition',
                        mode === 'errors'
                            ? 'bg-rose-600/20 border-rose-500/50 text-slate-50'
                            : 'bg-slate-800/50 border-slate-700 text-slate-300 hover:border-slate-600'
                    ]"
                >
                    <div class="text-2xl mb-1">🔄</div>
                    <div class="font-semibold flex items-center justify-between">
                        <span>Banco de Fallos</span>
                        <span v-if="!quota?.is_premium" class="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">🔒 PRO</span>
                    </div>
                    <div class="text-xs opacity-70 mt-0.5">Repasá lo que fallaste</div>
                </button>
                <button
                    @click="onFlashcardsModeClick"
                    :class="[
                        'p-4 rounded-xl border text-left transition',
                        mode === 'flashcards'
                            ? 'bg-emerald-600/20 border-emerald-500/50 text-slate-50'
                            : 'bg-slate-800/50 border-slate-700 text-slate-300 hover:border-slate-600'
                    ]"
                >
                    <div class="text-2xl mb-1">📇</div>
                    <div class="font-semibold flex items-center justify-between">
                        <span>Flashcards</span>
                        <span v-if="!quota?.is_premium" class="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">🔒 PRO</span>
                    </div>
                    <div class="text-xs opacity-70 mt-0.5">Repaso rápido de conceptos</div>
                </button>
            </div>

            <!-- Estado de suscripción (plan real) -->
            <div
                v-if="sub.hasActive && sub.active"
                class="mb-6 p-4 rounded-xl border-2 bg-gradient-to-r from-emerald-500/10 to-cyan-500/5 border-emerald-500/30 flex items-center justify-between flex-wrap gap-3"
            >
                <div>
                    <p class="text-sm text-slate-100 font-semibold flex items-center gap-2">
                        <span class="text-xl">✅</span>
                        Plan <span class="text-emerald-300">{{ sub.active.plan.name }}</span> activo
                        <span v-if="sub.active.is_unlimited" class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-200 border border-emerald-500/30 font-bold">
                            TODAS
                        </span>
                    </p>
                    <p class="text-xs text-slate-700 mt-1">
                        <span v-if="!sub.active.is_unlimited && sub.active.specialties.length > 0">
                            📚 {{ sub.active.specialties.map(s => s.name).join(' · ') }}
                        </span>
                        <span v-else>Acceso a todas las especialidades + flashcards</span>
                        · <strong class="text-emerald-300">{{ sub.active.days_remaining }}</strong> días restantes
                    </p>
                </div>
                <div class="flex gap-2">
                    <router-link
                        to="/my-subscription"
                        class="px-3.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-100 text-xs font-semibold transition"
                    >
                        Gestionar
                    </router-link>
                </div>
            </div>

            <div
                v-else-if="sub.pending"
                class="mb-6 p-4 rounded-xl border bg-amber-500/10 border-amber-500/30 flex items-center justify-between flex-wrap gap-3"
            >
                <div>
                    <p class="text-sm text-amber-200 font-semibold flex items-center gap-2">
                        <span class="text-xl">⏳</span>
                        Solicitud del plan {{ sub.pending.plan.name }} en revisión
                    </p>
                    <p class="text-xs text-amber-300/70 mt-1">
                        Te avisamos por WhatsApp cuando esté activa.
                    </p>
                </div>
                <router-link
                    to="/my-subscription"
                    class="px-3.5 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-900 font-bold text-xs transition"
                >
                    Ver detalle
                </router-link>
            </div>

            <!-- Estado de cuota FREE -->
            <div v-else-if="sub.freeQuota" class="mb-6 p-4 rounded-xl border bg-slate-800/50 border-slate-700">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex-1">
                        <p class="text-sm text-slate-300">
                            <span v-if="!sub.freeQuota.chosen_specialty" class="font-semibold text-slate-100">
                                🆓 Empezá con el plan gratuito: elegí una materia y tenés 5 preguntas por día.
                            </span>
                            <span v-else-if="sub.freeQuota.is_exhausted" class="text-amber-300 font-semibold">
                                🔒 Límite diario alcanzado. Vuelve mañana o desbloqueá más con un plan.
                            </span>
                            <span v-else>
                                🆓 Plan Gratuito: <strong class="text-emerald-300">{{ sub.freeQuota.questions_answered }} / {{ sub.freeQuota.daily_limit }}</strong>
                                preguntas usadas hoy de <strong class="text-emerald-300">{{ chosenFreeSpecialtyName }}</strong>.
                            </span>
                        </p>
                        <p v-if="streak && streak.current_daily_streak > 0" class="text-xs text-orange-300 mt-1">🔥 Racha diaria: {{ streak.current_daily_streak }} días</p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            v-if="!sub.freeQuota.chosen_specialty"
                            @click="showFreeModal = true"
                            class="px-3.5 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-bold text-xs transition"
                        >
                            Elegir materia gratis
                        </button>
                        <router-link
                            to="/pricing"
                            class="px-3.5 py-1.5 rounded-lg bg-indigo-500 hover:bg-indigo-400 text-white font-bold text-xs transition"
                        >
                            ⚡ Ver planes
                        </router-link>
                    </div>
                </div>
            </div>

            <!-- Mensaje de error -->
            <div v-if="errorMsg" class="mb-6 p-4 rounded-xl border bg-rose-500/10 border-rose-500/30 text-rose-300 flex items-center justify-between flex-wrap gap-2">
                <span>{{ errorMsg }}</span>
                <button
                    @click="loadSpecialties"
                    class="px-3 py-1 text-xs bg-rose-600 hover:bg-rose-500 text-white rounded-lg transition font-medium"
                >
                    Reintentar
                </button>
            </div>

            <!-- Lista de especialidades (solo modo study o errors) -->
            <div v-if="mode === 'study' || mode === 'errors'" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-if="loading" class="col-span-full text-center py-12 text-slate-500">
                    Cargando especialidades…
                </div>
                <div v-else-if="!specialties || !specialties.length" class="col-span-full text-center py-12 text-slate-500">
                    No hay especialidades disponibles todavía.
                </div>
                <button
                    v-for="s in (specialties || [])"
                    :key="s.id"
                    @click="onSpecialtyClick(s)"
                    class="p-5 rounded-2xl border transition text-left relative overflow-hidden"
                    :class="[
                        hasAccessTo(s.id)
                            ? 'bg-slate-800/50 border-slate-700 hover:border-indigo-500/50 hover:bg-slate-800 text-slate-100'
                            : 'bg-slate-800/30 border-slate-800 text-slate-400 hover:border-amber-500/50'
                    ]"
                >
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-lg font-bold text-slate-100">{{ s.name }}</h3>
                        <span
                            v-if="!hasAccessTo(s.id)"
                            class="text-xs px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-semibold border border-amber-500/30"
                        >
                            🔒
                        </span>
                        <span v-else class="text-xs px-2 py-0.5 rounded-full bg-slate-700 text-slate-700">{{ s.code }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs text-slate-400">
                        <div v-if="hasAccessTo(s.id)">
                            📝 <span class="text-slate-200">{{ s.stats?.total_questions ?? 0 }}</span> preguntas
                        </div>
                        <div v-else>
                            📝 <span class="text-slate-500">{{ s.stats?.total_questions ?? 0 }}</span>
                            <span class="text-amber-400 text-[10px] ml-1">🔒</span>
                        </div>
                        <div>
                            📇 <span :class="hasAccessTo(s.id) ? 'text-slate-200' : 'text-slate-500'">{{ s.stats?.total_flashcards ?? 0 }}</span>
                            <span v-if="!hasAccessTo(s.id)" class="text-amber-400 text-[10px] ml-1">PRO</span>
                            <span v-else>flashcards</span>
                        </div>
                        <div>✅ <span class="text-emerald-300">{{ s.stats?.accuracy ?? 0 }}%</span> acierto</div>
                        <div>📊 <span class="text-indigo-300">{{ s.stats?.progress ?? 0 }}%</span> avance</div>
                    </div>
                    <div class="mt-3 h-1.5 rounded-full bg-slate-700 overflow-hidden">
                        <div
                            class="h-full transition-all bg-gradient-to-r from-indigo-500 to-purple-500"
                            :style="{ width: `${s.stats?.progress ?? 0}%` }"
                        ></div>
                    </div>
                    <div v-if="!hasAccessTo(s.id)" class="mt-3 text-xs text-amber-300 font-medium flex items-center gap-1">
                        <span>🔒 No incluida en tu plan · Tocar para ver planes</span>
                    </div>
                </button>
            </div>

            <!-- Flashcards: solo ir al listado -->
            <div v-else-if="mode === 'flashcards'" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-if="loading" class="col-span-full text-center py-12 text-slate-500">
                    Cargando flashcards…
                </div>
                <template v-else>
                    <button
                        v-for="s in (specialties || []).filter(x => (x.stats?.total_flashcards ?? 0) > 0)"
                        :key="s.id"
                        @click="router.push({ name: 'flashcards', query: { specialty: s.id } })"
                        class="p-5 rounded-2xl border bg-slate-800/50 border-slate-700 hover:border-emerald-500/50 hover:bg-slate-800 transition text-left"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-lg font-bold text-slate-100">{{ s.name }}</h3>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-700 text-slate-700">{{ s.code }}</span>
                        </div>
                        <div class="text-sm text-slate-300">{{ s.stats?.total_flashcards ?? 0 }} flashcards disponibles</div>
                    </button>
                    <div v-if="!(specialties || []).some(x => (x.stats?.total_flashcards ?? 0) > 0)" class="col-span-full text-center py-12 text-slate-500">
                        Pronto habrá flashcards disponibles.
                    </div>
                </template>
            </div>
        </main>

        <PricingModal :open="showPricing" @close="showPricing = false" />
        <FreeSpecialtyModal
            v-if="showFreeModal"
            :specialties="sub.specialties"
            :submitting="sub.submitting"
            @close="showFreeModal = false"
            @confirm="onConfirmFreeSpecialty"
        />
        <TopicSelectModal
            :open="showTopicModal"
            :specialty="selectedSpecialtyForTopic"
            @close="showTopicModal = false"
            @select="onTopicSelected"
        />
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import Navbar from '@/components/Navbar.vue';
import PricingModal from '@/components/PricingModal.vue';
import FreeSpecialtyModal from '@/components/FreeSpecialtyModal.vue';
import TopicSelectModal, { type TopicItem } from '@/components/TopicSelectModal.vue';
import { useQuotaStore } from '@/stores/quota';
import { useSubscriptionStore } from '@/stores/subscription';
import { useAuthStore } from '@/stores/auth';

interface SpecialtyStat {
    id: number;
    code: string;
    name: string;
    slug: string;
    icon: string | null;
    color: string | null;
    is_locked?: boolean;
    free_limit?: number;
    free_remaining?: number | null;
    topics?: TopicItem[];
    stats: {
        total_questions: number;
        total_flashcards: number;
        answered: number;
        correct: number;
        accuracy: number;
        progress: number;
    };
}

const router = useRouter();
const route = useRoute();
const quotaStore = useQuotaStore();
const sub = useSubscriptionStore();
const auth = useAuthStore();

const mode = ref<'study' | 'errors' | 'flashcards'>('study');
const specialties = ref<SpecialtyStat[]>([]);
const errorMsg = ref<string | null>(null);
const loading = ref(false);
const showPricing = ref(false);
const showFreeModal = ref(false);
const showTopicModal = ref(false);
const selectedSpecialtyForTopic = ref<SpecialtyStat | null>(null);

const quota = computed(() => quotaStore.quota);
const streak = computed(() => quotaStore.streak);

const chosenFreeSpecialtyName = computed(() => {
    const id = sub.freeQuota?.chosen_specialty;
    if (!id) return '';
    return sub.specialties.find(s => s.id === id)?.name
        ?? specialties.value.find(s => s.id === id)?.name
        ?? '';
});

const totalQuestions = computed(() =>
    (specialties.value || []).reduce((acc, s) => acc + (s.stats?.total_questions ?? 0), 0)
);
const totalFlashcards = computed(() =>
    (specialties.value || []).reduce((acc, s) => acc + (s.stats?.total_flashcards ?? 0), 0)
);

/** ¿El user tiene acceso a esta specialty? (plan o free) */
function hasAccessTo(specialtyId: number): boolean {
    if (sub.hasActive) {
        if (sub.active?.is_unlimited) return true;
        return !!sub.active?.specialties.some(s => s.id === specialtyId);
    }
    // Free: solo la materia que eligió y mientras tenga cuota
    return sub.freeQuota?.chosen_specialty === specialtyId
        && !sub.freeQuota.is_exhausted;
}

function onErrorsModeClick() {
    if (!sub.hasActive) {
        showPricing.value = true;
        return;
    }
    mode.value = 'errors';
}

function onFlashcardsModeClick() {
    if (!sub.hasActive || !sub.active?.plan.includes_flashcards) {
        showPricing.value = true;
        return;
    }
    mode.value = 'flashcards';
}

function onSpecialtyClick(s: SpecialtyStat) {
    if (!hasAccessTo(s.id)) {
        showPricing.value = true;
        return;
    }
    if (mode.value === 'study') {
        selectedSpecialtyForTopic.value = s;
        showTopicModal.value = true;
    } else {
        onStart(s.id);
    }
}

function onTopicSelected(topicId: number | null) {
    showTopicModal.value = false;
    if (!selectedSpecialtyForTopic.value) return;

    const query: Record<string, any> = { specialty: selectedSpecialtyForTopic.value.id };
    if (topicId) {
        query.topic = topicId;
    }
    router.push({ name: 'quiz', query });
}

async function onConfirmFreeSpecialty(specialtyId: number) {
    try {
        await sub.chooseFreeSpecialty(specialtyId);
        showFreeModal.value = false;
    } catch {
        // error en el store
    }
}

async function loadSpecialties() {
    errorMsg.value = null;
    loading.value = true;
    try {
        const { data } = await axios.get('/specialties');
        specialties.value = Array.isArray(data?.specialties) ? data.specialties : [];
        if (data?.quota) {
            quotaStore.applyServerQuota(data.quota, data.streak);
        }
    } catch (e: any) {
        specialties.value = [];
        if (e.response?.status === 401) {
            errorMsg.value = 'Tu sesión expiró. Volvé a iniciar sesión.';
        } else if (e.code === 'ERR_NETWORK' || !e.response) {
            errorMsg.value = 'No se puede conectar con el servidor. Verificá que esté en ejecución.';
        } else {
            errorMsg.value = e.response?.data?.message || 'Error al cargar especialidades';
        }
    } finally {
        loading.value = false;
    }
}

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}

function onStart(specialtyId: number) {
    if (mode.value === 'study') {
        router.push({ name: 'quiz', query: { specialty: specialtyId } });
    } else if (mode.value === 'errors') {
        router.push({ name: 'errors', query: { specialty: specialtyId } });
    }
}

onMounted(() => {
    loadSpecialties();
    quotaStore.fetch();
    sub.fetchMySubscription();
    if (sub.plans.length === 0) sub.fetchPlans();
    if (route.query.upgrade) {
        showPricing.value = true;
    }
});
</script>
