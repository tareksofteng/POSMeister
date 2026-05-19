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

    approve:  (id, notes)   => api.post(`/expenses/${id}/approve`, { notes }),
    reject:   (id, reason)  => api.post(`/expenses/${id}/reject`,  { reason }),
    markPaid: (id, data)    => api.post(`/expenses/${id}/mark-paid`, data),
    reopen:   (id, notes)   => api.post(`/expenses/${id}/reopen`, { notes }),
    auditLog: (id)          => api.get(`/expenses/${id}/audit-log`),

    exportCsvUrl(params = {}) {
        const qs = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => {
            if (v !== null && v !== undefined && v !== '') qs.append(k, v);
        });
        const base = api.defaults.baseURL || '';
        return `${base}/expenses/export.csv?${qs.toString()}`;
    },
};

export const expenseReportsService = {
    dashboard:         (params = {}) => api.get('/expense-reports/dashboard', { params }),
    categoryBreakdown: (params = {}) => api.get('/expense-reports/category-breakdown', { params }),
    monthlyTrend:      (params = {}) => api.get('/expense-reports/monthly-trend', { params }),
    branchBreakdown:   (params = {}) => api.get('/expense-reports/branch-breakdown', { params }),
};
