import api from './api';

export const stockService = {
    filterOptions: ()       => api.get('/stock/filter-options'),
    current:       (params) => api.get('/stock/current', { params }),
};
