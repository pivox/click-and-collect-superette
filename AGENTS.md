# AGENTS.md — Click & Collect Supérette Tunisie

## Project mission

Build a click & collect application for local supérettes in Tunisia.

The customer scans a shop QR code, opens the shop space, browses products, prepares a **Kadhia**, chooses a pickup slot, submits the order, then picks it up after merchant validation.

## Always read first

Before changing anything, read these files when present:

1. `AI_CONTEXT.md`
2. `README.md`
3. `docs/product/`
4. `Codex/instructions.md`
5. `Codex/workflows.md`
6. `Codex/checklist.md`

## Language and tone

- Respond in French by default.
- Keep product documentation clear, concrete and MVP-oriented.
- Use English comments in code when code is added.
- Preserve the business vocabulary: **Kadhia**, supérette, marchand, client, rendez-vous, retrait.

## MVP boundaries

Included in MVP:

- shop QR code access;
- simple catalog;
- Kadhia / cart;
- pickup slot selection;
- order submission;
- merchant accept/reject workflow;
- preparation states;
- pickup QR code;
- customer + merchant double validation;
- French / Arabic interface;
- prices in TND.

Excluded unless explicitly requested:

- online payment;
- delivery;
- loyalty program;
- complex warehouse stock management;
- multi-merchant marketplace cart.

## Product rules

- A product can exist in a shared Tunisian product reference, then be offered by each merchant with its own price, availability and visibility.
- Do not assume every merchant has the same price or availability.
- A customer must be able to find already-known products by name, brand, format and category.
- The QR code shop flow is central to the product.
- Pickup completion must keep a secure double-validation step.
- The app must be prepared for French and Arabic, including RTL UI constraints.

## Technical target

Preferred backend stack:

- Symfony 7;
- API Platform;
- Doctrine ORM;
- PostgreSQL;
- Symfony Messenger for async jobs;
- Redis only when useful;
- Mercure or WebSocket only when the use case requires realtime updates.

Preferred frontend target:

- mobile-first PWA for customers;
- responsive web backoffice for merchants;
- admin platform for supervision.

## Coding rules

- Prefer small, focused changes.
- Do not introduce new production dependencies without explaining why.
- Keep domain logic in services or domain classes, not controllers.
- Keep API resources explicit with serialization groups when needed.
- Use DTOs for write models when entity exposure would create coupling.
- Add migrations for database changes.
- Add tests for meaningful business behavior.
- Keep names explicit and business-oriented.

## Symfony/API Platform guidance

When backend code exists:

- Use API Platform operations for separate read/write routes when the payloads differ.
- Use serialization groups to expose different representations.
- Do not rely on eager loading as a substitute for clear API design.
- Avoid exposing internal persistence details to the client.
- Use validation constraints on input DTOs or entities.
- Use voters/security expressions for merchant/customer/admin access separation.

## Data model guidance

Prefer this conceptual model unless the project documents evolve:

- `Shop`
- `CustomerShop` or `FavoriteShop`
- `ProductReference`
- `ProductReferenceProposal`
- `ProductVariant`
- `MerchantProductOffer`
- `ProductFoodInfo`
- `ProductExternalSource`
- `Kadhia`
- `KadhiaLine`
- `PickupSlot`
- `Order`
- `OrderLine`
- `PickupSession`

## Verification commands

The repository is currently documentation-first. Do not invent successful test results.

When a Symfony application is added, prefer these checks when available:

- `composer validate`
- `composer install`
- `symfony console lint:container`
- `symfony console doctrine:schema:validate`
- `vendor/bin/phpunit`
- `vendor/bin/phpstan analyse`
- `vendor/bin/php-cs-fixer fix --dry-run --diff`

For documentation-only changes:

- check Markdown readability;
- check internal links;
- check that MVP scope remains coherent.

## Required final response format

For every task, summarize:

- what changed;
- files changed;
- verification performed or not performed;
- assumptions;
- risks / next steps.

Never claim that tests passed unless they were actually run.
