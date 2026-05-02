import api from './api';

export const quotationService = {
    index:        (params = {}) => api.get('/quotations', { params }),
    show:         (id)          => api.get(`/quotations/${id}`),
    store:        (data)        => api.post('/quotations', data),
    update:       (id, data)    => api.put(`/quotations/${id}`, data),
    updateStatus: (id, status)  => api.put(`/quotations/${id}/status`, { status }),
    destroy:      (id)          => api.delete(`/quotations/${id}`),
};
