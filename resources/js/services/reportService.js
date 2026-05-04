import api from './api';

export const reportService = {
    customerLedger: (params) => api.get('/reports/customer-ledger', { params }),
    supplierLedger: (params) => api.get('/reports/supplier-ledger', { params }),
    productLedger:  (params) => api.get('/reports/product-ledger',  { params }),
};
