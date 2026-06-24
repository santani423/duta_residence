import { http } from '../api/http.js';

export const api = {
  auth: {
    login: (payload) => http.post('/auth/login', payload),
    logout: () => http.post('/auth/logout'),
    me: () => http.get('/auth/me'),
    changePassword: (payload) => http.post('/auth/change-password', payload),
  },
  dashboard: {
    summary: () => http.get('/reports/dashboard'),
    monthly: (params) => http.get('/reports/monthly', { params }),
    aging: () => http.get('/receivables/aging'),
  },
  clusters: {
    list: (params) => http.get('/clusters', { params }),
    detail: (id) => http.get(`/clusters/${id}`),
    update: (id, payload) => http.put(`/clusters/${id}`, payload),
  },
  customers: {
    list: (params) => http.get('/customers', { params }),
    detail: (id) => http.get(`/customers/${id}`),
    create: (payload) => http.post('/customers', payload),
    update: (id, payload) => http.put(`/customers/${id}`, payload),
    remove: (id) => http.delete(`/customers/${id}`),
    convert: (id, payload) => http.post(`/customers/${id}/convert-property`, payload),
    installments: (id) => http.get(`/customers/${id}/installments`),
  },
  billings: {
    list: (params) => http.get('/billings', { params }),
    pendingApproval: (params) => http.get('/billings/pending-approval', { params }),
    prepareMonthly: (payload) => http.post('/billings/prepare-monthly', payload),
    prepareSpecial: (payload) => http.post('/billings/prepare-special', payload),
    prepareBack: (payload) => http.post('/billings/prepare-back', payload),
    approve: (id, payload) => http.post(`/billings/${id}/approve`, payload),
    approveBatch: (payload) => http.post('/billings/approve-batch', payload),
  },
  payments: {
    search: (params) => http.get('/payments/search', { params }),
    preview: (payload) => http.post('/payments/preview', payload),
    process: (payload) => http.post('/payments/process', payload),
    receipts: (params) => http.get('/payments/receipts', { params }),
    receipt: (number) => http.get(`/payments/receipts/${number}`),
    gatewayConfig: () => http.get('/payments/gateway/config'),
    gatewayTransactions: (params) => http.get('/payments/gateway/transactions', { params }),
    gatewayTransaction: (id) => http.get(`/payments/gateway/transactions/${id}`),
    createGateway: (payload) => http.post('/payments/gateway', payload),
    uploadManualProof: (id, payload) => http.post(`/payments/${id}/manual-proof`, payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
    verifyManual: (id, payload) => http.post(`/payments/${id}/verify`, payload),
    rejectManual: (id, payload) => http.post(`/payments/${id}/reject`, payload),
  },
  installments: {
    list: (params) => http.get('/installments', { params }),
    create: (payload) => http.post('/installments', payload),
  },
  reversals: {
    list: (params) => http.get('/reversals', { params }),
    create: (payload) => http.post('/reversals', payload),
    approve: (id, payload) => http.post(`/reversals/${id}/approve`, payload),
    reject: (id, payload) => http.post(`/reversals/${id}/reject`, payload),
  },
  receivables: {
    list: (params) => http.get('/receivables', { params }),
    aging: () => http.get('/receivables/aging'),
  },
  reports: {
    monthly: (params) => http.get('/reports/monthly', { params }),
    dailyReceipt: (params) => http.get('/reports/daily-receipt', { params }),
    reconciliation: (params) => http.get('/reports/reconciliation', { params }),
    collector: (params) => http.get('/reports/collector', { params }),
  },
  documents: {
    url: (path) => `${http.defaults.baseURL}${path}`,
  },
  users: {
    list: (params) => http.get('/users', { params }),
    detail: (id) => http.get(`/users/${id}`),
    create: (payload) => http.post('/users', payload),
    update: (id, payload) => http.put(`/users/${id}`, payload),
    remove: (id) => http.delete(`/users/${id}`),
    resetPassword: (id) => http.post(`/users/${id}/reset-password`),
    toggleStatus: (id) => http.post(`/users/${id}/toggle-status`),
    activities: (id, params) => http.get(`/users/${id}/activities`, { params }),
  },
  auditLogs: {
    list: (params) => http.get('/audit-logs', { params }),
  },
  notifications: {
    list: (params) => http.get('/notifications', { params }),
    read: (id) => http.post(`/notifications/${id}/read`),
    readAll: () => http.post('/notifications/read-all'),
  },
  lookup: {
    regencies: () => http.get('/lookup/regencies'),
    districts: (params) => http.get('/lookup/districts', { params }),
  },
};
