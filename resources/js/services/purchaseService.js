import api from './api';

export const purchaseService = {
    index:   (params = {}) => api.get('/purchases', { params }),
    show:    (id)          => api.get(`/purchases/${id}`),
    store:   (data)        => api.post('/purchases', data),
    update:  (id, data)    => api.put(`/purchases/${id}`, data),
    receive: (id)          => api.put(`/purchases/${id}/receive`),
    destroy: (id)          => api.delete(`/purchases/${id}`),
};
