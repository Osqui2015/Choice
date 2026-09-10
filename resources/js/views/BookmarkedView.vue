<template>
    <div class="min-h-screen bg-slate-900 text-slate-100">
        <Navbar />
        <main class="max-w-3xl w-full mx-auto px-4 sm:px-6 py-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-100">⭐ Preguntas Guardadas</h1>
                    <p class="text-sm text-slate-400 mt-1">Tus favoritas para repasar cuando quieras</p>
                </div>
                <router-link to="/study" class="text-sm text-slate-400 hover:text-slate-100">← Volver</router-link>
            </div>

            <div v-if="loading" class="text-center py-12 text-slate-400">Cargando…</div>

            <div v-else-if="!questions.length" class="bg-slate-800 border border-slate-700 rounded-xl p-12 text-center">
                <p class="text-3xl mb-2">⭐</p>
                <p class="text-slate-100 font-semibold">No tenés preguntas guardadas todavía</p>
                <p class="text-sm text-slate-400 mt-1">Tocá la estrella en cualquier pregunta para guardarla acá.</p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="q in questions"
                    :key="q.id"
                    class="bg-slate-800 border border-slate-700 rounded-xl p-4"
                >
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                            <span v-if="q.specialty" class="px-2 py-0.5 rounded-full bg-indigo-500/20 border border-indigo-500/30 text-indigo-300">{{ q.specialty.name }}</span>
                            <span v-if="q.topic">· {{ q.topic.name }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                @click="unbookmark(q)"
                                title="Quitar de guardados"
                                class="text-yellow-300 hover:text-yellow-200 text-sm"
                            >★</button>
                            <button
                                @click="goQuiz(q)"
                                class="text-xs px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold"
                            >Practicar →</button>
                        </div>
                    </div>
                    <p class="text-sm text-slate-200 line-clamp-3">{{ q.statement }}</p>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import Navbar from '@/components/Navbar.vue';
import type { Question } from '@/stores/quiz';

const router = useRouter();
const route = useRoute();
const loading = ref(false);
const questions = ref<Question[]>([]);

async function load() {
    loading.value = true;
    try {
        const params: Record<string, any> = { limit: 100 };
        if (route.query.specialty) params.specialty_id = Number(route.query.specialty);
        const { data } = await axios.get('/questions/bookmarked', { params });
        questions.value = Array.isArray(data?.questions) ? data.questions : [];
    } catch {
        questions.value = [];
    } finally {
        loading.value = false;
    }
}

function goQuiz(q: Question) {
    router.push({ name: 'quiz', query: { specialty: q.specialty?.id } });
}

async function unbookmark(q: Question) {
    await axios.post(`/questions/${q.id}/bookmark`);
    questions.value = questions.value.filter((x) => x.id !== q.id);
}

onMounted(load);
watch(() => route.query.specialty, load);
</script>
