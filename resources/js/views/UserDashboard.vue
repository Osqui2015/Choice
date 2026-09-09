<template>
    <div class="min-h-screen bg-slate-900 text-slate-100">
        <Navbar />
        <main class="max-w-4xl mx-auto px-4 sm:px-6 py-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-white">¡Hola {{ auth.user?.name }}!</h1>
                <p class="text-slate-400 mt-1">Tu progreso en Choice</p>
            </div>

            <div v-if="loading" class="text-center py-12 text-slate-400">Cargando métricas…</div>

            <div v-else-if="stats" class="space-y-6">
                <!-- Tarjetas de métricas -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
                        <p class="text-xs uppercase tracking-wider text-slate-400">🎯 Acierto</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-300">{{ stats.global.accuracy }}%</p>
                        <p class="text-xs text-slate-500 mt-1">{{ stats.global.total_correct }} / {{ stats.global.total_answered }}</p>
                    </div>
                    <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
                        <p class="text-xs uppercase tracking-wider text-slate-400">📊 Avance</p>
                        <p class="mt-1 text-2xl font-bold text-indigo-300">{{ stats.global.progress }}%</p>
                        <p class="text-xs text-slate-500 mt-1">{{ stats.global.total_answered }} / {{ stats.global.total_questions }}</p>
                    </div>
                    <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
                        <p class="text-xs uppercase tracking-wider text-slate-400">🔥 Racha</p>
                        <p class="mt-1 text-2xl font-bold text-orange-300">{{ stats.streak.current_daily_streak }}</p>
                        <p class="text-xs text-slate-500 mt-1">días seguidos</p>
                    </div>
                    <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
                        <p class="text-xs uppercase tracking-wider text-slate-400">⭐ Guardadas</p>
                        <p class="mt-1 text-2xl font-bold text-yellow-300">{{ stats.global.bookmarks }}</p>
                        <p class="text-xs text-slate-500 mt-1">preguntas</p>
                    </div>
                </div>

                <!-- Barras por especialidad -->
                <div class="bg-slate-800 border border-slate-700 rounded-2xl p-5">
                    <h2 class="text-lg font-bold text-white mb-4">📚 Rendimiento por Especialidad</h2>
                    <div class="space-y-4">
                        <div v-for="s in stats.by_specialty" :key="s.id">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-sm font-semibold text-slate-200">{{ s.name }}</span>
                                <span class="text-xs text-slate-400">
                                    <span class="text-emerald-300 font-semibold">{{ s.accuracy }}%</span> acierto ·
                                    <span class="text-indigo-300">{{ s.progress }}%</span> avance
                                </span>
                            </div>
                            <div class="h-2.5 rounded-full bg-slate-700 overflow-hidden">
                                <div
                                    class="h-full bg-gradient-to-r from-indigo-500 to-purple-500 transition-all"
                                    :style="{ width: `${s.progress}%` }"
                                ></div>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                {{ s.answered }} / {{ s.total }} respondidas · {{ s.correct }} correctas
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Accesos rápidos -->
                <div class="grid sm:grid-cols-4 gap-3">
                    <router-link
                        to="/study"
                        class="p-5 rounded-2xl bg-slate-800/50 border border-slate-700 hover:border-indigo-500/50 transition text-center"
                    >
                        <div class="text-3xl mb-1">📖</div>
                        <div class="text-sm font-semibold text-white">Estudiar</div>
                    </router-link>
                    <router-link
                        to="/errors"
                        class="p-5 rounded-2xl bg-slate-800/50 border border-slate-700 hover:border-rose-500/50 transition text-center"
                    >
                        <div class="text-3xl mb-1">🔄</div>
                        <div class="text-sm font-semibold text-white">Banco de Fallos</div>
                    </router-link>
                    <router-link
                        to="/bookmarked"
                        class="p-5 rounded-2xl bg-slate-800/50 border border-slate-700 hover:border-yellow-500/50 transition text-center"
                    >
                        <div class="text-3xl mb-1">⭐</div>
                        <div class="text-sm font-semibold text-white">Guardadas</div>
                    </router-link>
                    <router-link
                        to="/flashcards"
                        class="p-5 rounded-2xl bg-slate-800/50 border border-slate-700 hover:border-emerald-500/50 transition text-center"
                    >
                        <div class="text-3xl mb-1">📇</div>
                        <div class="text-sm font-semibold text-white">Flashcards</div>
                    </router-link>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import axios from 'axios';
import Navbar from '@/components/Navbar.vue';
import { useAuthStore } from '@/stores/auth';

interface Stats {
    global: {
        total_questions: number;
        total_answered: number;
        total_correct: number;
        accuracy: number;
        progress: number;
        total_flashcards: number;
        flashcards_learned: number;
        bookmarks: number;
    };
    by_specialty: {
        id: number;
        name: string;
        code: string;
        total: number;
        answered: number;
        correct: number;
        accuracy: number;
        progress: number;
    }[];
    streak: {
        current_daily_streak: number;
        max_daily_streak: number;
        current_correct_streak: number;
        max_correct_streak: number;
        last_activity_date: string | null;
    };
    quota: any;
}

const auth = useAuthStore();
const stats = ref<Stats | null>(null);
const loading = ref(true);

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get('/user/stats');
        stats.value = data;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
