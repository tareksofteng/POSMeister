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
};
