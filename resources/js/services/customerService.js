import api from './api';

export const customerService = {
    index:         (params = {}) => api.get('/customers', { params }),
    all:           ()             => api.get('/customers/all'),
    show:          (id)           => api.get(`/customers/${id}`),
    store:         (data)         => api.post('/customers', data),
    update:        (id, data)     => api.put(`/customers/${id}`, data),
    getPayments:   (id)           => api.get(`/customers/${id}/payments`),
    storePayment:  (id, data)     => api.post(`/customers/${id}/payments`, data),
    // standalone payment endpoints
    payments:      (params = {}) => api.get('/customer-payments', { params }),
    createPayment: (data)         => api.post('/customer-payments', data),
    showPayment:   (id)           => api.get(`/customer-payments/${id}`),
};
