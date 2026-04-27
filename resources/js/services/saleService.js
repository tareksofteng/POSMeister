import api from './api';

export const saleService = {
    index:        (params = {}) => api.get('/sales', { params }),
    record:       (params = {}) => api.get('/sales/record', { params }),
    show:         (id)          => api.get(`/sales/${id}`),
    store:        (data)        => api.post('/sales', data),
    cancel:       (id)          => api.put(`/sales/${id}/cancel`),
    posSearch:    (q, branchId) => api.get('/pos/products', { params: { q, branch_id: branchId } }),

    // Sale Returns
    returnDetails: (saleId)      => api.get(`/sales/${saleId}/return-details`),
    storeReturn:   (data)        => api.post('/sale-returns', data),
    indexReturns:  (params = {}) => api.get('/sale-returns', { params }),
    returnShow:    (id)          => api.get(`/sale-returns/${id}`),
    returnRecord:  (params = {}) => api.get('/sale-returns/record', { params }),
};
