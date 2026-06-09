import api from './api';

export const notificationService = {
    list:        (params = {})  => api.get('/notifications', { params }),
    digest:      (period = 'morning') => api.get('/notifications/digest', { params: { period } }),
    digestPreview: (period = 'morning') => api.get('/notifications/digest/preview', { params: { period } }),
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

    // ── Phase AB Round 3 — Rule Engine CRUD (admin-only) ────────────────
    rules:       ()             => api.get('/notifications/rules'),
    ruleCodes:   ()             => api.get('/notifications/rules/codes'),
    saveRule:    (data)         => api.post('/notifications/rules', data),
    updateRule:  (id, data)     => api.put(`/notifications/rules/${id}`, data),
    deleteRule:  (id)           => api.delete(`/notifications/rules/${id}`),
};
