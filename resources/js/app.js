import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from '@/router';
import { i18n } from '@/plugins/i18n';
import App from '@/App.vue';
import { registerPwa } from '@/pwa/register';
import { startSyncWorker } from '@/pwa/syncWorker';
import { startSyncEngine } from '@/offline/syncEngine';
import { startSnapshotLoop } from '@/offline/snapshotPreloader';

const app = createApp(App);

app.use(createPinia());
app.use(router);
app.use(i18n);

app.mount('#app');

registerPwa();
startSyncWorker();      // legacy IndexedDB queue (kept for older offline data)
startSyncEngine();      // Phase Ω — batches offline_sales to /api/system/sync/sales
startSnapshotLoop();    // Phase Ω — refreshes products/customers cache every 15min
