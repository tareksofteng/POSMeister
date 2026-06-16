import api from './api';

/*
 * Phase AE — Business Insights API surface. The timeline is persistent;
 * the forecast endpoints are read-through cached (30 min) on the
 * backend so calling these from a widget is cheap.
 */
export const insightsService = {
    /** bucket: 'today' | 'yesterday' | 'week' | 'month' */
    timeline:    (bucket = 'today') => api.get('/insights/timeline', { params: { bucket } }),

    /**
     * metric:  'revenue' | 'profit' | 'cash_flow'
     * horizon: 7 | 30 | 90
     */
    forecast:        (metric = 'revenue', horizon = 7) =>
                         api.get('/insights/forecast', { params: { metric, horizon } }),

    /** All three metrics @ default 7-day horizon — feeds the dashboard widget. */
    forecastSummary: () => api.get('/insights/forecast-summary'),

    /** status: 'active' | 'resolved' | 'ignored' | 'pinned' */
    markStatus:  (id, status) => api.post(`/insights/${id}/status`, { status }),

    /** Admin manual trigger — normally fires every 10 min via cron. */
    captureNow:  () => api.post('/insights/capture'),

    // ── Phase AE R2 ─────────────────────────────────────────────────────

    /** RFM segment summary (counts + totals + top-3-per-segment). */
    customerSegments: () => api.get('/insights/customer-segments'),

    /** Full list of customers in a single segment. */
    customersInSegment: (segment) =>
        api.get('/insights/customer-segments', { params: { segment } }),

    /** Turnover ratio, aging, stockout forecast, velocity leaders. */
    inventoryIntelligence: () => api.get('/insights/inventory'),

    // ── Phase AE R3 ─────────────────────────────────────────────────────

    /** Top suppliers, concentration risk, lead times, inactivity, payment perf. */
    supplierIntelligence: () => api.get('/insights/suppliers'),

    /** Frequently bought together + category growth + margin mix. */
    productOpportunities: () => api.get('/insights/opportunities'),
};
