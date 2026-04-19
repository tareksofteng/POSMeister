import api from './api';

export const brandService = {
    index:   (params) => api.get('/brands', { params }),
    all:     ()       => api.get('/brands/all'),
    store:   (data)   => api.post('/brands', data),
    update:  (id, data) => api.put(`/brands/${id}`, data),
    destroy: (id)     => api.delete(`/brands/${id}`),
};
