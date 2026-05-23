import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from '@/router';
import { i18n } from '@/plugins/i18n';
import App from '@/App.vue';
import { registerPwa } from '@/pwa/register';
import { startSyncWorker } from '@/pwa/syncWorker';

const app = createApp(App);

app.use(createPinia());
app.use(router);
app.use(i18n);

app.mount('#app');

registerPwa();
startSyncWorker();
