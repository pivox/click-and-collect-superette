import { apiClient } from '@/lib/api';
import { USE_MOCKS, mockDelay } from './index';

// MERCHANT-TEAM-006: team management, reserved to the primary account.

export interface MerchantTeamAccount {
  accountId: string;
  firstName: string | null;
  lastName: string | null;
  email: string;
  phone: string | null;
  status: 'invited' | 'active' | 'revoked';
  isPrimary: boolean;
  invitedAt: string | null;
  acceptedAt: string | null;
  revokedAt: string | null;
  invitationStatus?: string | null;
}

export interface MerchantTeam {
  storeId: string;
  organizationId: string;
  limit: number;
  activeOrInvitedCount: number;
  items: MerchantTeamAccount[];
}

export interface MerchantTeamInvitationInput {
  firstName: string;
  lastName: string;
  email: string;
  phone?: string | null;
}

interface ApiTeamAccount {
  account_id: string;
  first_name?: string | null;
  last_name?: string | null;
  email: string;
  phone?: string | null;
  status: 'invited' | 'active' | 'revoked';
  is_primary: boolean;
  invited_at?: string | null;
  accepted_at?: string | null;
  revoked_at?: string | null;
  invitation_status?: string | null;
}

function mapAccount(data: ApiTeamAccount): MerchantTeamAccount {
  return {
    accountId: data.account_id,
    firstName: data.first_name ?? null,
    lastName: data.last_name ?? null,
    email: data.email,
    phone: data.phone ?? null,
    status: data.status,
    isPrimary: data.is_primary,
    invitedAt: data.invited_at ?? null,
    acceptedAt: data.accepted_at ?? null,
    revokedAt: data.revoked_at ?? null,
    invitationStatus: data.invitation_status ?? null,
  };
}

let mockTeam: MerchantTeam = {
  storeId: 'store-1',
  organizationId: 'org-1',
  limit: 10,
  activeOrInvitedCount: 1,
  items: [
    {
      accountId: 'user-1',
      firstName: 'Ali',
      lastName: 'Ben Salah',
      email: 'merchant@example.test',
      phone: null,
      status: 'active',
      isPrimary: true,
      invitedAt: null,
      acceptedAt: null,
      revokedAt: null,
    },
  ],
};

export async function getMerchantTeam(storeId: string): Promise<MerchantTeam> {
  if (USE_MOCKS) return mockDelay({ ...mockTeam, items: [...mockTeam.items] });
  const { data } = await apiClient.get<{
    store_id: string;
    organization_id: string;
    limit: number;
    active_or_invited_count: number;
    items: ApiTeamAccount[];
  }>(`/api/merchant/stores/${storeId}/accounts`);
  return {
    storeId: data.store_id,
    organizationId: data.organization_id,
    limit: data.limit,
    activeOrInvitedCount: data.active_or_invited_count,
    items: (data.items ?? []).map(mapAccount),
  };
}

export async function inviteMerchantTeamAccount(
  storeId: string,
  input: MerchantTeamInvitationInput,
): Promise<MerchantTeamAccount> {
  if (USE_MOCKS) {
    const account: MerchantTeamAccount = {
      accountId: `user-${mockTeam.items.length + 1}`,
      firstName: input.firstName,
      lastName: input.lastName,
      email: input.email.trim().toLowerCase(),
      phone: input.phone ?? null,
      status: 'invited',
      isPrimary: false,
      invitedAt: new Date().toISOString(),
      acceptedAt: null,
      revokedAt: null,
      invitationStatus: 'sent',
    };
    mockTeam = {
      ...mockTeam,
      activeOrInvitedCount: mockTeam.activeOrInvitedCount + 1,
      items: [...mockTeam.items, account],
    };
    return mockDelay(account);
  }
  const { data } = await apiClient.post<ApiTeamAccount>(
    `/api/merchant/stores/${storeId}/account-invitations`,
    {
      first_name: input.firstName,
      last_name: input.lastName,
      email: input.email,
      phone: input.phone ?? null,
    },
  );
  return mapAccount(data);
}

export async function resendMerchantTeamInvitation(
  storeId: string,
  accountId: string,
): Promise<MerchantTeamAccount> {
  if (USE_MOCKS) {
    const account = mockTeam.items.find((item) => item.accountId === accountId);
    if (!account) throw new Error('MERCHANT_ACCOUNT_NOT_FOUND');
    return mockDelay({ ...account, invitationStatus: 'sent' });
  }
  const { data } = await apiClient.post<ApiTeamAccount>(
    `/api/merchant/stores/${storeId}/accounts/${accountId}/resend-invitation`,
    {},
  );
  return mapAccount(data);
}

export async function revokeMerchantTeamAccount(storeId: string, accountId: string): Promise<void> {
  if (USE_MOCKS) {
    mockTeam = {
      ...mockTeam,
      activeOrInvitedCount: Math.max(0, mockTeam.activeOrInvitedCount - 1),
      items: mockTeam.items.map((item) =>
        item.accountId === accountId
          ? { ...item, status: 'revoked' as const, revokedAt: new Date().toISOString() }
          : item,
      ),
    };
    await mockDelay(undefined);
    return;
  }
  await apiClient.delete(`/api/merchant/stores/${storeId}/accounts/${accountId}`);
}
