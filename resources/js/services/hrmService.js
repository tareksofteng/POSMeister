import api from './api';

export const employeeService = {
    index:        (params = {})    => api.get('/hrm/employees', { params }),
    stats:        ()               => api.get('/hrm/employees/stats'),
    show:         (id)             => api.get(`/hrm/employees/${id}`),
    store:        (formData)       => api.post('/hrm/employees', formData, multipart()),
    update:       (id, formData)   => api.post(`/hrm/employees/${id}?_method=PUT`, formData, multipart()),
    setStatus:    (id, status)     => api.put(`/hrm/employees/${id}/status`, { status }),
    destroy:      (id)             => api.delete(`/hrm/employees/${id}`),
    uploadPhoto:  (id, file)       => {
        const fd = new FormData();
        fd.append('photo', file);
        return api.post(`/hrm/employees/${id}/photo`, fd, multipart());
    },
    deletePhoto:  (id)             => api.delete(`/hrm/employees/${id}/photo`),
};

export const departmentService = {
    index:        ()         => api.get('/hrm/departments'),
    all:          ()         => api.get('/hrm/departments/all'),
    store:        (data)     => api.post('/hrm/departments', data),
    update:       (id, data) => api.put(`/hrm/departments/${id}`, data),
    toggleStatus: (id)       => api.put(`/hrm/departments/${id}/status`),
    destroy:      (id)       => api.delete(`/hrm/departments/${id}`),
};

export const designationService = {
    index:        (params = {}) => api.get('/hrm/designations',     { params }),
    all:          (params = {}) => api.get('/hrm/designations/all', { params }),
    store:        (data)        => api.post('/hrm/designations', data),
    update:       (id, data)    => api.put(`/hrm/designations/${id}`, data),
    toggleStatus: (id)          => api.put(`/hrm/designations/${id}/status`),
    destroy:      (id)          => api.delete(`/hrm/designations/${id}`),
};

export const payrollPeriodService = {
    index:    (params = {}) => api.get('/hrm/payroll-periods', { params }),
    show:     (id)          => api.get(`/hrm/payroll-periods/${id}`),
    store:    (data)        => api.post('/hrm/payroll-periods', data),
    update:   (id, data)    => api.put(`/hrm/payroll-periods/${id}`, data),
    destroy:  (id)          => api.delete(`/hrm/payroll-periods/${id}`),
    generate: (id)          => api.post(`/hrm/payroll-periods/${id}/generate`),
    finalize: (id)          => api.post(`/hrm/payroll-periods/${id}/finalize`),
};

export const payslipService = {
    index:      (params = {}) => api.get('/hrm/payslips', { params }),
    show:       (id)          => api.get(`/hrm/payslips/${id}`),
    update:     (id, data)    => api.put(`/hrm/payslips/${id}`, data),
    destroy:    (id)          => api.delete(`/hrm/payslips/${id}`),
    addItem:    (id, item)    => api.post(`/hrm/payslips/${id}/items`, item),
    removeItem: (id, itemId)  => api.delete(`/hrm/payslips/${id}/items/${itemId}`),
    pay:        (id, data)    => api.post(`/hrm/payslips/${id}/pay`, data),
};

export const attendanceService = {
    daily:    (params)        => api.get('/hrm/attendance/daily',   { params }),
    monthly:  (params)        => api.get('/hrm/attendance/monthly', { params }),
    bulkMark: (date, rows)    => api.post('/hrm/attendance/bulk', { date, rows }),
    destroy:  (id)            => api.delete(`/hrm/attendance/${id}`),
};

export const shiftService = {
    index:        ()         => api.get('/hrm/shifts'),
    all:          ()         => api.get('/hrm/shifts/all'),
    store:        (data)     => api.post('/hrm/shifts', data),
    update:       (id, data) => api.put(`/hrm/shifts/${id}`, data),
    toggleStatus: (id)       => api.put(`/hrm/shifts/${id}/status`),
    destroy:      (id)       => api.delete(`/hrm/shifts/${id}`),
};

function multipart() {
    return { headers: { 'Content-Type': 'multipart/form-data' } };
}
