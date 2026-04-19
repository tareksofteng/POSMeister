import api from './api';

export const branchService = {
    /** Paginated list — GET /api/branches */
    index(params = {}) {
        return api.get('/branches', { params });
    },

    /** All active branches for dropdowns — GET /api/branches/all */
    all() {
        return api.get('/branches/all');
    },

    /** Single branch — GET /api/branches/:id */
    show(id) {
        return api.get(`/branches/${id}`);
    },

    /** Create — POST /api/branches */
    store(data) {
        return api.post('/branches', data);
    },

    /** Update — PUT /api/branches/:id */
    update(id, data) {
        return api.put(`/branches/${id}`, data);
    },

    /** Delete — DELETE /api/branches/:id */
    destroy(id) {
        return api.delete(`/branches/${id}`);
    },
};
