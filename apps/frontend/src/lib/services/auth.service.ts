import { apiClient } from '@/lib/api';

export interface AdminUser {
  token: string;
  email: string;
  name: string;
}

export function decodeJwtPayload(token: string): Record<string, unknown> {
  const parts = token.split('.');
  if (parts.length !== 3) throw new Error('Invalid JWT format');
  const base64 = parts[1].replace(/-/g, '+').replace(/_/g, '/');
  const json = atob(base64);
  return JSON.parse(json) as Record<string, unknown>;
}

export async function adminLogin(email: string, password: string): Promise<AdminUser> {
  const { data } = await apiClient.post<{ token: string }>('/api/auth/login', {
    email,
    password,
  });
  const payload = decodeJwtPayload(data.token);
  const roles = Array.isArray(payload.roles) ? (payload.roles as string[]) : [];
  if (!roles.includes('ROLE_ADMIN')) {
    throw new Error("Accès réservé à l'administration");
  }
  return {
    token: data.token,
    email: typeof payload.email === 'string' ? payload.email : email,
    name: typeof payload.name === 'string' ? payload.name : email,
  };
}
