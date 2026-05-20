import api from './api';

export const orderService = {
    dashboard:  (params = {})    => api.get('/orders/dashboard', { params }),
    index:      (params = {})    => api.get('/orders',          { params }),
    show:       (id)             => api.get(`/orders/${id}`),
    store:      (data)           => api.post('/orders',         data),
    transition: (id, data)       => api.post(`/orders/${id}/transition`, data),
    fulfil:     (id, data)       => api.post(`/orders/${id}/fulfil`,     data),
    markPaid:   (id, data)       => api.post(`/orders/${id}/payment`,    data),
};

export const courierService = {
    index:    (params = {}) => api.get('/couriers',  { params }),
    store:    (data)        => api.post('/couriers', data),
    update:   (id, data)    => api.put(`/couriers/${id}`, data),
    destroy:  (id)          => api.delete(`/couriers/${id}`),
};

export const shipmentService = {
    index:        (params = {})        => api.get('/shipments',           { params }),
    ship:         (orderId, courierId) => api.post(`/shipments/orders/${orderId}/couriers/${courierId}`),
    refresh:      (id)                 => api.post(`/shipments/${id}/refresh`),
    cancel:       (id, data = {})      => api.post(`/shipments/${id}/cancel`, data),
};

export const notificationService = {
    index:        (params = {}) => api.get('/notifications', { params }),
    store:        (data)        => api.post('/notifications', data),
    markRead:     (id)          => api.post(`/notifications/${id}/read`),
    unreadCount:  ()            => api.get('/notifications/unread-count'),

    templates:        ()              => api.get('/notification-templates'),
    saveTemplate:     (data)          => api.post('/notification-templates', data),
    updateTemplate:   (id, data)      => api.put(`/notification-templates/${id}`, data),
    deleteTemplate:   (id)            => api.delete(`/notification-templates/${id}`),
};

export const automationService = {
    rules:    ()             => api.get('/automation/rules'),
    show:     (id)           => api.get(`/automation/rules/${id}`),
    store:    (data)         => api.post('/automation/rules', data),
    update:   (id, data)     => api.put(`/automation/rules/${id}`, data),
    destroy:  (id)           => api.delete(`/automation/rules/${id}`),
    run:      (id)           => api.post(`/automation/rules/${id}/run`),
    runAll:   ()             => api.post('/automation/run-all'),
    logs:     (params = {})  => api.get('/automation/logs', { params }),
};

export const ecommerceService = {
    connectors:     ()            => api.get('/ecommerce/connectors'),
    storeConnector: (data)        => api.post('/ecommerce/connectors', data),
    updateConnector:(id, data)    => api.put(`/ecommerce/connectors/${id}`, data),
    deleteConnector:(id)          => api.delete(`/ecommerce/connectors/${id}`),
    startSync:      (id, data)    => api.post(`/ecommerce/connectors/${id}/sync`, data),
    jobs:           (params = {}) => api.get('/ecommerce/jobs', { params }),
};
