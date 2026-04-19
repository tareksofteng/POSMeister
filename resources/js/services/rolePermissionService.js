import api from './api';

export const rolePermissionService = {
    /** GET /api/role-permissions — returns { data: { manager: [...], cashier: [...] }, modules: [...] } */
    get() {
        return api.get('/role-permissions');
    },

    /** PUT /api/role-permissions/:role — sync module list for a role */
    updateRole(role, modules) {
        return api.put(`/role-permissions/${role}`, { modules });
    },
};
