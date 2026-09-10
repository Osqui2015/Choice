<template>
    <AdminLayout>
        <div v-if="loading" class="text-center py-12 text-slate-400">Cargando métricas…</div>
        <div v-else-if="stats" class="space-y-6">
            <h1 class="text-2xl font-bold text-slate-100">📊 Panel General</h1>

            <!-- Métricas principales -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs uppercase tracking-wider text-slate-400">👥 Usuarios</p>
                    <p class="mt-1 text-2xl font-bold text-slate-100">{{ stats.users.total }}</p>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ stats.users.active }} activos · {{ stats.users.inactive }} inactivos
                    </p>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs uppercase tracking-wider text-slate-400">⚡ Premium</p>
                    <p class="mt-1 text-2xl font-bold text-yellow-300">{{ stats.users.premium }}</p>
                    <p class="text-xs text-slate-500 mt-1">cuentas premium</p>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs uppercase tracking-wider text-slate-400">❓ Preguntas</p>
                    <p class="mt-1 text-2xl font-bold text-indigo-300">{{ stats.content.questions_active }}</p>
                    <p class="text-xs text-slate-500 mt-1">de {{ stats.content.questions_total }} totales</p>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs uppercase tracking-wider text-slate-400">📇 Flashcards</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-300">{{ stats.content.flashcards_active }}</p>
                    <p class="text-xs text-slate-500 mt-1">de {{ stats.content.flashcards_total }} totales</p>
                </div>
            </div>

            <!-- Actividad -->
            <div class="grid sm:grid-cols-3 gap-3">
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs uppercase tracking-wider text-slate-400">📈 Respuestas totales</p>
                    <p class="mt-1 text-2xl font-bold text-slate-100">{{ stats.activity.answers_total }}</p>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs uppercase tracking-wider text-slate-400">📅 Respuestas hoy</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-300">{{ stats.activity.answers_today }}</p>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs uppercase tracking-wider text-slate-400">🎯 Acierto global</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-300">{{ stats.activity.global_accuracy }}%</p>
                    <p class="text-xs text-slate-500 mt-1">{{ stats.activity.correct_total }} correctas</p>
                </div>
            </div>

            <!-- Top usuarios -->
            <div class="bg-slate-800 border border-slate-700 rounded-2xl p-5">
                <h2 class="text-lg font-bold text-slate-100 mb-3">🏆 Top 5 usuarios por respuestas correctas</h2>
                <div class="space-y-2">
                    <div v-for="(u, i) in stats.top_users" :key="u.id" class="flex items-center justify-between px-3 py-2 rounded-lg bg-slate-900/50">
                        <div class="flex items-center gap-3">
                            <span class="w-7 h-7 rounded-full bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 text-xs font-bold flex items-center justify-center">{{ i + 1 }}</span>
                            <div>
                                <p class="text-sm font-semibold text-slate-100">{{ u.name }}</p>
                                <p class="text-xs text-slate-400">{{ u.email }}</p>
                            </div>
                        </div>
                        <span class="text-sm font-bold text-emerald-300">{{ u.correct_count }} ✓</span>
                    </div>
                    <p v-if="!stats.top_users.length" class="text-sm text-slate-500 text-center py-4">Sin actividad todavía.</p>
                </div>
            </div>

            <!-- Por especialidad -->
            <div class="bg-slate-800 border border-slate-700 rounded-2xl p-5">
                <h2 class="text-lg font-bold text-slate-100 mb-3">📚 Por Especialidad</h2>
                <div class="space-y-3">
                    <div v-for="s in stats.by_specialty" :key="s.id" class="flex items-center justify-between p-3 rounded-lg bg-slate-900/50">
                        <div>
                            <p class="text-sm font-semibold text-slate-100">{{ s.name }} <span class="text-slate-500 text-xs">({{ s.code }})</span></p>
                            <p class="text-xs text-slate-400 mt-0.5">
                                <span :class="s.is_active ? 'text-emerald-300' : 'text-red-300'">{{ s.is_active ? 'Activa' : 'Inactiva' }}</span>
                                · {{ s.active_questions }} / {{ s.total_questions }} preguntas
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import axios from 'axios';
import AdminLayout from '@/components/AdminLayout.vue';

interface Stats {
    users: { total: number; active: number; inactive: number; premium: number };
    content: {
        specialties_total: number; specialties_active: number;
        questions_total: number; questions_active: number;
        flashcards_total: number; flashcards_active: number;
    };
    activity: {
        answers_total: number; answers_today: number;
        correct_total: number; global_accuracy: number;
        active_users_today: number;
    };
    top_users: { id: number; name: string; email: string; correct_count: number }[];
    by_specialty: {
        id: number; name: string; code: string; is_active: boolean;
        total_questions: number; active_questions: number;
    }[];
}

const stats = ref<Stats | null>(null);
const loading = ref(true);

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get('/admin/stats');
        stats.value = data;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
