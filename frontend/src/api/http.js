import axios from 'axios';

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL;

if (!apiBaseUrl) {
  throw new Error('VITE_API_BASE_URL belum diset. Isi frontend/.env sebelum menjalankan frontend.');
}

export const http = axios.create({
  baseURL: apiBaseUrl,
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
  async (error) => {
    let payload = error.response?.data;
    // Blob requests (PDF/CSV) receive their JSON error body as a Blob too; without parsing
    // it the real reason (e.g. "terlalu banyak data") is lost behind axios' generic message.
    if (payload instanceof Blob && payload.type.includes('json')) {
      try {
        payload = JSON.parse(await payload.text());
      } catch {
        payload = undefined;
      }
    }
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
