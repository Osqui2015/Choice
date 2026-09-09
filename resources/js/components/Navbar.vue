<template>
    <header class="sticky top-0 z-30 bg-slate-900/80 backdrop-blur border-b border-slate-800">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
            <router-link to="/study" class="flex items-center gap-2.5">
                <img src="/icons/icon-192.png" alt="Choice" width="32" height="32" class="w-8 h-8 rounded-lg shadow-md shadow-indigo-500/20 shrink-0 object-cover" />
                <span class="font-bold text-white tracking-tight text-lg hidden sm:inline">Choice</span>
            </router-link>

            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Racha diaria -->
                <div
                    v-if="streak && streak.current_daily_streak > 0"
                    class="px-2.5 py-1 rounded-full bg-orange-500/20 border border-orange-500/30 text-orange-300 text-xs font-semibold flex items-center gap-1"
                    :title="`Racha diaria · máxima ${streak.max_daily_streak}`"
                >
                    🔥 {{ streak.current_daily_streak }}
                </div>

                <!-- Plan badge (real, según SubscriptionStore) -->
                <button
                    v-if="sub.hasActive && sub.active"
                    @click="router.push('/my-subscription')"
                    :title="`Plan ${sub.active.plan.name} · ${sub.active.days_remaining} días restantes`"
                    class="px-2.5 py-1 rounded-full text-xs font-semibold flex items-center gap-1.5 border transition bg-emerald-500/20 border-emerald-500/40 text-emerald-200 hover:bg-emerald-500/30"
                >
                    <span class="text-sm">✅</span>
                    <span>{{ sub.active.plan.name }}</span>
                    <span v-if="sub.active.is_unlimited" class="text-[10px] opacity-80">∞</span>
                    <span v-else class="text-[10px] opacity-80">·{{ sub.active.days_remaining }}d</span>
                </button>
                <button
                    v-else-if="sub.pending"
                    @click="router.push('/my-subscription')"
                    class="px-2.5 py-1 rounded-full text-xs font-semibold flex items-center gap-1.5 border bg-amber-500/20 border-amber-500/40 text-amber-200 hover:bg-amber-500/30 transition"
                    title="Tu solicitud está siendo revisada"
                >
                    <span class="text-sm">⏳</span>
                    <span>Pendiente</span>
                </button>
                <button
                    v-else
                    @click="onQuotaClick"
                    :title="sub.freeQuota?.is_exhausted
                        ? 'Cuota diaria agotada · click para ver planes'
                        : 'Plan Gratuito · click para ver planes'"
                    class="px-2.5 py-1 rounded-full text-xs font-semibold flex items-center gap-1 border transition"
                    :class="sub.freeQuota?.is_exhausted
                        ? 'bg-red-500/20 border-red-500/30 text-red-300 hover:bg-red-500/30'
                        : 'bg-emerald-500/20 border-emerald-500/30 text-emerald-300 hover:bg-emerald-500/30'"
                >
                    <span v-if="sub.freeQuota?.is_exhausted">🔒 Agotado</span>
                    <span v-else-if="sub.freeQuota">
                        ⚡ {{ sub.freeQuota.questions_answered }}/{{ sub.freeQuota.daily_limit }}
                    </span>
                    <span v-else>⚡ FREE</span>
                </button>

                <!-- Menú usuario -->
                <div class="relative" ref="menuRef">
                    <button
                        @click="menuOpen = !menuOpen"
                        class="w-9 h-9 rounded-full bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white text-sm font-semibold transition"
                    >
                        {{ initials }}
                    </button>
                    <div
                        v-if="menuOpen"
                        class="absolute right-0 mt-2 w-56 bg-slate-800 border border-slate-700 rounded-lg shadow-xl py-1"
                    >
                        <div class="px-4 py-2 border-b border-slate-700">
                            <p class="text-sm text-white font-semibold truncate">{{ auth.user?.name }}</p>
                            <p class="text-xs text-slate-400 truncate">{{ auth.user?.email }}</p>
                        </div>
                        <router-link
                            to="/study"
                            @click="menuOpen = false"
                            class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-700"
                        >📖 Estudiar</router-link>
                        <router-link
                            to="/dashboard"
                            @click="menuOpen = false"
                            class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-700"
                        >📊 Mi Progreso</router-link>
                        <router-link
                            to="/my-subscription"
                            @click="menuOpen = false"
                            class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-700"
                        >💎 Mi Suscripción</router-link>
                        <router-link
                            to="/pricing"
                            @click="menuOpen = false"
                            class="block px-4 py-2 text-sm text-emerald-300 hover:bg-slate-700"
                        >⚡ Ver planes</router-link>

                        <!-- Banco de Fallos (PRO) -->
                        <button
                            v-if="!sub.hasActive"
                            @click="menuOpen = false; showPricing = true"
                            class="w-full text-left flex items-center justify-between px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 transition"
                        >
                            <span>🔄 Banco de fallos</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30">PRO</span>
                        </button>
                        <router-link
                            v-else
                            to="/errors"
                            @click="menuOpen = false"
                            class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-700"
                        >🔄 Banco de fallos</router-link>

                        <!-- Flashcards (PRO) -->
                        <button
                            v-if="!sub.hasActive || !sub.active?.plan.includes_flashcards"
                            @click="menuOpen = false; showPricing = true"
                            class="w-full text-left flex items-center justify-between px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 transition"
                        >
                            <span>📇 Flashcards</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30">PRO</span>
                        </button>
                        <router-link
                            v-else
                            to="/flashcards"
                            @click="menuOpen = false"
                            class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-700"
                        >📇 Flashcards</router-link>
                        <router-link
                            to="/bookmarked"
                            @click="menuOpen = false"
                            class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-700"
                        >⭐ Guardadas</router-link>
                        <router-link
                            v-if="auth.isAdmin"
                            to="/admin"
                            @click="menuOpen = false"
                            class="block px-4 py-2 text-sm text-indigo-300 hover:bg-slate-700"
                        >🛠️ Panel Admin</router-link>
                        <router-link
                            v-if="auth.isAdmin"
                            to="/admin/subscriptions"
                            @click="menuOpen = false"
                            class="block px-4 py-2 text-sm text-indigo-300 hover:bg-slate-700"
                        >💳 Suscripciones</router-link>
                        <button
                            @click="onLogout"
                            class="w-full text-left px-4 py-2 text-sm text-red-300 hover:bg-slate-700 border-t border-slate-700"
                        >Cerrar sesión</button>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <PricingModal :open="showPricing" @close="showPricing = false" />
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useQuotaStore } from '@/stores/quota';
import { useSubscriptionStore } from '@/stores/subscription';
import PricingModal from '@/components/PricingModal.vue';

const auth = useAuthStore();
const quotaStore = useQuotaStore();
const sub = useSubscriptionStore();
const router = useRouter();

const menuOpen = ref(false);
const menuRef = ref<HTMLElement | null>(null);
const showPricing = ref(false);

const quota = computed(() => quotaStore.quota);
const streak = computed(() => quotaStore.streak);

const initials = computed(() => {
    const n = auth.user?.name ?? '?';
    return n.split(' ').slice(0, 2).map((p) => p[0]?.toUpperCase()).join('');
});

const formatCountdown = computed(() => quotaStore.countdown);

function onLogout() {
    menuOpen.value = false;
    auth.logout().then(() => router.push({ name: 'login' }));
}

function onQuotaClick() {
    showPricing.value = true;
}

function onClickOutside(e: MouseEvent) {
    if (menuOpen.value && menuRef.value && !menuRef.value.contains(e.target as Node)) {
        menuOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onClickOutside);
    if (auth.user) {
        quotaStore.fetch();
        sub.fetchMySubscription();
    }
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onClickOutside);
    quotaStore.cleanup();
});
</script>
