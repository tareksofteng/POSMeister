import api from './api';

export const chartOfAccountsService = {
    index:    (params = {}) => api.get('/accounting/coa',         { params }),
    show:     (id)          => api.get(`/accounting/coa/${id}`),
    store:    (data)        => api.post('/accounting/coa',        data),
    update:   (id, data)    => api.put(`/accounting/coa/${id}`,   data),
    destroy:  (id)          => api.delete(`/accounting/coa/${id}`),
};

export const journalEntryService = {
    index:    (params = {}) => api.get('/accounting/journal',     { params }),
    show:     (id)          => api.get(`/accounting/journal/${id}`),
    store:    (data)        => api.post('/accounting/journal',    data),
    reverse:  (id, data)    => api.post(`/accounting/journal/${id}/reverse`, data),
    destroy:  (id)          => api.delete(`/accounting/journal/${id}`),
};

export const accountingReportService = {
    dashboard:    (params = {}) => api.get('/accounting/dashboard',     { params }),
    ledger:       (id, params)  => api.get(`/accounting/ledger/${id}`,  { params }),
    trialBalance: (params)      => api.get('/accounting/trial-balance', { params }),
    profitLoss:   (params)      => api.get('/accounting/profit-loss',   { params }),
    balanceSheet: (params)      => api.get('/accounting/balance-sheet', { params }),
    cashbook:     (id, params)  => api.get(`/accounting/cashbook/${id}`, { params }),
};

export const bankAccountService = {
    index:   (params = {}) => api.get('/accounting/banks',  { params }),
    store:   (data)        => api.post('/accounting/banks', data),
    update:  (id, data)    => api.put(`/accounting/banks/${id}`, data),
    destroy: (id)          => api.delete(`/accounting/banks/${id}`),
};

export const cashbookService = {
    index:   (params = {}) => api.get('/accounting/cashbooks',  { params }),
    store:   (data)        => api.post('/accounting/cashbooks', data),
    update:  (id, data)    => api.put(`/accounting/cashbooks/${id}`, data),
    destroy: (id)          => api.delete(`/accounting/cashbooks/${id}`),
};
