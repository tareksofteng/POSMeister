import api from './api';

/**
 * Topbar branch-workspace switcher API.
 *
 *   current()     — read the branch the server thinks we're in (after the
 *                   X-Branch-Id header is processed by CurrentBranchMiddleware)
 *   available()   — branches the signed-in user can switch to (admin = all,
 *                   manager/cashier = their assigned branch only)
 *   switchTo(id)  — audited switch + ACL check. Pass `null` to enter the
 *                   "All branches" super workspace (admin only).
 */
export const branchContextService = {
    current:    ()        => api.get('/branch-context/current'),
    available:  ()        => api.get('/branch-context/available'),
    switchTo:   (branchId) => api.post('/branch-context/switch', { branch_id: branchId }),
};

export default branchContextService;
