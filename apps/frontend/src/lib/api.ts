import axios from 'axios';

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';

export const apiClient = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

// Attach JWT token — admin_token takes priority over customer jwt_token
apiClient.interceptors.request.use((config) => {
  if (typeof window !== 'undefined') {
    const token =
      localStorage.getItem('admin_token') ?? localStorage.getItem('jwt_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
  }
  return config;
});

// Redirect to login on 401 — admin path → /admin/login, client path → /login
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && typeof window !== 'undefined') {
      const isAdminPath = window.location.pathname.startsWith('/admin');
      if (isAdminPath) {
        localStorage.removeItem('admin_token');
        document.cookie =
          'admin_token=; path=/admin; expires=Thu, 01 Jan 1970 00:00:00 GMT';
        window.location.href = '/admin/login';
      } else {
        localStorage.removeItem('jwt_token');
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  },
);
