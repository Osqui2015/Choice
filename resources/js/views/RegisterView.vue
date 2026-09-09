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
                        :class="errors.name ? 'border-red-500' : ''"
                        placeholder="Juan Pérez"
                    />
                    <p v-if="errors.name" class="text-xs text-red-400 mt-1">{{ errors.name[0] }}</p>
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
                    <label class="block text-sm font-medium text-slate-300 mb-1">
                        WhatsApp <span class="text-slate-500 font-normal">(con código de país)</span>
                    </label>
                    <input
                        v-model="phone"
                        type="tel"
                        required
                        autocomplete="tel"
                        inputmode="tel"
                        class="w-full px-4 py-2.5 rounded-lg bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        :class="errors.phone ? 'border-red-500' : ''"
                        placeholder="+54 9 11 1234-5678"
                    />
                    <p v-if="errors.phone" class="text-xs text-red-400 mt-1">{{ errors.phone[0] }}</p>
                    <p v-else class="text-xs text-slate-500 mt-1">
                        Lo usamos para coordinar el pago y avisarte cuando se active tu plan.
                    </p>
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
                        :class="errors.password ? 'border-red-500' : ''"
                        placeholder="Mínimo 8 caracteres"
                    />
                    <p v-if="errors.password" class="text-xs text-red-400 mt-1">{{ errors.password[0] }}</p>
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
                </div>

                <label class="flex items-start gap-3 cursor-pointer group">
                    <input
                        v-model="acceptsPromotions"
                        type="checkbox"
                        class="mt-1 rounded text-indigo-500 focus:ring-indigo-500 focus:ring-offset-0 bg-slate-700 border-slate-600"
                    />
                    <span class="text-sm text-slate-300 group-hover:text-slate-200">
                        Quiero recibir novedades y promociones por WhatsApp.
                        <span class="text-slate-500">(Opcional, podés cambiarlo cuando quieras)</span>
                    </span>
                </label>

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
const phone = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const acceptsPromotions = ref(true);
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
            phone: phone.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
            accepts_promotions: acceptsPromotions.value,
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
