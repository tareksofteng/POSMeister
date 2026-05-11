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
    index: () => api.get('/hrm/departments'),
    all:   () => api.get('/hrm/departments/all'),
};

export const designationService = {
    index: (params = {}) => api.get('/hrm/designations',     { params }),
    all:   (params = {}) => api.get('/hrm/designations/all', { params }),
};

export const shiftService = {
    index: () => api.get('/hrm/shifts'),
    all:   () => api.get('/hrm/shifts/all'),
};

function multipart() {
    return { headers: { 'Content-Type': 'multipart/form-data' } };
}
