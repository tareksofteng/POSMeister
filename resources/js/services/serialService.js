import api from './api';

/**
 * Phase Y — frontend client for the Serials / IMEI / Warranty backend.
 *
 * Mirrors the Round 1 SerialController routes 1:1. All methods return
 * the raw axios promise so callers can `.then(({ data }) => …)` or
 * destructure in async/await — same pattern as the other services in
 * this folder.
 */
export const serialService = {
    /** GET /api/products/{product}/serials — paginated list for the Serial Inventory modal. */
    listForProduct: (productId, params = {}) =>
        api.get(`/products/${productId}/serials`, { params }),

    /** GET /api/products/{product}/serials/available — what the cashier can sell right now. */
    availableForSale: (productId, branchId = null) =>
        api.get(`/products/${productId}/serials/available`, {
            params: branchId ? { branch_id: branchId } : {},
        }),

    /** GET /api/products/{product}/serials/in-stock-count — single integer for badges + dashboards. */
    inStockCount: (productId, branchId = null) =>
        api.get(`/products/${productId}/serials/in-stock-count`, {
            params: branchId ? { branch_id: branchId } : {},
        }),

    /** GET /api/serials/{serial} — full record + movement timeline. */
    show: (serialId) => api.get(`/serials/${serialId}`),

    /** GET /api/serials/warranty-expiring?days=30 — feeds the warranty widget + notifier. */
    warrantyExpiring: (days = 30, branchId = null) =>
        api.get('/serials/warranty-expiring', {
            params: { days, ...(branchId ? { branch_id: branchId } : {}) },
        }),

    /** POST /api/serials/attach-purchase — bulk attach freshly received serials. */
    attachToPurchase: (payload) => api.post('/serials/attach-purchase', payload),

    /** POST /api/serials/attach-sale — move selected serials to "sold". */
    attachToSale: (payload) => api.post('/serials/attach-sale', payload),

    /** GET /api/customers/{customer}/owned-devices — the Customer "Owned Devices" tab. */
    ownedByCustomer: (customerId) => api.get(`/customers/${customerId}/owned-devices`),
};

export default serialService;
