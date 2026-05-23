import api from './api';

export const systemOpsService = {
    dashboard:        ()  => api.get('/system/dashboard'),
    environment:      ()  => api.get('/system/environment-check'),
    queue:            ()  => api.get('/system/queue-status'),
    scheduler:        ()  => api.get('/system/scheduler-status'),
    deployment:       ()  => api.get('/system/deployment'),
    version:          ()  => api.get('/system/version'),
    pwaStatus:        ()  => api.get('/system/pwa/status'),

    backupStatus:     ()  => api.get('/system/backup/status'),
    backupRun:        (note) => api.post('/system/backup/run', { note }),
    backupPrune:      ()  => api.post('/system/backup/prune'),

    syncPending:      ()  => api.get('/system/sync/pending'),
    syncPrune:        ()  => api.post('/system/sync/prune'),
};
