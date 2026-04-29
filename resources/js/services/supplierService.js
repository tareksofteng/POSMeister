import api from './api';

export const supplierService = {
    index: (params = {}) => api.get('/suppliers', { params }),
    all:   ()            => api.get('/suppliers/all'),
    show:  (id)          => api.get(`/suppliers/${id}`),
    store: (data)        => api.post('/suppliers', data),
    update:(id, data)    => api.put(`/suppliers/${id}`, data),
    toggleStatus: (id)   => api.put(`/suppliers/${id}/status`),
    destroy: (id)        => api.delete(`/suppliers/${id}`),
    // due report
    dueReport:     (params = {}) => api.get('/suppliers/due-report', { params }),
    // payment endpoints
    payments:      (params = {}) => api.get('/supplier-payments', { params }),
    createPayment: (data)         => api.post('/supplier-payments', data),
    showPayment:   (id)           => api.get(`/supplier-payments/${id}`),
};


