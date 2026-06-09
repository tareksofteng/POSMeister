import api from './api';

export const dashboardService = {
    stats:  () => api.get('/dashboard/stats'),

    /**
     * Phase AC Round 2 — Executive trend chart data.
     * metric: 'revenue' | 'profit' | 'purchase' | 'cash_flow'
     * days:   7 | 30 | 90
     */
    trends: (metric = 'revenue', days = 30) =>
        api.get('/dashboard/trends', { params: { metric, days } }),

    /**
     * Phase AC Round 3 — Top Products 2.0.
     * tab: 'best' | 'slow' | 'dead' | 'reorder'
     */
    topProducts: (tab = 'best') =>
        api.get('/dashboard/top-products', { params: { tab } }),

    /**
     * Phase AC Round 3 — Top Customers 2.0.
     * tab: 'vip' | 'recent' | 'outstanding' | 'biggest'
     */
    topCustomers: (tab = 'vip') =>
        api.get('/dashboard/top-customers', { params: { tab } }),
};
