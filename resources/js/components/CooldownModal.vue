<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
            @click.self="onClose"
        >
            <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-md w-full p-8 text-center shadow-2xl">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-3xl">
                    🔒
                </div>
                <h2 class="text-2xl font-bold text-white">¡Completaste tus 10 preguntas de hoy!</h2>
                <p class="text-slate-400 mt-2 text-sm">Volvé mañana para mantener tu racha 🔥</p>

                <div class="mt-6 bg-slate-900 border border-slate-700 rounded-xl p-5">
                    <p class="text-xs uppercase tracking-wider text-slate-400">Tus próximas 10 preguntas se desbloquean en</p>
                    <p class="mt-2 text-4xl font-mono font-bold text-emerald-300 tabular-nums">
                        {{ quotaStore.countdown }}
                    </p>
                </div>

                <div class="mt-6 bg-gradient-to-br from-indigo-600/20 to-purple-600/20 border border-indigo-500/30 rounded-xl p-5">
                    <p class="text-white font-semibold">⚡ Acceso Ilimitado Premium</p>
                    <p class="text-sm text-slate-300 mt-1">Estudiá sin límites, sin esperas y con todas las funciones avanzadas.</p>
                    <button
                        @click="openPricing = true"
                        class="mt-4 w-full py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-bold transition"
                    >💬 Ver planes y obtener Premium</button>
                </div>

                <button
                    @click="onClose"
                    class="mt-5 text-slate-400 hover:text-white text-sm transition"
                >Seguir revisando mis respuestas</button>
            </div>
        </div>
        <PricingModal :open="openPricing" @close="openPricing = false" />
    </Teleport>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useQuotaStore } from '@/stores/quota';
import PricingModal from '@/components/PricingModal.vue';

const props = defineProps<{ open: boolean; redirectOnClose?: boolean }>();
const emit = defineEmits<{ (e: 'close'): void }>();

const quotaStore = useQuotaStore();
const router = useRouter();
const openPricing = ref(false);

function onClose() {
    emit('close');
    if (props.redirectOnClose) {
        router.push('/study');
    }
}

watch(
    () => props.open,
    (v) => {
        if (v) quotaStore.startTimer();
    },
);
</script>
