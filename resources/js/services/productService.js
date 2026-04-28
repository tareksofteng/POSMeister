import api from './api';

export const productService = {
    index:        (params)    => api.get('/products', { params }),
    show:         (id)        => api.get(`/products/${id}`),
    barcodeData:  (id)        => api.get(`/products/${id}/barcode`),
    search:       (q)         => api.get('/products/search', { params: { q } }),
    store:        (data)      => api.post('/products', data),
    update:       (id, data)  => api.put(`/products/${id}`, data),
    toggleStatus: (id)        => api.put(`/products/${id}/status`),
    destroy:      (id)        => api.delete(`/products/${id}`),

    uploadImage: (id, file) => {
        const fd = new FormData();
        fd.append('image', file);
        return api.post(`/products/${id}/image`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    deleteImage: (id) => api.delete(`/products/${id}/image`),
};
