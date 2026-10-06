import { apiClient } from '@/lib/api';
import { USE_MOCKS, mockDelay } from './index';

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
  try {
    return JSON.parse(json) as Record<string, unknown>;
  } catch {
    throw new Error('Invalid JWT payload: cannot parse JSON');
  }
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

export interface ClientUser {
  token: string;
  email: string;
  name: string;
}

/** JWT claims do not carry the customer's editable profile. */
export async function getClientProfile(): Promise<Pick<ClientUser, 'name' | 'email'>> {
  const { data } = await apiClient.get<Pick<ClientUser, 'name' | 'email'>>('/api/me/profile', {
    skipAuthRedirect: true,
    timeout: 10_000,
  });
  return data;
}

export async function clientLogin(email: string, password: string): Promise<ClientUser> {
  const { data } = await apiClient.post<{ token: string }>('/api/auth/login', {
    email,
    password,
  });
  const payload = decodeJwtPayload(data.token);
  const roles = Array.isArray(payload.roles) ? (payload.roles as string[]) : [];
  if (!roles.includes('ROLE_CUSTOMER')) {
    throw new Error('Accès réservé aux clients');
  }
  return {
    token: data.token,
    email: typeof payload.email === 'string' ? payload.email : email,
    name: typeof payload.name === 'string' ? payload.name : email,
  };
}

export async function clientRegister(
  email: string,
  password: string,
  name: string,
): Promise<void> {
  await apiClient.post('/api/auth/register/customer', { email, password, name });
}

export async function requestPasswordReset(email: string): Promise<void> {
  await apiClient.post('/api/auth/forgot-password', { email });
}

export async function confirmPasswordReset(token: string, password: string): Promise<void> {
  await apiClient.post('/api/auth/reset-password', { token, new_password: password });
}

export async function updateProfile(
  firstName: string,
  lastName: string,
): Promise<{ name: string }> {
  const name = `${firstName} ${lastName}`.trim();
  if (USE_MOCKS) {
    return mockDelay({ name });
  }
  const { data } = await apiClient.patch<{ name: string }>('/api/me/profile', { first_name: firstName, last_name: lastName });
  return { name: data.name };
}

export async function deleteAccount(): Promise<void> {
  if (USE_MOCKS) {
    return mockDelay(undefined);
  }
  await apiClient.delete('/api/me/account');
}
