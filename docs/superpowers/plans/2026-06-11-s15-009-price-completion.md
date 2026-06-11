# S15-009 Price Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let merchants find products imported from product groups with placeholder prices, enter real prices, and publish only complete products.

**Architecture:** Reuse the merchant catalog list and edit drawer. Add a `completion=needs_price` filter and `requires_price_completion` output flag, then enforce the business guard in the catalog update processor so `is_visible=true` is rejected unless the final price is positive.

**Tech Stack:** Symfony 7, API Platform 4, Doctrine ORM, PHPUnit, Next.js 14, React Testing Library, Vitest, Tailwind CSS.

---

### Task 1: Backend Completion Filter And Publication Guard

**Files:**
- Modify: `apps/backend/src/ApiResource/MerchantCatalogListOutput.php`
- Modify: `apps/backend/src/ApiResource/MerchantCatalogProductOutput.php`
- Modify: `apps/backend/src/Mapper/MerchantCatalogProductMapper.php`
- Modify: `apps/backend/src/Provider/MerchantCatalogProductCollectionProvider.php`
- Modify: `apps/backend/src/Repository/MerchantProductRepository.php`
- Modify: `apps/backend/src/Processor/UpdateMerchantCatalogProductProcessor.php`
- Test: `apps/backend/tests/Functional/Api/MerchantCatalogApiTest.php`
- Test: `apps/backend/tests/Functional/Api/PublicStoreCatalogApiTest.php`

- [ ] **Step 1: Write failing backend tests**

Add tests that create one product at `0.000` and one product at `1.500`, verify `completion=needs_price` returns only the placeholder product with `requires_price_completion=true`, verify `PATCH is_visible=true` is rejected while price is still `0.000`, and verify `PATCH price_tnd=1.900,is_visible=true` succeeds.

- [ ] **Step 2: Run backend tests to verify failure**

Run: `vendor/bin/phpunit tests/Functional/Api/MerchantCatalogApiTest.php tests/Functional/Api/PublicStoreCatalogApiTest.php --testdox`

Expected before implementation: at least one failure because the filter, output flag, or guard does not exist.

- [ ] **Step 3: Implement backend behavior**

Add `completion` as an optional query parameter, pass it through the provider to the repository, filter `needs_price` with `bccomp($product->getPriceTnd(), '0.000', 3) === 0`, expose `requires_price_completion`, and reject attempted visibility when the final price is not positive.

- [ ] **Step 4: Run backend tests to verify pass**

Run: `vendor/bin/phpunit tests/Functional/Api/MerchantCatalogApiTest.php tests/Functional/Api/PublicStoreCatalogApiTest.php tests/Functional/Api/MerchantProductPriceHistoryApiTest.php --testdox`

Expected after implementation: all targeted backend tests pass.

### Task 2: Frontend Completion Workflow

**Files:**
- Modify: `apps/frontend/src/lib/types/merchant-catalog.types.ts`
- Modify: `apps/frontend/src/lib/services/merchant-catalog.service.ts`
- Modify: `apps/frontend/src/app/merchant/catalogue/page.tsx`
- Modify: `apps/frontend/src/components/merchant/catalogue/MerchantCatalogTable.tsx`
- Modify: `apps/frontend/src/components/merchant/catalogue/MerchantCatalogEditDrawer.tsx`
- Test: `apps/frontend/src/tests/merchant.catalogue.test.tsx`

- [ ] **Step 1: Write failing frontend tests**

Add tests that verify the page can switch to products to complete, sends `completion: 'needs_price'`, shows an `À compléter` badge, prevents publishing a product while the drawer price is `0.000`, and allows saving a positive price with `is_visible=true`.

- [ ] **Step 2: Run frontend tests to verify failure**

Run: `npm run test:run -- src/tests/merchant.catalogue.test.tsx`

Expected before implementation: at least one failure because `completion` and the badge/guard are not implemented.

- [ ] **Step 3: Implement frontend behavior**

Extend the filter type and service params with `completion`, add a compact action on the merchant catalog page to apply the needs-price filter, render `À compléter` in the table when `requires_price_completion` is true, and block the visible checkbox in the drawer until the draft price is positive.

- [ ] **Step 4: Run frontend tests to verify pass**

Run: `npm run test:run -- src/tests/merchant.catalogue.test.tsx src/tests/merchant.product-groups.test.tsx src/tests/merchant.product-groups.service.test.ts`

Expected after implementation: all targeted frontend tests pass.

### Task 3: Documentation And PR

**Files:**
- Modify: `docs/Sprint15/README.md`
- Modify: `docs/product/product-groups.md`

- [ ] **Step 1: Update docs**

Mark #467 as delivered and document that imported products can be filtered as `completion=needs_price`, remain invisible until a positive merchant-owned price is set, and create merchant price history on first real price entry.

- [ ] **Step 2: Run final checks**

Run backend targeted tests, frontend targeted tests, `npm run lint`, and available backend quality checks that fit the touched surface.

- [ ] **Step 3: Commit, push, PR, review, merge**

Stage only intended files, commit with a concise S15-009 message, push `codex/s15-009-price-completion`, open a PR linked to #467, request Codex review, wait for checks/review, address feedback, merge only with green checks and no unresolved blocking Codex feedback, then update local `main`.
