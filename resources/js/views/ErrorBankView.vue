<template>
    <div class="min-h-screen bg-slate-900 text-slate-100">
        <Navbar />
        <main class="max-w-3xl w-full mx-auto px-4 sm:px-6 py-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-100">🔄 Banco de Fallos</h1>
                    <p class="text-sm text-slate-400 mt-1">Preguntas que fallaste y aún no re-acertaste</p>
                </div>
                <router-link
                    to="/study"
                    class="text-sm text-slate-400 hover:text-slate-100"
                >← Volver</router-link>
            </div>

            <div v-if="loading" class="text-center py-12 text-slate-400">Cargando…</div>

            <div v-else-if="!questions.length" class="bg-slate-800 border border-slate-700 rounded-xl p-12 text-center">
                <p class="text-3xl mb-2">🎉</p>
                <p class="text-slate-100 font-semibold">¡No tenés preguntas pendientes!</p>
                <p class="text-sm text-slate-400 mt-1">Andá a <router-link to="/study" class="text-indigo-300 underline">estudiar</router-link> para acumular práctica.</p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="q in questions"
                    :key="q.id"
                    class="bg-slate-800 border border-slate-700 rounded-xl p-4"
                >
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                            <span class="px-2 py-0.5 rounded-full bg-rose-500/20 border border-rose-500/30 text-rose-300">{{ q.error_count }} fallos</span>
                            <span v-if="q.specialty" class="px-2 py-0.5 rounded-full bg-indigo-500/20 border border-indigo-500/30 text-indigo-300">{{ q.specialty.name }}</span>
                            <span v-if="q.topic">· {{ q.topic.name }}</span>
                        </div>
                        <button
                            @click="goQuiz(q)"
                            class="text-xs px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold"
                        >Practicar →</button>
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
import Navbar from '@/components/Navbar.vue';
import { useQuizStore, type Question } from '@/stores/quiz';

const router = useRouter();
const route = useRoute();
const quiz = useQuizStore();

const loading = ref(false);
const questions = ref<Question[]>([]);

async function load() {
    loading.value = true;
    try {
        await quiz.fetchErrors({
            specialty_id: route.query.specialty ? Number(route.query.specialty) : undefined,
            limit: 50,
        });
        questions.value = quiz.questions;
    } finally {
        loading.value = false;
    }
}

function goQuiz(q: Question) {
    router.push({ name: 'quiz', query: { specialty: q.specialty?.id } });
}

onMounted(load);
watch(() => route.query.specialty, load);
</script>
