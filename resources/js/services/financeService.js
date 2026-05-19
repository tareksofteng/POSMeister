import api from './api';

export const budgetService = {
    index:     (params = {}) => api.get('/budgets', { params }),
    show:      (id)          => api.get(`/budgets/${id}`),
    store:     (data)        => api.post('/budgets', data),
    update:    (id, data)    => api.put(`/budgets/${id}`, data),
    setStatus: (id, status)  => api.put(`/budgets/${id}/status`, { status }),
    duplicate: (id, year)    => api.post(`/budgets/${id}/duplicate`, { fiscal_year: year }),
    destroy:   (id)          => api.delete(`/budgets/${id}`),
    analytics: (id)          => api.get(`/budgets/${id}/analytics`),
};

export const cashflowService = {
    dashboard: (params = {}) => api.get('/cashflow/dashboard', { params }),
    forecast:  (params = {}) => api.get('/cashflow/forecast',  { params }),
};

export const financeAlertService = {
    list: (params = {}) => api.get('/finance/alerts', { params }),
};

export const financialCalendarService = {
    month: (params) => api.get('/finance/calendar', { params }),
};
