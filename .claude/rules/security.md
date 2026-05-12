# Security Rules

## Roles

- `ROLE_CUSTOMER` — authenticated customer, access to `/api/me/*` and public catalog
- `ROLE_MERCHANT` — authenticated merchant, access to `/api/merchant/*`
- `ROLE_ADMIN` — platform admin, access to `/api/admin/*`
- `PUBLIC_ACCESS` — unauthenticated, access to `/api/stores/*` (public info, catalog, slots)

## Authentication

- JWT RS256, stateless, 1h expiry (ADR-0003).
- No refresh token in MVP.
- Login endpoint: `POST /api/auth/login`.

## Authorization patterns

- Merchant shop ownership: `MerchantShopAccessChecker::denyUnlessMerchantOwnsShop()` — call in every merchant processor.
- Admin routes: secured globally via `security.yaml` pattern `^/api/admin` → `ROLE_ADMIN`.
- Customer routes: use `#[IsGranted('ROLE_CUSTOMER')]` on API resource operations.
- Voter `ShopOwnerVoter` for fine-grained shop ownership checks.

## Rules

- Never mix customer/merchant/admin operations in the same API resource.
- Never expose internal entity IDs in public-facing routes — use UUIDs.
- QR code tokens (`qrCodeToken`) are opaque — do not expose internal shop IDs via them.
- Prices are snapshots frozen at Kadhia line creation — do not re-fetch live prices on order submission.
