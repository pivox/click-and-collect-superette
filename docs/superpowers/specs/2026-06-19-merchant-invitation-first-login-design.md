# Merchant Invitation First Login Design

## Goal

Deliver #515 by adding the public merchant invitation screen reached from the
email link `/merchant/invitation?token=...`.

## Scope

The page lets an invited marchand define a definitive password from the backend
invitation token delivered by #512. It does not change the temporary password
flow at `/merchant/premiere-connexion`, does not authenticate the marchand
automatically, and does not close #501.

## User Flow

1. The marchand opens `/merchant/invitation?token=...` from the email.
2. The frontend verifies the token with `POST /api/auth/merchant-invitations/verify`.
3. If the token is valid, the page shows a new password and confirmation form.
4. The frontend validates password length and matching confirmation.
5. The frontend finalizes with `POST /api/auth/merchant-invitations/complete`.
6. On `204`, the page confirms success and redirects to `/merchant/login`.

## Error Handling

- Missing token: show an invalid-link message and no API call.
- Invalid token: show that the invitation link is invalid.
- Expired token: show that the invitation link has expired.
- Used token: show that the invitation link was already used.
- Revoked token: show that a newer invitation replaced this link.
- Weak password or confirmation mismatch: validate client-side first; still map
  backend `422` to a password validation message.
- Unexpected API failure: show a generic retry message.

## Architecture

- Add `apps/frontend/src/lib/services/merchant-invitation.service.ts` for the
  two public API calls.
- Add `apps/frontend/src/app/merchant/invitation/page.tsx` as a client page.
- Add `apps/frontend/src/tests/merchant.invitation.test.tsx` for UI and service
  integration behavior using mocked services.

## Testing

Tests cover render/loading, missing token, verify error states, validation
before API calls, successful finalization and redirect, and generic API errors.
