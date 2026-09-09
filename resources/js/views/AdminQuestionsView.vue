<template>
    <AdminLayout>
        <div class="space-y-4">
            <h1 class="text-2xl font-bold text-white">❓ Preguntas</h1>

            <div class="grid sm:grid-cols-3 gap-2">
                <input
                    v-model="filters.search"
                    @input="debouncedLoad"
                    type="text"
                    placeholder="Buscar en el enunciado…"
                    class="px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-sm text-white placeholder-slate-500"
                />
                <select v-model="filters.specialty_id" @change="load(1)" class="px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-sm text-white">
                    <option value="">Todas las especialidades</option>
                    <option v-for="s in specialties" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <select v-model="filters.is_active" @change="load(1)" class="px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-sm text-white">
                    <option value="">Todas</option>
                    <option value="true">Solo activas</option>
                    <option value="false">Solo inactivas</option>
                </select>
            </div>

            <div class="bg-slate-800 border border-slate-700 rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900/60 text-xs text-slate-400 uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left">#</th>
                            <th class="px-3 py-2 text-left">Enunciado</th>
                            <th class="px-3 py-2 text-left">Especialidad</th>
                            <th class="px-3 py-2 text-left">Resp.</th>
                            <th class="px-3 py-2 text-left">Activa</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading"><td colspan="6" class="px-3 py-8 text-center text-slate-500">Cargando…</td></tr>
                        <tr v-else-if="!rows.length"><td colspan="6" class="px-3 py-8 text-center text-slate-500">Sin resultados.</td></tr>
                        <tr v-for="q in rows" v-else :key="q.id" class="border-t border-slate-700/60">
                            <td class="px-3 py-2 text-slate-500 text-xs">#{{ q.code_number }}</td>
                            <td class="px-3 py-2 text-slate-200 line-clamp-2 max-w-md">{{ q.statement }}</td>
                            <td class="px-3 py-2 text-xs text-slate-400">{{ q.specialty?.name ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-0.5 rounded font-mono text-xs font-bold bg-slate-700 text-white">{{ q.correct_answer?.toUpperCase() }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <button
                                    @click="toggleActive(q)"
                                    :class="['px-2 py-1 rounded text-xs font-semibold', q.is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300']"
                                >{{ q.is_active ? 'Activa' : 'Inactiva' }}</button>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button @click="openEdit(q)" class="text-indigo-300 hover:text-indigo-200 text-xs font-semibold">Ver / Editar</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="meta.last_page > 1" class="flex items-center justify-center gap-2 text-sm flex-wrap">
                <button
                    v-for="p in Math.min(meta.last_page, 10)"
                    :key="p"
                    @click="load(p)"
                    :class="['px-3 py-1 rounded', p === meta.current_page ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-300']"
                >{{ p }}</button>
                <span v-if="meta.last_page > 10" class="text-slate-500 text-xs">… {{ meta.last_page }} páginas</span>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="editing" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4" @click.self="editing = null">
                <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
                    <h2 class="text-xl font-bold text-white mb-4">Pregunta #{{ editing.code_number }}</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Enunciado</label>
                            <textarea v-model="editing.statement" rows="3" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-2">Opciones (tildá la correcta)</label>
                            <div v-for="opt in editing.options" :key="opt.key" class="flex items-start gap-2 mb-2">
                                <input
                                    type="radio"
                                    :name="`correct-${editing.id}`"
                                    :checked="editing.correct_answer === opt.key"
                                    @change="editing.correct_answer = opt.key"
                                    class="mt-1.5 w-4 h-4"
                                />
                                <div class="flex-1">
                                    <div class="flex items-center gap-1 mb-1">
                                        <span class="text-xs font-bold text-slate-400 w-4">{{ opt.key.toUpperCase() }}</span>
                                    </div>
                                    <textarea
                                        v-model="opt.text"
                                        rows="2"
                                        class="w-full px-2 py-1 rounded bg-slate-900 border border-slate-700 text-white text-sm"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-200">
                            <input v-model="editing.is_active" type="checkbox" class="w-4 h-4" />
                            Activa (visible para los usuarios)
                        </label>
                    </div>
                    <div class="flex gap-2 pt-4">
                        <button @click="editing = null" class="flex-1 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm">Cancelar</button>
                        <button @click="save" :disabled="saving" class="flex-1 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold disabled:opacity-50">{{ saving ? 'Guardando…' : 'Guardar' }}</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import AdminLayout from '@/components/AdminLayout.vue';

const rows = ref<any[]>([]);
const specialties = ref<any[]>([]);
const loading = ref(false);
const saving = ref(false);
const meta = reactive({ current_page: 1, last_page: 1, per_page: 20, total: 0 });
const filters = reactive({ search: '', specialty_id: '', is_active: '' });
const editing = ref<any>(null);

let timer: number | null = null;
function debouncedLoad() {
    if (timer) clearTimeout(timer);
    timer = window.setTimeout(() => load(1), 300);
}

async function load(page = 1) {
    loading.value = true;
    try {
        const params: Record<string, any> = { per_page: 20, page };
        if (filters.search) params.search = filters.search;
        if (filters.specialty_id) params.specialty_id = filters.specialty_id;
        if (filters.is_active !== '') params.is_active = filters.is_active;
        const { data } = await axios.get('/admin/questions', { params });
        rows.value = data.data;
        Object.assign(meta, data.meta);
    } finally {
        loading.value = false;
    }
}

async function openEdit(q: any) {
    const { data } = await axios.get(`/admin/questions/${q.id}`);
    editing.value = JSON.parse(JSON.stringify(data.question));
}

async function save() {
    if (!editing.value) return;
    saving.value = true;
    try {
        await axios.patch(`/admin/questions/${editing.value.id}`, {
            statement: editing.value.statement,
            options: editing.value.options,
            correct_answer: editing.value.correct_answer,
            is_active: editing.value.is_active,
        });
        editing.value = null;
        await load(meta.current_page);
    } catch (e: any) {
        alert(e.response?.data?.message || 'Error');
    } finally {
        saving.value = false;
    }
}

async function toggleActive(q: any) {
    await axios.patch(`/admin/questions/${q.id}`, { is_active: !q.is_active });
    q.is_active = !q.is_active;
}

onMounted(async () => {
    const { data: s } = await axios.get('/admin/specialties');
    specialties.value = s.data;
    await load(1);
});
</script>
