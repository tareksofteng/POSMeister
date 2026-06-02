import api from './api';

export const notificationService = {
    list:        (params = {})  => api.get('/notifications', { params }),
    digest:      ()             => api.get('/notifications/digest'),
    prefs:       ()             => api.get('/notifications/preferences'),
    savePrefs:   (data)         => api.put('/notifications/preferences', data),
    markRead:    (id)           => api.post(`/notifications/${id}/read`),
    ack:         (id)           => api.post(`/notifications/${id}/ack`),
    archive:     (id)           => api.post(`/notifications/${id}/archive`),
    markAllRead: ()             => api.post('/notifications/mark-all-read'),
    clearRead:   ()             => api.post('/notifications/clear-read'),
    clearAll:    ()             => api.post('/notifications/clear-all'),
    analytics:   ()             => api.get('/notifications/analytics'),
    detectNow:   ()             => api.post('/notifications/detect'),
};
