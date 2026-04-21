import api from './api';

export const saleService = {
    index:        (params = {}) => api.get('/sales', { params }),
    show:         (id)          => api.get(`/sales/${id}`),
    store:        (data)        => api.post('/sales', data),
    cancel:       (id)          => api.put(`/sales/${id}/cancel`),
    posSearch:    (q, branchId) => api.get('/pos/products', { params: { q, branch_id: branchId } }),
};
