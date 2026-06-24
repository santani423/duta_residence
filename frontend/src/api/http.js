import axios from 'axios';

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api/v1',
  headers: {
    Accept: 'application/json',
  },
});

http.interceptors.request.use((config) => {
  const token = localStorage.getItem('gd_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

http.interceptors.response.use(
  (response) => response.data,
  (error) => {
    const payload = error.response?.data;
    const status = error.response?.status;
    const normalized = {
      status,
      message: payload?.message || error.message || 'Koneksi API gagal.',
      errors: payload?.errors,
      data: payload?.data,
    };

    if (status === 401) {
      window.dispatchEvent(new CustomEvent('gd:session-expired'));
    }

    if (status === 403) {
      window.dispatchEvent(new CustomEvent('gd:forbidden'));
    }

    return Promise.reject(normalized);
  },
);
