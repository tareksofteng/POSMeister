import api from './api';

export const categoryService = {
    index:   (params) => api.get('/categories', { params }),
    all:     ()       => api.get('/categories/all'),
    store:   (data)   => api.post('/categories', data),
    update:  (id, data) => api.put(`/categories/${id}`, data),
    destroy: (id)     => api.delete(`/categories/${id}`),
};
