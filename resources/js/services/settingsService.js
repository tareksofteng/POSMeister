import api from './api';

export const settingsService = {
    get: () => api.get('/settings'),

    update: (data) => api.put('/settings', data),

    uploadLogo: (file) => {
        const fd = new FormData();
        fd.append('logo', file);
        return api.post('/settings/logo', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    deleteLogo: () => api.delete('/settings/logo'),
};
