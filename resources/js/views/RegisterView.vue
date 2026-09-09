<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 px-4">
        <div class="w-full max-w-md bg-slate-800/80 backdrop-blur border border-slate-700 rounded-2xl shadow-2xl p-8">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-white tracking-tight">Creá tu cuenta</h1>
                <p class="text-slate-400 mt-1 text-sm">Empezá con 10 preguntas gratis por día</p>
            </div>

            <form @submit.prevent="onSubmit" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Nombre completo</label>
                    <input
                        v-model="name"
                        type="text"
                        required
                        autocomplete="name"
                        class="w-full px-4 py-2.5 rounded-lg bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        placeholder="Juan Pérez"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Email</label>
                    <input
                        v-model="email"
                        type="email"
                        required
                        autocomplete="email"
                        class="w-full px-4 py-2.5 rounded-lg bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        :class="errors.email ? 'border-red-500' : ''"
                        placeholder="tu@email.com"
                    />
                    <p v-if="errors.email" class="text-xs text-red-400 mt-1">{{ errors.email[0] }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Contraseña</label>
                    <input
                        v-model="password"
                        type="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        class="w-full px-4 py-2.5 rounded-lg bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        placeholder="Mínimo 8 caracteres"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Confirmar contraseña</label>
                    <input
                        v-model="passwordConfirmation"
                        type="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        class="w-full px-4 py-2.5 rounded-lg bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        :class="errors.password ? 'border-red-500' : ''"
                        placeholder="Repetí la contraseña"
                    />
                    <p v-if="errors.password" class="text-xs text-red-400 mt-1">{{ errors.password[0] }}</p>
                </div>

                <div v-if="auth.error" class="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-3 py-2">
                    {{ auth.error }}
                </div>

                <button
                    type="submit"
                    :disabled="auth.loading"
                    class="w-full py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold transition"
                >
                    <span v-if="auth.loading">Creando cuenta…</span>
                    <span v-else>Crear cuenta gratis</span>
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-400">
                ¿Ya tenés cuenta?
                <router-link to="/login" class="text-indigo-300 hover:text-indigo-200 font-semibold">Iniciá sesión</router-link>
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const errors = reactive<Record<string, string[]>>({});

const auth = useAuthStore();
const router = useRouter();

async function onSubmit() {
    Object.keys(errors).forEach((k) => delete errors[k]);
    auth.error = null;
    try {
        const { data } = await axios.post('/auth/register', {
            name: name.value,
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        });
        auth.token = data.token;
        auth.user = data.user;
        localStorage.setItem('auth_token', data.token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`;
        router.push({ name: 'study' });
    } catch (e: any) {
        if (e.response?.status === 422) {
            Object.assign(errors, e.response.data.errors ?? {});
        } else {
            auth.error = e.response?.data?.message || 'No se pudo crear la cuenta';
        }
    }
}
</script>
