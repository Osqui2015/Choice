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
                        {{ countdown }}
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
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useQuotaStore } from '@/stores/quota';
import PricingModal from '@/components/PricingModal.vue';

const props = defineProps<{ open: boolean; redirectOnClose?: boolean }>();
const emit = defineEmits<{ (e: 'close'): void }>();

const quotaStore = useQuotaStore();
const router = useRouter();
const openPricing = ref(false);

/**
 * Timer LOCAL del componente. Se inicia cuando se abre el modal
 * y se limpia automáticamente al cerrarlo o desmontarlo.
 *
 * Antes este countdown vivía en el Pinia store (quotaStore.startTimer),
 * pero generaba un error de Vue scheduler ("startTime undefined") en
 * producción porque el setInterval del store seguía corriendo fuera del
 * scope de cualquier componente.
 */
let timer: number | null = null;

function tick() {
    // Disparar reactividad en el computed de abajo
    now.value = Date.now();
}

const now = ref(Date.now());

const countdown = computed(() => {
    const secs = quotaStore.quota?.seconds_remaining ?? 0;
    if (secs <= 0) return '00:00:00';
    // Calcular offset en base a Date.now() para que se actualice solo
    const elapsedSinceFetch = Math.floor((now.value - fetchedAt.value) / 1000);
    const remaining = Math.max(0, secs - elapsedSinceFetch);
    if (remaining <= 0) return '00:00:00';
    const h = Math.floor(remaining / 3600);
    const m = Math.floor((remaining % 3600) / 60);
    const s = remaining % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

const fetchedAt = ref(Date.now());

function onClose() {
    emit('close');
    if (props.redirectOnClose) {
        router.push('/study');
    }
}

function startLocalTimer() {
    stopLocalTimer();
    fetchedAt.value = Date.now();
    now.value = Date.now();
    timer = window.setInterval(tick, 1000);
}

function stopLocalTimer() {
    if (timer !== null) {
        clearInterval(timer);
        timer = null;
    }
}

watch(
    () => props.open,
    (v) => {
        if (v) {
            startLocalTimer();
        } else {
            stopLocalTimer();
        }
    },
);

onMounted(() => {
    if (props.open) startLocalTimer();
});

onBeforeUnmount(() => {
    stopLocalTimer();
});
</script>
