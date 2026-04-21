import api from './api';

export const customerService = {
    index:  (params = {}) => api.get('/customers', { params }),
    all:    ()             => api.get('/customers/all'),
    store:  (data)         => api.post('/customers', data),
    update: (id, data)     => api.put(`/customers/${id}`, data),
};
