import api from './api';

export const systemService = {
    ping:    () => api.get('/system/ping'),
    health:  () => api.get('/system/health'),
    info:    () => api.get('/system/info'),
    audit:   (params = {}) => api.get('/system/audit', { params }),
};
