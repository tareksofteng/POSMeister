import api from './api';

export const userService = {
    /** Paginated list — GET /api/users */
    index(params = {}) {
        return api.get('/users', { params });
    },

    /** Single user — GET /api/users/:id */
    show(id) {
        return api.get(`/users/${id}`);
    },

    /** Create — POST /api/users */
    store(data) {
        return api.post('/users', data);
    },

    /** Update — PUT /api/users/:id */
    update(id, data) {
        return api.put(`/users/${id}`, data);
    },

    /** Toggle active status — PUT /api/users/:id/status */
    toggleStatus(id) {
        return api.put(`/users/${id}/status`);
    },

    /** Delete — DELETE /api/users/:id */
    destroy(id) {
        return api.delete(`/users/${id}`);
    },
};
