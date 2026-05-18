import api from './api';

function multipart() {
    return { headers: { 'Content-Type': 'multipart/form-data' } };
}

export const expenseCategoryService = {
    index:        ()         => api.get('/expense-categories'),
    all:          ()         => api.get('/expense-categories/all'),
    store:        (data)     => api.post('/expense-categories', data),
    update:       (id, data) => api.put(`/expense-categories/${id}`, data),
    toggleStatus: (id)       => api.put(`/expense-categories/${id}/status`),
    destroy:      (id)       => api.delete(`/expense-categories/${id}`),
};

export const expenseService = {
    index:    (params = {}) => api.get('/expenses', { params }),
    summary:  (params = {}) => api.get('/expenses/summary', { params }),
    show:     (id)          => api.get(`/expenses/${id}`),
    store:    (formData)    => api.post('/expenses', formData, multipart()),
    update:   (id, fd)      => api.post(`/expenses/${id}?_method=PUT`, fd, multipart()),
    destroy:  (id)          => api.delete(`/expenses/${id}`),
};
