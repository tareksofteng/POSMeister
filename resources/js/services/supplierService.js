import api from './api';

export const supplierService = {
    index: (params = {}) => api.get('/suppliers', { params }),
    all:   ()            => api.get('/suppliers/all'),
    show:  (id)          => api.get(`/suppliers/${id}`),
    store: (data)        => api.post('/suppliers', data),
    update:(id, data)    => api.put(`/suppliers/${id}`, data),
    toggleStatus: (id)   => api.put(`/suppliers/${id}/status`),
    destroy: (id)        => api.delete(`/suppliers/${id}`),
};
