import axios from 'axios';

declare module 'axios' {
  // Opt a request out of the global 401 → /login redirect. The caller then
  // handles the 401 itself (e.g. a public page making an optional auth call).
  export interface AxiosRequestConfig {
    skipAuthRedirect?: boolean;
  }
}

// Server-side (SSR) uses API_URL (internal Docker network); client uses NEXT_PUBLIC_API_URL.
const API_URL =
  typeof window === 'undefined'
    ? (process.env.API_URL ?? process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000')
    : (process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000');

export const apiClient = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

// Attach JWT token — path-aware: admin and merchant spaces have separate tokens.
apiClient.interceptors.request.use((config) => {
  if (typeof window !== 'undefined') {
    const isAdminPath = window.location.pathname.startsWith('/admin');
    const isMerchantPath = window.location.pathname.startsWith('/merchant');
    const token = isAdminPath
      ? localStorage.getItem('admin_token')
      : isMerchantPath
        ? localStorage.getItem('merchant_token')
        : localStorage.getItem('jwt_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
  }
  return config;
});

// Redirect to login on 401 — admin path → /admin/login, merchant path → /merchant/login.
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (
      error.response?.status === 401 &&
      typeof window !== 'undefined' &&
      !error.config?.skipAuthRedirect
    ) {
      const isAdminPath = window.location.pathname.startsWith('/admin');
      const isMerchantPath = window.location.pathname.startsWith('/merchant');
      const isLoginPath =
        window.location.pathname === '/admin/login' ||
        window.location.pathname === '/merchant/login' ||
        window.location.pathname === '/login';

      if (isLoginPath) {
        return Promise.reject(error);
      }

      // Preserve the current path so the user returns here after logging in.
      const returnTo = encodeURIComponent(
        window.location.pathname + window.location.search,
      );

      if (isAdminPath) {
        localStorage.removeItem('admin_token');
        document.cookie =
          'admin_token=; path=/admin; expires=Thu, 01 Jan 1970 00:00:00 GMT';
        window.location.href = `/admin/login?redirect=${returnTo}`;
      } else if (isMerchantPath) {
        localStorage.removeItem('merchant_token');
        document.cookie =
          'merchant_token=; path=/merchant; expires=Thu, 01 Jan 1970 00:00:00 GMT';
        window.location.href = `/merchant/login?redirect=${returnTo}`;
      } else {
        localStorage.removeItem('jwt_token');
        window.location.href = `/login?redirect=${returnTo}`;
      }
    }
    return Promise.reject(error);
  },
);
