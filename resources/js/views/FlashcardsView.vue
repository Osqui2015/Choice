<template>
    <div class="min-h-screen bg-slate-900 text-slate-100">
        <Navbar />
        <main class="max-w-2xl w-full mx-auto px-4 sm:px-6 py-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-white">📇 Flashcards</h1>
                    <p class="text-sm text-slate-400 mt-1">Tocá la tarjeta para girar</p>
                </div>
                <router-link to="/study" class="text-sm text-slate-400 hover:text-white">← Volver</router-link>
            </div>

            <div v-if="loading" class="text-center py-12 text-slate-400">Cargando…</div>

            <div v-else-if="!cards.length" class="bg-slate-800 border border-slate-700 rounded-xl p-12 text-center">
                <p class="text-3xl mb-2">📇</p>
                <p class="text-white font-semibold">No hay flashcards disponibles.</p>
            </div>

            <div v-else>
                <div class="mb-4 flex items-center justify-between">
                    <span class="text-sm text-slate-400">{{ index + 1 }} / {{ cards.length }}</span>
                    <div class="flex gap-2">
                        <button
                            @click="prev"
                            :disabled="index === 0"
                            class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-sm disabled:opacity-40"
                        >← Anterior</button>
                        <button
                            @click="next"
                            :disabled="index === cards.length - 1"
                            class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-sm disabled:opacity-40"
                        >Siguiente →</button>
                    </div>
                </div>

                <div
                    @click="flipped = !flipped"
                    class="relative h-72 cursor-pointer [perspective:1000px] mb-6"
                >
                    <div
                        :class="[
                            'relative w-full h-full transition-transform duration-500 [transform-style:preserve-3d]',
                            flipped ? '[transform:rotateY(180deg)]' : ''
                        ]"
                    >
                        <!-- Frente -->
                        <div class="absolute inset-0 [backface-visibility:hidden] bg-slate-800 border-2 border-slate-700 rounded-2xl p-6 flex flex-col items-center justify-center text-center">
                            <p class="text-xs uppercase tracking-wider text-slate-400 mb-3">Pregunta</p>
                            <p class="text-lg text-white whitespace-pre-line">{{ current.front }}</p>
                            <p class="text-xs text-slate-500 mt-4">click para ver respuesta</p>
                        </div>
                        <!-- Dorso -->
                        <div class="absolute inset-0 [backface-visibility:hidden] [transform:rotateY(180deg)] bg-emerald-900/40 border-2 border-emerald-700 rounded-2xl p-6 flex flex-col items-center justify-center text-center">
                            <p class="text-xs uppercase tracking-wider text-emerald-300 mb-3">Respuesta</p>
                            <p class="text-lg text-white whitespace-pre-line">{{ current.back }}</p>
                            <p v-if="current.justification" class="text-xs text-slate-300 mt-3 italic">{{ current.justification }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 justify-center">
                    <button
                        @click="mark(false)"
                        class="flex-1 py-2.5 rounded-lg bg-rose-500/20 border border-rose-500/30 text-rose-300 hover:bg-rose-500/30 font-semibold"
                    >Repasar luego</button>
                    <button
                        @click="mark(true)"
                        class="flex-1 py-2.5 rounded-lg bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 hover:bg-emerald-500/30 font-semibold"
                    >Lo sabía ✓</button>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import Navbar from '@/components/Navbar.vue';

interface Flashcard {
    id: number;
    front: string;
    back: string;
    justification: string | null;
    specialty?: string;
    topic?: string;
    section?: string;
    catedra?: string | null;
}

const route = useRoute();
const cards = ref<Flashcard[]>([]);
const index = ref(0);
const flipped = ref(false);
const loading = ref(false);

const current = computed(() => cards.value[index.value] ?? { front: '', back: '' });

async function load() {
    loading.value = true;
    try {
        const params: Record<string, any> = { limit: 30 };
        if (route.query.specialty) params.specialty_id = Number(route.query.specialty);
        const { data } = await axios.get('/flashcards', { params });
        cards.value = Array.isArray(data?.flashcards) ? data.flashcards : [];
        index.value = 0;
        flipped.value = false;
    } catch (e) {
        // Silenciar errores para no romper el scheduler de Vue con DevTools.
        // El componente se queda con cards=[] y un mensaje genérico.
        cards.value = [];
        index.value = 0;
        flipped.value = false;
        // eslint-disable-next-line no-console
        console.warn('No se pudieron cargar las flashcards:', e);
    } finally {
        loading.value = false;
    }
}

function next() {
    if (index.value < cards.value.length - 1) {
        index.value++;
        flipped.value = false;
    }
}

function prev() {
    if (index.value > 0) {
        index.value--;
        flipped.value = false;
    }
}

async function mark(knew: boolean) {
    const card = current.value;
    if (card?.id) {
        // Enviar en segundo plano para no demorar la animación
        axios.post(`/flashcards/${card.id}/progress`, { knew_it: knew }).catch(() => {});
    }

    if (index.value < cards.value.length - 1) {
        index.value++;
        flipped.value = false;
    } else {
        flipped.value = false;
    }
}

onMounted(load);
watch(() => route.query.specialty, load);
</script>
