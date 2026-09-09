<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
            @click.self="onClose"
        >
            <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-lg w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-white">{{ plan.name }}</h2>
                        <p class="text-sm text-slate-400">{{ plan.formatted_price }} por {{ plan.duration_days }} días</p>
                    </div>
                    <button @click="onClose" class="text-slate-400 hover:text-white text-2xl leading-none">×</button>
                </div>

                <p v-if="plan.description" class="text-sm text-slate-300 mb-4">{{ plan.description }}</p>

                <!-- Selección de especialidades (si no es Full) -->
                <div v-if="!plan.is_unlimited" class="mb-4">
                    <label class="block text-sm font-semibold text-slate-300 mb-2">
                        Elegí las materias ({{ selected.length }} / {{ plan.max_specialties }})
                    </label>
                    <div class="space-y-2 max-h-60 overflow-y-auto">
                        <label
                            v-for="sp in specialties"
                            :key="sp.id"
                            class="flex items-center gap-2 p-2.5 rounded-lg border bg-slate-900/50 cursor-pointer hover:bg-slate-900"
                            :class="selected.includes(sp.id) ? 'border-indigo-500/60 bg-indigo-500/10' : 'border-slate-700'"
                        >
                            <input
                                type="checkbox"
                                :value="sp.id"
                                v-model="selected"
                                :disabled="!selected.includes(sp.id) && selected.length >= plan.max_specialties!"
                                class="rounded text-indigo-500 focus:ring-indigo-500 focus:ring-offset-0 bg-slate-700 border-slate-600 disabled:opacity-50"
                            />
                            <span class="text-sm text-white">{{ sp.name }}</span>
                            <span class="text-xs text-slate-500 ml-auto">{{ sp.code }}</span>
                        </label>
                    </div>
                    <p v-if="!isValidCount" class="text-xs text-rose-300 mt-2">
                        Tenés que elegir entre 1 y {{ plan.max_specialties }} materias.
                    </p>
                </div>
                <p v-else class="mb-4 text-sm text-emerald-300">
                    Con este plan vas a tener acceso a <strong>TODAS</strong> las especialidades automáticamente.
                </p>

                <!-- Nota opcional -->
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-300 mb-2">Mensaje para el admin (opcional)</label>
                    <textarea
                        v-model="notes"
                        rows="2"
                        maxlength="500"
                        class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                        placeholder="Ej: Quiero preparar el final de clínica, soy alumno de 5to año..."
                    />
                </div>

                <button
                    @click="onConfirm"
                    :disabled="!isValidCount || submitting"
                    class="w-full py-3 rounded-lg bg-emerald-500 hover:bg-emerald-400 disabled:opacity-40 disabled:cursor-not-allowed text-slate-900 font-bold transition flex items-center justify-center gap-2"
                >
                    <span v-if="submitting">Procesando…</span>
                    <span v-else>💬 Continuar y abrir WhatsApp</span>
                </button>

                <p class="text-xs text-slate-500 text-center mt-3">
                    Te vamos a redirigir a WhatsApp con un mensaje pre-armado para coordinar el pago.
                </p>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import type { Plan, SpecialtyLite } from '@/stores/subscription';

const props = defineProps<{
    plan: Plan;
    specialties: SpecialtyLite[];
    submitting: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'confirm', payload: { specialty_ids: number[]; notes?: string }): void;
}>();

const selected = ref<number[]>([]);
const notes = ref('');

const isValidCount = computed(() => {
    if (props.plan.is_unlimited) return true;
    return selected.value.length > 0 && selected.value.length <= (props.plan.max_specialties ?? 0);
});

function onClose() {
    emit('close');
}

function onConfirm() {
    if (!isValidCount.value) return;
    emit('confirm', {
        specialty_ids: selected.value,
        notes: notes.value.trim() || undefined,
    });
}
</script>
