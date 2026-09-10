<template>
    <Teleport to="body">
        <div
            v-if="open && specialty"
            class="fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity animate-fade-in"
            @click.self="onClose"
        >
            <div
                class="bg-slate-800 border border-slate-700 rounded-2xl max-w-lg w-full p-6 shadow-2xl flex flex-col max-h-[90vh] overflow-hidden text-slate-100"
            >
                <!-- Header -->
                <div class="flex items-start justify-between pb-4 border-b border-slate-700/80">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">{{ specialtyIcon }}</span>
                            <h2 class="text-xl font-bold text-slate-100">{{ specialty.name }}</h2>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">
                            Seleccioná un tema específico para practicar o estudiá toda la materia.
                        </p>
                    </div>
                    <button
                        @click="onClose"
                        class="text-slate-400 hover:text-slate-100 text-2xl leading-none p-1 rounded-lg hover:bg-slate-700/50 transition"
                    >
                        ×
                    </button>
                </div>

                <!-- Buscador de temas -->
                <div v-if="topics.length > 6" class="pt-3 pb-2">
                    <div class="relative">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Buscar tema (ej. TEP, Asma, Insuficiencia...)"
                            class="w-full bg-slate-900/70 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition"
                        />
                        <span
                            v-if="search"
                            @click="search = ''"
                            class="absolute right-3 top-2 text-xs text-slate-400 hover:text-slate-100 cursor-pointer"
                        >
                            ✕
                        </span>
                    </div>
                </div>

                <!-- Lista de opciones -->
                <div class="space-y-2 py-3 overflow-y-auto flex-1 pr-1 custom-scrollbar">
                    <!-- Opción: Todos los temas -->
                    <button
                        v-if="!search"
                        @click="onSelectTopic(null)"
                        class="w-full text-left p-3.5 rounded-xl border-2 bg-gradient-to-r from-indigo-950/40 to-slate-800/80 border-indigo-500/50 hover:border-indigo-400 hover:bg-indigo-900/30 transition flex items-center justify-between group"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-xl group-hover:scale-110 transition-transform">🎯</span>
                            <div>
                                <p class="text-sm font-bold text-slate-100 group-hover:text-indigo-200 transition-colors">
                                    Todos los temas de {{ specialty.name }}
                                </p>
                                <p class="text-xs text-slate-400">Preguntas aleatorias de toda la materia</p>
                            </div>
                        </div>
                        <span class="text-xs px-2.5 py-1 rounded-full bg-indigo-500/20 text-indigo-300 font-semibold border border-indigo-500/30 shrink-0">
                            {{ totalQuestions }} pregs
                        </span>
                    </button>

                    <div v-if="!search && topics.length > 0" class="pt-2 pb-1">
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-1">
                            Temas disponibles ({{ filteredTopics.length }})
                        </span>
                    </div>

                    <!-- Lista de Subtemas individuales -->
                    <div v-if="filteredTopics.length > 0" class="grid sm:grid-cols-2 gap-2">
                        <button
                            v-for="topic in filteredTopics"
                            :key="topic.id"
                            @click="onSelectTopic(topic.id)"
                            class="w-full text-left p-3 rounded-xl border bg-slate-900/50 border-slate-700/80 hover:border-emerald-500/60 hover:bg-slate-800 transition flex items-center justify-between group"
                        >
                            <div class="min-w-0 pr-2">
                                <p class="text-xs font-semibold text-slate-200 group-hover:text-emerald-300 transition-colors truncate">
                                    {{ topic.name }}
                                </p>
                                <p class="text-[10px] text-slate-500">
                                    Tema {{ topic.code ? `#${topic.code}` : '' }}
                                </p>
                            </div>
                            <span class="text-[11px] px-2 py-0.5 rounded-md bg-slate-800 text-emerald-400 border border-slate-700 shrink-0 font-medium">
                                {{ topic.questions_count }}
                            </span>
                        </button>
                    </div>

                    <div v-else class="text-center py-8 text-slate-500 text-xs">
                        No se encontraron temas con "{{ search }}".
                    </div>
                </div>

                <!-- Footer -->
                <div class="pt-3 border-t border-slate-700/80 flex justify-end">
                    <button
                        @click="onClose"
                        class="px-4 py-2 rounded-xl bg-slate-700/50 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition"
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';

export interface TopicItem {
    id: number;
    code: string;
    name: string;
    slug: string;
    questions_count: number;
}

export interface SpecialtyWithTopics {
    id: number;
    code: string;
    name: string;
    slug: string;
    icon: string | null;
    color: string | null;
    topics?: TopicItem[];
    stats?: {
        total_questions: number;
    };
}

const props = defineProps<{
    open: boolean;
    specialty: SpecialtyWithTopics | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'select', topicId: number | null): void;
}>();

const search = ref('');

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) search.value = '';
    }
);

const topics = computed<TopicItem[]>(() => {
    return props.specialty?.topics ?? [];
});

const totalQuestions = computed(() => {
    return props.specialty?.stats?.total_questions ?? 0;
});

const specialtyIcon = computed(() => {
    const name = props.specialty?.name?.toLowerCase() ?? '';
    if (name.includes('cardio')) return '❤️';
    if (name.includes('nefro')) return '🩺';
    if (name.includes('neumo')) return '🫁';
    return '📚';
});

const filteredTopics = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return topics.value;
    return topics.value.filter(
        (t) =>
            t.name.toLowerCase().includes(q) ||
            t.code?.toLowerCase().includes(q)
    );
});

function onClose() {
    emit('close');
}

function onSelectTopic(topicId: number | null) {
    emit('select', topicId);
}
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.4);
    border-radius: 9999px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(100, 116, 139, 0.4);
    border-radius: 9999px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(100, 116, 139, 0.7);
}
</style>
