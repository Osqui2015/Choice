<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
            @click.self="onClose"
        >
            <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-3xl w-full p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-100">Elegí tu plan</h2>
                        <p class="text-slate-400 text-sm mt-0.5">Acceso a las +3.230 preguntas y 328 flashcards</p>
                    </div>
                    <button @click="onClose" class="text-slate-400 hover:text-slate-100 text-2xl leading-none">×</button>
                </div>

                <div v-if="sub.loading" class="text-slate-400 py-8 text-center">Cargando…</div>

                <div v-else class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- Free -->
                    <div class="border border-slate-700 rounded-xl p-4 bg-slate-900/50 flex flex-col">
                        <p class="text-xs uppercase tracking-wider text-slate-400">Gratuito</p>
                        <p class="text-2xl font-bold text-slate-100 mt-1">$0</p>
                        <ul class="mt-3 space-y-1.5 text-xs text-slate-300 flex-1">
                            <li class="flex gap-1.5"><span class="text-emerald-400">✓</span> 5 preguntas diarias</li>
                            <li class="flex gap-1.5"><span class="text-emerald-400">✓</span> 1 materia a elección</li>
                            <li class="flex gap-1.5 text-rose-400"><span class="font-bold">✗</span> Sin flashcards</li>
                        </ul>
                    </div>

                    <!-- Básico -->
                    <div
                        v-for="plan in sub.plans"
                        :key="plan.id"
                        class="border rounded-xl p-4 flex flex-col"
                        :class="plan.is_unlimited
                            ? 'border-2 border-emerald-500/60 bg-gradient-to-br from-emerald-600/15 to-cyan-600/10'
                            : (plan.slug === 'estudiante'
                                ? 'border-2 border-indigo-500 bg-gradient-to-br from-indigo-600/15 to-purple-600/10'
                                : 'border-slate-700 bg-slate-900/50')"
                    >
                        <p
                            class="text-xs uppercase tracking-wider"
                            :class="plan.is_unlimited ? 'text-emerald-300' : (plan.slug === 'estudiante' ? 'text-indigo-300' : 'text-slate-400')"
                        >
                            {{ plan.name }}
                        </p>
                        <p class="text-2xl font-bold text-slate-100 mt-1">{{ plan.formatted_price }}</p>
                        <p class="text-[10px] text-slate-500 -mt-0.5">
                            {{ plan.is_unlimited ? 'Todas las materias' : `${plan.max_specialties} materia${plan.max_specialties === 1 ? '' : 's'}` }}
                        </p>
                        <ul class="mt-3 space-y-1.5 text-xs text-slate-300 flex-1">
                            <li class="flex gap-1.5"><span class="text-emerald-400">✓</span> Preguntas ilimitadas</li>
                            <li v-if="plan.includes_flashcards" class="flex gap-1.5"><span class="text-emerald-400">✓</span> Flashcards</li>
                            <li class="flex gap-1.5"><span class="text-emerald-400">✓</span> Banco de fallos</li>
                        </ul>
                    </div>
                </div>

                <router-link
                    to="/pricing"
                    @click="onClose"
                    class="mt-5 block w-full text-center py-3 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-bold transition text-base"
                >
                    💬 Ver planes y obtener por WhatsApp
                </router-link>
                <p class="text-center text-xs text-slate-500 mt-2">
                    Pagás por transferencia · Se activa en minutos.
                </p>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { onMounted, watch } from 'vue';
import { useSubscriptionStore } from '@/stores/subscription';

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ (e: 'close'): void }>();

const sub = useSubscriptionStore();

onMounted(async () => {
    if (sub.plans.length === 0) {
        await sub.fetchPlans();
    }
});

watch(() => props.open, (isOpen) => {
    if (isOpen && sub.plans.length === 0) {
        sub.fetchPlans();
    }
});

function onClose() {
    emit('close');
}
</script>
