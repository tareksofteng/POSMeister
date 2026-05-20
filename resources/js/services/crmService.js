import api from './api';

export const customerIntelligenceService = {
    dashboard:   (params = {}) => api.get('/customer-intelligence/dashboard', { params }),
    segments:    (params = {}) => api.get('/customer-intelligence/segments',  { params }),
    segmentList: (name, params = {}) => api.get(`/customer-intelligence/segments/${name}`, { params }),
    profile:     (customerId) => api.get(`/customer-intelligence/customers/${customerId}/profile`),
    behavior:    (customerId) => api.get(`/customer-intelligence/customers/${customerId}/behavior`),
};

export const loyaltyService = {
    settings:     ()               => api.get('/loyalty/settings'),
    saveSettings: (data)           => api.put('/loyalty/settings', data),
    summary:      (customerId)     => api.get(`/loyalty/customers/${customerId}/summary`),
    transactions: (customerId, params = {}) => api.get(`/loyalty/customers/${customerId}/transactions`, { params }),
    adjust:       (customerId, data) => api.post(`/loyalty/customers/${customerId}/adjust`, data),
    redeem:       (customerId, data) => api.post(`/loyalty/customers/${customerId}/redeem`, data),
};

export const walletService = {
    summary:      (customerId)     => api.get(`/customer-wallets/customers/${customerId}/summary`),
    settings:     (customerId, data) => api.put(`/customer-wallets/customers/${customerId}/settings`, data),
    transactions: (customerId, params = {}) => api.get(`/customer-wallets/customers/${customerId}/transactions`, { params }),
    credit:       (customerId, data) => api.post(`/customer-wallets/customers/${customerId}/credit`, data),
    debit:        (customerId, data) => api.post(`/customer-wallets/customers/${customerId}/debit`,  data),
    adjust:       (customerId, data) => api.post(`/customer-wallets/customers/${customerId}/adjust`, data),
    recent:       (params = {}) => api.get('/customer-wallets/recent', { params }),
};

export const campaignService = {
    list:     (params = {}) => api.get('/crm/campaigns', { params }),
    store:    (data)        => api.post('/crm/campaigns', data),
    update:   (id, data)    => api.put(`/crm/campaigns/${id}`, data),
    schedule: (id)          => api.post(`/crm/campaigns/${id}/schedule`),
    queue:    (id)          => api.post(`/crm/campaigns/${id}/queue`),
    cancel:   (id)          => api.post(`/crm/campaigns/${id}/cancel`),
    preview:  (id)          => api.get(`/crm/campaigns/${id}/preview`),
};
