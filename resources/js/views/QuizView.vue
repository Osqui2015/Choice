<template>
    <div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col">
        <Navbar />

        <main class="flex-1 max-w-3xl w-full mx-auto px-4 sm:px-6 py-6">
            <div v-if="loading && !current" class="text-center py-20 text-slate-400">Cargando preguntas…</div>

            <div v-else-if="quiz.error" class="text-center py-16 max-w-md mx-auto">
                <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-2xl">
                    🔒
                </div>
                <h2 class="text-xl font-bold text-white">{{ quiz.error }}</h2>
                <p class="text-slate-400 text-sm mt-2">
                    Pasate a Premium para acceder a todas las preguntas de medicina sin límites.
                </p>
                <div class="mt-6 flex flex-col gap-3">
                    <button
                        @click="showPricing = true"
                        class="w-full py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-bold transition text-sm"
                    >
                        💬 Desbloquear con Premium por WhatsApp
                    </button>
                    <router-link
                        to="/study"
                        class="text-xs text-slate-400 hover:text-white mt-1"
                    >
                        ← Volver a especialidades
                    </router-link>
                </div>
            </div>

            <div v-else-if="!current" class="text-center py-16">
                <h2 class="text-2xl font-bold text-white">¡Sesión finalizada!</h2>
                <p class="text-slate-400 mt-2">Respondiste {{ quiz.sessionCorrect + quiz.sessionWrong }} preguntas</p>
                <div class="mt-6 inline-flex gap-6 text-sm">
                    <div class="px-4 py-2 rounded-lg bg-emerald-500/20 border border-emerald-500/30 text-emerald-300">
                        ✅ {{ quiz.sessionCorrect }} correctas
                    </div>
                    <div class="px-4 py-2 rounded-lg bg-rose-500/20 border border-rose-500/30 text-rose-300">
                        ❌ {{ quiz.sessionWrong }} incorrectas
                    </div>
                </div>

                <!-- Si es usuario gratuito y terminó su cupo -->
                <div v-if="!quota?.is_premium" class="mt-8 p-6 max-w-lg mx-auto rounded-2xl bg-gradient-to-br from-indigo-900/30 to-purple-900/30 border border-indigo-500/30 text-center">
                    <p class="text-2xl">🎉</p>
                    <h3 class="text-lg font-bold text-white mt-1">¡Completaste tu prueba gratuita de esta especialidad!</h3>
                    <p class="text-sm text-slate-300 mt-2">
                        Has respondido las preguntas de muestra. Desbloqueá el resto de las +800 preguntas de esta especialidad, el Banco de Fallos y las Flashcards con el Pase Premium.
                    </p>
                    <button
                        @click="showPricing = true"
                        class="mt-4 px-6 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-bold transition text-sm"
                    >
                        💬 Desbloquear con Premium por WhatsApp
                    </button>
                </div>

                <div class="mt-8 flex gap-3 justify-center">
                    <button
                        v-if="quota?.is_premium"
                        @click="restart"
                        class="px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold"
                    >Hacer otra tanda</button>
                    <router-link
                        to="/study"
                        class="px-5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white"
                    >Volver a especialidades</router-link>
                </div>
            </div>

            <div v-else>
                <!-- Header de pregunta -->
                <div class="mb-4 flex items-center justify-between text-xs text-slate-400">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700">Pregunta {{ quiz.progress }}</span>
                        <span v-if="current.specialty" class="px-2 py-0.5 rounded-full bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 font-medium">{{ current.specialty.name }}</span>
                        <span v-if="current.topic" class="px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 font-medium">🏷️ {{ current.topic.name }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="toggleBookmark"
                            :title="current.is_bookmarked ? 'Quitar de guardados' : 'Guardar pregunta'"
                            class="w-7 h-7 rounded-full flex items-center justify-center transition border"
                            :class="current.is_bookmarked
                                ? 'bg-yellow-500/20 border-yellow-500/40 text-yellow-300'
                                : 'bg-slate-800 border-slate-700 text-slate-400 hover:text-yellow-300 hover:border-yellow-500/30'"
                        >
                            {{ current.is_bookmarked ? '★' : '☆' }}
                        </button>
                        <span v-if="streak > 0" class="px-2 py-0.5 rounded-full bg-orange-500/20 border border-orange-500/30 text-orange-300">🔥 {{ streak }}</span>
                    </div>
                </div>

                <!-- Enunciado -->
                <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6 mb-4">
                    <p class="text-lg leading-relaxed text-white whitespace-pre-line">{{ current.statement }}</p>
                </div>

                <!-- Opciones -->
                <div class="space-y-2 mb-4">
                    <div
                        v-for="opt in current.options"
                        :key="opt.key"
                        :class="[
                            'w-full text-left p-4 rounded-xl border transition flex items-center gap-3',
                            optionClasses(opt.key)
                        ]"
                    >
                        <button
                            @click="quiz.selectOption(opt.key)"
                            :disabled="!!quiz.feedback"
                            class="flex-1 flex items-center gap-3 disabled:cursor-default"
                        >
                            <span
                                :class="[
                                    'w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold border-2 shrink-0',
                                    keyClasses(opt.key)
                                ]"
                            >{{ opt.key.toUpperCase() }}</span>
                            <span class="flex-1" :class="quiz.crossedOut.has(opt.key) ? 'line-through opacity-50' : ''">
                                {{ opt.text }}
                            </span>
                        </button>
                        <button
                            v-if="!quiz.feedback"
                            @click="quiz.toggleCrossOut(opt.key)"
                            class="text-xs text-slate-500 hover:text-slate-300 px-2 py-1"
                            :title="quiz.crossedOut.has(opt.key) ? 'Quitar tachado' : 'Tachar opción'"
                        >✗</button>
                    </div>
                </div>

                <!-- Botón responder -->
                <div v-if="!quiz.feedback" class="flex justify-end">
                    <button
                        @click="quiz.submitAnswer()"
                        :disabled="!quiz.selected || quiz.loading"
                        class="px-6 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold"
                    >
                        {{ quiz.loading ? 'Enviando…' : 'Responder' }}
                    </button>
                </div>

                <!-- Feedback -->
                <div v-else>
                    <div
                        :class="[
                            'rounded-2xl p-5 mb-3 border-2',
                            quiz.feedback.is_correct
                                ? 'bg-emerald-500/10 border-emerald-500/50'
                                : 'bg-rose-500/10 border-rose-500/50'
                        ]"
                    >
                        <p class="text-sm uppercase tracking-wider" :class="quiz.feedback.is_correct ? 'text-emerald-300' : 'text-rose-300'">
                            {{ quiz.feedback.is_correct ? '✅ ¡Correcta!' : '❌ Incorrecta' }}
                            <span v-if="!quiz.feedback.is_correct"> — La correcta es la <strong class="font-mono">{{ quiz.feedback.correct_answer.toUpperCase() }}</strong></span>
                        </p>
                        <div v-if="quiz.feedback.correct_justification" class="mt-3">
                            <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">Justificación de la respuesta correcta</p>
                            <div class="text-sm text-slate-200 leading-relaxed whitespace-pre-line prose prose-invert max-w-none" v-html="quiz.feedback.correct_justification"></div>
                        </div>
                        <div v-if="quiz.feedback.incorrect_justification" class="mt-3">
                            <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">Por qué se descartan las otras</p>
                            <div class="text-sm text-slate-200 leading-relaxed whitespace-pre-line prose prose-invert max-w-none" v-html="quiz.feedback.incorrect_justification"></div>
                        </div>
                        <div v-if="quiz.feedback.source_justification" class="mt-3 p-3 bg-slate-900/60 rounded-xl border border-slate-700/60">
                            <p class="text-xs uppercase tracking-wider text-indigo-400 font-semibold mb-1">Correlación Clínica / Algoritmo</p>
                            <div class="text-xs text-slate-300 leading-relaxed whitespace-pre-line" v-html="quiz.feedback.source_justification"></div>
                        </div>
                        <div v-if="quiz.feedback.source_file || quiz.feedback.catedra" class="mt-3 pt-3 border-t border-slate-700 text-xs text-slate-400 space-y-0.5">
                            <p v-if="quiz.feedback.catedra"><strong>Cátedra:</strong> {{ quiz.feedback.catedra }}</p>
                            <p v-if="quiz.feedback.source_file"><strong>Fuente:</strong> {{ quiz.feedback.source_file }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button
                            @click="quiz.next()"
                            class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold"
                        >
                            Siguiente pregunta →
                        </button>
                    </div>
                </div>
            </div>
        </main>

        <PricingModal :open="showPricing" @close="showPricing = false" />
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Navbar from '@/components/Navbar.vue';
import PricingModal from '@/components/PricingModal.vue';
import { useQuizStore } from '@/stores/quiz';
import { useQuotaStore } from '@/stores/quota';

const route = useRoute();
const router = useRouter();
const quiz = useQuizStore();
const quotaStore = useQuotaStore();

const showPricing = ref(false);
const loading = computed(() => quiz.loading);
const current = computed(() => quiz.current);
const streak = computed(() => quotaStore.currentCorrectStreak);
const quota = computed(() => quotaStore.quota);

function optionClasses(key: string): string {
    if (!quiz.feedback) {
        return quiz.selected === key
            ? 'bg-indigo-600/20 border-indigo-500'
            : 'bg-slate-800 border-slate-700 hover:border-slate-600';
    }
    const correct = quiz.feedback.correct_answer === key;
    const selected = quiz.selected === key;
    if (correct) return 'bg-emerald-500/20 border-emerald-500';
    if (selected && !correct) return 'bg-rose-500/20 border-rose-500';
    return 'bg-slate-800 border-slate-700 opacity-60';
}

function keyClasses(key: string): string {
    if (!quiz.feedback) {
        return quiz.selected === key
            ? 'bg-indigo-600 border-indigo-500 text-white'
            : 'bg-slate-700 border-slate-600 text-slate-200';
    }
    const correct = quiz.feedback.correct_answer === key;
    const selected = quiz.selected === key;
    if (correct) return 'bg-emerald-600 border-emerald-500 text-white';
    if (selected && !correct) return 'bg-rose-600 border-rose-500 text-white';
    return 'bg-slate-700 border-slate-600 text-slate-400';
}

function restart() {
    quiz.reset();
    loadBatch();
}

async function toggleBookmark() {
    if (!current.value) return;
    await quiz.toggleBookmark(current.value.id);
}

async function loadBatch() {
    const specialtyId = route.query.specialty ? Number(route.query.specialty) : undefined;
    const topicId = route.query.topic ? Number(route.query.topic) : undefined;
    await quiz.fetchStudy({ specialty_id: specialtyId, topic_id: topicId, mode: 'free', limit: 10 });
}

onMounted(loadBatch);

watch(
    [() => route.query.specialty, () => route.query.topic],
    () => {
        quiz.reset();
        loadBatch();
    },
);
</script>
