import api from './api';

export const authService = {
    /**
     * @param {{ email: string, password: string }} credentials
     * @returns {Promise<{ token: string, user: object }>}
     */
    login(credentials) {
        return api.post('/auth/login', credentials);
    },

    /**
     * @returns {Promise<{ user: object }>}
     */
    me() {
        return api.get('/auth/me');
    },

    /**
     * @returns {Promise<{ message: string }>}
     */
    logout() {
        return api.post('/auth/logout');
    },
};
