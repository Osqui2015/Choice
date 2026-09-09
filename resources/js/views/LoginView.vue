<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 px-4">
        <div class="w-full max-w-md bg-slate-800/80 backdrop-blur border border-slate-700 rounded-2xl shadow-2xl p-8">
            <div class="text-center mb-8">
                <img src="/icons/icon-192.png" alt="Choice" class="w-16 h-16 mx-auto mb-3 rounded-2xl shadow-lg shadow-indigo-500/25" />
                <h1 class="text-3xl font-bold text-white tracking-tight">Choice</h1>
                <p class="text-slate-400 mt-1 text-sm">Iniciá sesión para continuar</p>
            </div>

            <form @submit.prevent="onSubmit" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Email</label>
                    <input
                        v-model="email"
                        type="email"
                        required
                        autocomplete="email"
                        class="w-full px-4 py-2.5 rounded-lg bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        placeholder="tu@email.com"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Contraseña</label>
                    <input
                        v-model="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full px-4 py-2.5 rounded-lg bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        placeholder="••••••••"
                    />
                </div>

                <div v-if="auth.error" class="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-3 py-2">
                    {{ auth.error }}
                </div>

                <button
                    type="submit"
                    :disabled="auth.loading"
                    class="w-full py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold transition"
                >
                    <span v-if="auth.loading">Ingresando…</span>
                    <span v-else>Entrar</span>
                </button>
            </form>

            <!--
                Usuarios de prueba: solo se muestran en entorno de desarrollo.
                En producción (vite build) este bloque no aparece.
                Activable también con VITE_SHOW_DEMO_LOGIN_HINT=true en .env
            -->
            <div
                v-if="showDemoUsers"
                class="mt-6 pt-6 border-t border-slate-700 text-xs text-slate-400 space-y-1"
            >
                <p class="font-semibold text-slate-300">Usuarios de prueba:</p>
                <p>Admin: <code class="text-indigo-300">admin@choice.test</code> / <code class="text-indigo-300">password</code></p>
                <p>Usuario: <code class="text-indigo-300">usuario@choice.test</code> / <code class="text-indigo-300">password</code></p>
            </div>

            <p class="mt-4 text-center text-sm text-slate-400">
                ¿No tenés cuenta?
                <router-link to="/register" class="text-indigo-300 hover:text-indigo-200 font-semibold">Creá tu cuenta gratis</router-link>
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const email = ref('');
const password = ref('');
const auth = useAuthStore();
const router = useRouter();

/**
 * Mostrar usuarios demo solo en dev, o si explícitamente se activa
 * con VITE_SHOW_DEMO_LOGIN_HINT=true en el .env.
 */
const showDemoUsers = computed(() => {
    const explicit = import.meta.env.VITE_SHOW_DEMO_LOGIN_HINT;
    if (explicit === 'true' || explicit === true) return true;
    if (explicit === 'false' || explicit === false) return false;
    return import.meta.env.DEV; // default: dev sí, prod no
});

async function onSubmit() {
    const ok = await auth.login(email.value, password.value);
    if (ok) {
        router.push({ name: auth.homeRoute });
    }
}
</script>
