import api from './api';

export const inventoryIntelligenceService = {
    dashboard:    (params = {}) => api.get('/inventory-intelligence/dashboard',     { params }),
    movement:     (params = {}) => api.get('/inventory-intelligence/movement',      { params }),
    deadStock:    (params = {}) => api.get('/inventory-intelligence/dead-stock',    { params }),
    aging:        (params = {}) => api.get('/inventory-intelligence/aging',         { params }),
    branchHealth: ()            => api.get('/inventory-intelligence/branch-health'),
};

export const procurementService = {
    suggestions:           (params = {}) => api.get('/procurement/suggestions',             { params }),
    suggestionsBySupplier: (params = {}) => api.get('/procurement/suggestions-by-supplier', { params }),
};

export const inventoryReportService = {
    valuation:     (params = {}) => api.get('/inventory-reports/valuation',     { params }),
    profitability: (params = {}) => api.get('/inventory-reports/profitability', { params }),
    movement:      (params = {}) => api.get('/inventory-reports/movement',      { params }),
};

export const supplierAnalyticsService = {
    leaderboard: (params = {}) => api.get('/supplier-analytics/leaderboard', { params }),
    show:        (id, params = {}) => api.get(`/supplier-analytics/${id}`, { params }),
};
