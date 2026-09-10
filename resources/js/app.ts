import './bootstrap.ts';
import '../css/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';

import App from './App.vue';
import router from './router';
import { useThemeStore } from './stores/theme';

const app = createApp(App);
const pinia = createPinia();
app.use(pinia);
app.use(router);

// Inicializar el tema (sincroniza la clase dark con la preferencia guardada
// y se suscribe a cambios del sistema). Se hace antes del mount para que
// cualquier componente que lea theme.isDark en setup vea el valor correcto.
useThemeStore().init();

app.mount('#app');
