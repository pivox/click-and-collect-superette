# Merchant Invitation First Login Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the public merchant invitation first-login page for #515.

**Architecture:** The frontend keeps invitation API calls in a focused service and renders a standalone public page at `/merchant/invitation`. The page reads the token from the URL, verifies it before showing the password form, completes the invitation through the backend, and redirects to merchant login after success.

**Tech Stack:** Next.js App Router, React client components, Vitest, Testing Library, Axios API client.

---

### Task 1: Invitation API Service

**Files:**
- Create: `apps/frontend/src/lib/services/merchant-invitation.service.ts`
- Test through page tests: `apps/frontend/src/tests/merchant.invitation.test.tsx`

- [ ] **Step 1: Write service expectations in the page test mock**

Mock `verifyMerchantInvitation` and `completeMerchantInvitation` from
`@/lib/services/merchant-invitation.service` in the new page test.

- [ ] **Step 2: Implement the service**

Create functions with these payloads:

```ts
export interface MerchantInvitationVerification {
  status: 'valid';
  expiresAt: string;
}

export async function verifyMerchantInvitation(token: string): Promise<MerchantInvitationVerification> {
  const { data } = await apiClient.post<{ status: 'valid'; expires_at: string }>(
    '/api/auth/merchant-invitations/verify',
    { token },
    { skipAuthRedirect: true },
  );
  return { status: data.status, expiresAt: data.expires_at };
}

export async function completeMerchantInvitation(input: {
  token: string;
  newPassword: string;
  newPasswordConfirmation: string;
}): Promise<void> {
  await apiClient.post(
    '/api/auth/merchant-invitations/complete',
    {
      token: input.token,
      new_password: input.newPassword,
      new_password_confirmation: input.newPasswordConfirmation,
    },
    { skipAuthRedirect: true },
  );
}
```

### Task 2: Invitation Page Tests First

**Files:**
- Create: `apps/frontend/src/tests/merchant.invitation.test.tsx`
- Create: `apps/frontend/src/app/merchant/invitation/page.tsx`

- [ ] **Step 1: Write RED tests**

Add tests for:

- missing token shows invalid-link state and does not call verify;
- valid token verifies and shows the form;
- expired, used, revoked, invalid verification errors show specific messages;
- short password and mismatched confirmation do not call complete;
- successful completion calls the API, does not store the token or passwords,
  and redirects to `/merchant/login`;
- unexpected completion failure shows a generic error.

- [ ] **Step 2: Run RED**

Run:

```bash
docker compose run --rm frontend npm run test:run -- src/tests/merchant.invitation.test.tsx
```

Expected: fail because the page and service do not exist yet.

### Task 3: Invitation Page Implementation

**Files:**
- Create: `apps/frontend/src/app/merchant/invitation/page.tsx`
- Create: `apps/frontend/src/lib/services/merchant-invitation.service.ts`

- [ ] **Step 1: Implement minimal page**

Use a client component with `useSearchParams`, `useRouter`, `useEffect`, local
form state, and the service functions.

- [ ] **Step 2: Map backend errors**

Map backend `detail` codes:

- `MERCHANT_INVITATION_TOKEN_EXPIRED` -> expired message;
- `MERCHANT_INVITATION_TOKEN_ALREADY_USED` -> used message;
- `MERCHANT_INVITATION_TOKEN_REVOKED` -> replaced message;
- `MERCHANT_INVITATION_TOKEN_INVALID` -> invalid message;
- `AUTH_WEAK_PASSWORD` or `MERCHANT_INVITATION_PASSWORD_CONFIRMATION_MISMATCH` -> password validation message.

- [ ] **Step 3: Run GREEN**

Run:

```bash
docker compose run --rm frontend npm run test:run -- src/tests/merchant.invitation.test.tsx
```

Expected: all new tests pass.

### Task 4: Documentation And Verification

**Files:**
- Modify: `docs/Sprint15/S15-018-merchant-account-admin-onboarding.md`
- Modify: `docs/qa/s15-first-login-merchant-qa-report.md`

- [ ] **Step 1: Update docs**

Document that #515 now covers the frontend invitation screen and remove the
previous limitation that UI invitation tests were absent.

- [ ] **Step 2: Run targeted checks**

Run:

```bash
docker compose run --rm frontend npm run test:run -- src/tests/merchant.invitation.test.tsx src/tests/merchant.first-login.test.tsx src/tests/merchant.auth-first-login.test.tsx src/tests/api.interceptor.test.ts
docker compose run --rm frontend npm run lint
git diff --check
```

Expected: all commands exit 0.
