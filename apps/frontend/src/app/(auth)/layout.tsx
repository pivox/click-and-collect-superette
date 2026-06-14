import type { Metadata } from 'next';
import { defaultViewport } from '@/lib/config/viewport';

export const viewport = defaultViewport;

export const metadata: Metadata = {
  title: 'Kadhia · Compte',
};

/**
 * Neutral layout for portal-agnostic auth pages (password reset).
 * These pages are shared by the customer, merchant and admin portals, so they
 * must not inherit any portal-specific chrome (navigation, providers, widgets).
 */
export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return <div className="min-h-screen bg-bg">{children}</div>;
}
