import api from './api';

export const unitService = {
    index:   ()         => api.get('/units'),
    all:     ()         => api.get('/units/all'),
    store:   (data)     => api.post('/units', data),
    update:  (id, data) => api.put(`/units/${id}`, data),
    destroy: (id)       => api.delete(`/units/${id}`),
};
