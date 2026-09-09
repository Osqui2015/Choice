<template>
    <AdminLayout>
        <div class="space-y-4">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <h1 class="text-2xl font-bold text-white">📚 Especialidades</h1>
                <button
                    @click="openCreate"
                    class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold"
                >+ Nueva</button>
            </div>

            <div class="bg-slate-800 border border-slate-700 rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900/60 text-xs text-slate-400 uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left">Código</th>
                            <th class="px-3 py-2 text-left">Nombre</th>
                            <th class="px-3 py-2 text-left">Color</th>
                            <th class="px-3 py-2 text-right">Preguntas</th>
                            <th class="px-3 py-2 text-right">Flashcards</th>
                            <th class="px-3 py-2">Activa</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading"><td colspan="7" class="px-3 py-8 text-center text-slate-500">Cargando…</td></tr>
                        <tr v-else-if="!rows.length"><td colspan="7" class="px-3 py-8 text-center text-slate-500">Sin especialidades.</td></tr>
                        <tr v-for="s in rows" v-else :key="s.id" class="border-t border-slate-700/60">
                            <td class="px-3 py-2.5 font-mono text-xs text-slate-300">{{ s.code }}</td>
                            <td class="px-3 py-2.5 font-semibold text-white">{{ s.name }}</td>
                            <td class="px-3 py-2.5">
                                <span v-if="s.color" class="inline-block w-5 h-5 rounded border border-slate-700" :style="{ backgroundColor: s.color }"></span>
                                <span v-else class="text-slate-500 text-xs">—</span>
                            </td>
                            <td class="px-3 py-2.5 text-right text-slate-300">{{ s.questions_count }}</td>
                            <td class="px-3 py-2.5 text-right text-slate-300">{{ s.flashcards_count }}</td>
                            <td class="px-3 py-2.5">
                                <button
                                    @click="toggleActive(s)"
                                    :class="['px-2 py-1 rounded text-xs font-semibold', s.is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300']"
                                >{{ s.is_active ? 'Activa' : 'Inactiva' }}</button>
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                <button @click="openEdit(s)" class="text-indigo-300 hover:text-indigo-200 text-xs font-semibold mr-2">Editar</button>
                                <button @click="remove(s)" class="text-red-300 hover:text-red-200 text-xs font-semibold">Eliminar</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="editing" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4" @click.self="editing = null">
                <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-md w-full p-6">
                    <h2 class="text-xl font-bold text-white mb-4">{{ editing.id ? 'Editar' : 'Nueva' }} Especialidad</h2>
                    <form @submit.prevent="save" class="space-y-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Código (ej: 04_NEUMONOLOGIA)</label>
                            <input v-model="editing.code" type="text" required :disabled="!!editing.id" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white disabled:opacity-50" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Nombre</label>
                            <input v-model="editing.name" type="text" required class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Color (hex)</label>
                            <input v-model="editing.color" type="text" placeholder="#8b5cf6" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Icono</label>
                            <input v-model="editing.icon" type="text" placeholder="kidney" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white" />
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Orden</label>
                            <input v-model.number="editing.sort_order" type="number" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white" />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-200">
                            <input v-model="editing.is_active" type="checkbox" class="w-4 h-4" />
                            Activa (visible para usuarios)
                        </label>
                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="editing = null" class="flex-1 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm">Cancelar</button>
                            <button type="submit" :disabled="saving" class="flex-1 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold disabled:opacity-50">{{ saving ? 'Guardando…' : 'Guardar' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import axios from 'axios';
import AdminLayout from '@/components/AdminLayout.vue';

const rows = ref<any[]>([]);
const loading = ref(false);
const saving = ref(false);
const editing = ref<any>(null);

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get('/admin/specialties');
        rows.value = data.data;
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editing.value = { code: '', name: '', color: '', icon: '', sort_order: 0, is_active: true };
}

function openEdit(s: any) {
    editing.value = { ...s };
}

async function save() {
    if (!editing.value) return;
    saving.value = true;
    try {
        if (editing.value.id) {
            await axios.patch(`/admin/specialties/${editing.value.id}`, editing.value);
        } else {
            await axios.post('/admin/specialties', editing.value);
        }
        editing.value = null;
        await load();
    } catch (e: any) {
        alert(e.response?.data?.message || 'Error');
    } finally {
        saving.value = false;
    }
}

async function toggleActive(s: any) {
    await axios.patch(`/admin/specialties/${s.id}`, { is_active: !s.is_active });
    s.is_active = !s.is_active;
}

async function remove(s: any) {
    if (!confirm(`¿Eliminar la especialidad "${s.name}"? Solo se puede si no tiene preguntas.`)) return;
    try {
        await axios.delete(`/admin/specialties/${s.id}`);
        await load();
    } catch (e: any) {
        alert(e.response?.data?.message || 'Error');
    }
}

onMounted(load);
</script>
