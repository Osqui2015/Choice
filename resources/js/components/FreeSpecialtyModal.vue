<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
            @click.self="onClose"
        >
            <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-white">Elegí tu materia gratis</h2>
                        <p class="text-sm text-slate-400 mt-0.5">
                            Vas a tener <strong class="text-emerald-300">5 preguntas por día</strong> de la materia que elijas.
                            Podés cambiarla cuando quieras.
                        </p>
                    </div>
                    <button @click="onClose" class="text-slate-400 hover:text-white text-2xl leading-none">×</button>
                </div>

                <div class="space-y-2 max-h-72 overflow-y-auto">
                    <button
                        v-for="sp in specialties"
                        :key="sp.id"
                        @click="onSelect(sp.id)"
                        :disabled="submitting"
                        class="w-full text-left p-3 rounded-lg border bg-slate-900/50 border-slate-700 hover:border-emerald-500/60 hover:bg-emerald-500/10 transition flex items-center gap-3 disabled:opacity-50"
                    >
                        <span class="text-2xl">📚</span>
                        <div>
                            <p class="text-white font-semibold">{{ sp.name }}</p>
                            <p class="text-xs text-slate-500">{{ sp.code }}</p>
                        </div>
                    </button>
                </div>

                <p class="text-xs text-slate-500 text-center mt-4">
                    💡 Si querés más materias o acceso ilimitado, <a href="/pricing" class="text-emerald-400 hover:underline">mirá los planes pagos</a>.
                </p>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import type { SpecialtyLite } from '@/stores/subscription';

defineProps<{
    specialties: SpecialtyLite[];
    submitting: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'confirm', specialtyId: number): void;
}>();

function onClose() {
    emit('close');
}

function onSelect(id: number) {
    emit('confirm', id);
}
</script>
