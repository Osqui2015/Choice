<template>
    <div class="min-h-screen bg-slate-900 text-slate-100">
        <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-10">
            <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-white">{{ title }}</h1>
                    <p v-if="auth.user" class="text-xs text-slate-400">
                        {{ auth.user.name }} · {{ auth.user.roles.join(', ') }}
                    </p>
                </div>
                <button
                    @click="onLogout"
                    class="px-4 py-2 text-sm rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 transition"
                >
                    Cerrar sesión
                </button>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-6 py-8">
            <slot />
        </main>
    </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

defineProps<{ title: string; accent?: 'indigo' | 'emerald' }>();

const auth = useAuthStore();
const router = useRouter();

async function onLogout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>
