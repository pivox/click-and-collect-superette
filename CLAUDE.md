# CLAUDE.md — Click & Collect Supérette Tunisie

@AGENTS.md
@AI_CONTEXT.md
@Claude/instructions.md
@Claude/workflows.md

> `Claude/checklist.md` n'est pas importé : son contenu est intégré dans
> `Claude/workflows.md` (**Workflow 6 — Validation finale**), déjà importé ci-dessus.
> `AI_CONTEXT.md` est la source de vérité pour l'état courant du projet ;
> en cas de contradiction avec ce fichier, `AI_CONTEXT.md` prime.

## Règles prioritaires

1. Lire `AGENTS.md` et `AI_CONTEXT.md` avant toute proposition.
2. Respecter le périmètre MVP.
3. Répondre en français par défaut.
4. Ne pas introduire paiement, livraison ou marketplace multi-marchands sans demande explicite.
5. Préserver le vocabulaire métier : **Kadhia**, supérette, marchand, client, rendez-vous, retrait.
6. Pour chaque changement, expliquer : fichiers modifiés, raison, risque, test ou vérification.

## Architecture

Monorepo : `apps/backend/` (Symfony 7 · API Platform 4 · PostgreSQL · Doctrine)
et `apps/frontend/` (Next.js 14 · Tailwind CSS · `@tanstack/react-query` en usage limité).

> **Pattern dominant frontend** : `useState` + `useEffect` + `useCallback`. React Query n'est utilisé que dans `GlobalSearchBar` et `StoreSearchCombobox` (autocomplete).

**Dossiers backend clés (`apps/backend/src/`) :**
- `Entity/` — entités Doctrine (voir liste dans AI_CONTEXT.md)
- `ApiResource/` — Output DTOs annotés `#[ApiResource]`
- `Processor/` — écriture (POST/PATCH/DELETE)
- `Provider/` — lecture (GET collection/item)
- `Dto/` — Input DTOs avec contraintes de validation
- `Service/` — logique métier (NotificationService, OrderStatusLogRecorder…)
- `MessageHandler/` — handlers Symfony Messenger (async)

- `.claude/rules/` — règles auto-chargées : backend-patterns, migrations, security, testing, github

## Commandes projet

### Backend (`apps/backend/`)

```bash
cd apps/backend
composer install
symfony console lexik:jwt:generate-keypair   # première installation uniquement
symfony server:start

# Tests
vendor/bin/phpunit
vendor/bin/phpunit tests/Functional/Api/MonTest.php --testdox  # classe ciblée
vendor/bin/phpunit --filter testMethodName                      # méthode ciblée
vendor/bin/phpunit --testdox 2>&1 | tail -40                    # sortie concise (les [error] sur 403/404 sont normaux)

# Qualité (check complet avant PR)
vendor/bin/phpstan analyse --memory-limit=512M && vendor/bin/php-cs-fixer fix --dry-run --diff && vendor/bin/phpunit

vendor/bin/phpstan analyse --memory-limit=512M
vendor/bin/php-cs-fixer fix --dry-run --diff  # vérifier
vendor/bin/php-cs-fixer fix                   # corriger

# Base de données
symfony console doctrine:migrations:diff                         # générer une migration
symfony console doctrine:migrations:migrate --no-interaction    # appliquer en local (demande approbation Claude)

# Debug
php bin/console debug:router | grep "mon-pattern"   # vérifier les routes après ajout
```

> **Permissions bloquées** (demandent approbation explicite) : `doctrine:migrations:migrate`, `composer require`, `composer remove`, `git push --force`, lecture de `.env`.


### Commandes slash disponibles

**Projet (`.claude/commands/`) :**
- `/init-context` — charge le contexte complet + choisit le sous-agent (démarrage recommandé)
- `/api-resource` — conçoit ou révise une ressource API Platform
- `/mvp-check` — vérifie qu'une demande reste dans le périmètre MVP
- `/product-reference` — workflow référentiel produit

**Plugins (`.claude/settings.json` → `enabledPlugins`, marketplace officiel `claude-plugins-official`) :**
- `pr-review-toolkit` — `/review-pr`, revue de PR complète (multi-agents)
- `feature-dev` — `/feature-dev`, workflow implémentation feature (discovery → clarification → architecture → code)
- `claude-md-management` — `/revise-claude-md` (apprentissages de session) + skill `claude-md-improver` (audit CLAUDE.md)
- `commit-commands` — messages de commit conventionnels, staging intelligent, création de PR
- `php-lsp` — Intelephense sur `apps/backend/` : navigation, références, diagnostics temps réel
- `typescript-lsp` — typescript-language-server sur `apps/frontend/`

> **Prérequis binaires LSP** (une fois par poste) : `npm install -g intelephense typescript-language-server typescript`.
> Sans eux, les plugins LSP restent inactifs (erreur visible dans `/plugin` → Errors, non bloquante).

> **Migration :** `/review-pr`, `/feature-dev` et `/revise-claude-md` viennent désormais des plugins.
> Si des copies manuelles existent encore dans `~/.claude/commands/`, `~/.claude/agents/` ou `~/.claude/skills/`
> (anciennes installations), les supprimer pour éviter les doublons de commandes.

**Hook automatique :** coller une URL `github.com/pivox/click-and-collect-superette/pull/{N}` dans le prompt déclenche automatiquement une revue de PR sans commande explicite.

> **Tip :** taper `#` pendant une session Claude permet d'incorporer automatiquement les apprentissages en cours dans CLAUDE.md.

## Workflow features

Les features sont pilotées par les issues GitHub (`gh issue list`) ; les specs de sprint
vivent dans `docs/Sprint{N}/` et la roadmap active est référencée dans `AI_CONTEXT.md`.
Avant d'implémenter, vérifier si la feature est déjà livrée : `git log --oneline | grep sX-XXX`
(ou par numéro d'issue pour les épics récentes).

**État d'avancement : voir `AI_CONTEXT.md`** (source de vérité, section « Avancement global »).
Ne pas dupliquer ici l'état des sprints — il devient obsolète plus vite que ce fichier.

### Clôture de sprint (audit documentaire)

Branche : `docs/sN-XXX-sprintN-completion-audit`
Rapport : `docs/Sprint{N}/completion-report.md` (résultats réels des tests, routes, migrations, limites)
Commit : `docs(sN-XXX): audit et clôture Sprint N`

### Frontend (`apps/frontend/`)

```bash
cd apps/frontend
npm install
npm run dev      # dev sur http://localhost:3000
npm run build
npm run lint
npm run test:run  # tests vitest non-interactifs (CI)
npm test          # tests vitest en mode watch
```

**Variable critique :** `NEXT_PUBLIC_USE_MOCKS`
- Défaut `"1"` → données fictives (mocks en mémoire, backend ignoré)
- Pour appeler l'API réelle : ajouter `NEXT_PUBLIC_USE_MOCKS=0` dans `apps/frontend/.env.local`

**Tokens JWT** (`localStorage`) :
- `/(client)` et `/` → `jwt_token`
- `/merchant/*` → `merchant_token`
- `/admin/*` → `admin_token`

**Debug créneaux vides** : inspecter `localStorage['kadhia:current'].shopId` — si ce shopId pointe vers une supérette sans créneaux configurés, le client voit "Aucun créneau disponible".

**Structure des routes frontend :**
- `src/app/(client)/` — parcours client (catalogue, kadhia, commandes, profil)
- `src/app/merchant/` — interface marchand (commandes, créneaux, retrait, notifications)
- `src/app/admin/` — backoffice admin (dashboard, marchands, supérettes, référentiel, audit)

## Gotchas backend (voir aussi `.claude/rules/backend-patterns.md`)

**`enum` dans `QueryParameter` schema → 422 avant le provider (S15-010)**
`schema: ['type' => 'string', 'enum' => ['active', 'expired']]` sur un `#[QueryParameter]`
déclenche la validation native API Platform *avant* d'entrer dans le provider → 422 au lieu
du 400 attendu. Supprimer `'enum' => [...]` du schema et valider la valeur dans le provider
via `BadRequestHttpException` (pattern #12).

**`Assert\Choice` sur enum typé → 422 systématique (pattern #26)**
Ne jamais mettre `#[Assert\Choice(callback: [MyEnum::class, 'values'])]` sur un champ `ProductUnit $unit` (type PHP enum). Le validateur compare une instance d'enum à une liste de strings → validation échoue pour toutes les requêtes. Réserver `Assert\Choice` aux champs `?string`.

**UUID nil rejeté par `Assert\Uuid` dans les tests fonctionnels (pattern #27)**
`00000000-0000-0000-0000-000000000000` peut retourner 422 au lieu de 404 dans les tests qui vérifient "not found". Utiliser un vrai UUID v4 non-existant (ex. `550e8400-e29b-41d4-a716-446655440000`).

**Suite de tests complète — mémoire**
`vendor/bin/phpunit` complet peut tuer `ApiDocsExposureTest` par exhaustion mémoire dans le sérialiseur OpenAPI. Le test passe seul. Cibler les suites par classe ou lancer en dernier.

**Doctrine cascade dans les tests — `addLine()` obligatoire**
Persister une `KadhiaLine` via `$em->persist($line)` seul sans appeler `$kadhia->addLine($line)` laisse la collection Doctrine vide : `cascade: ['remove']` ne propage pas le DELETE. Toujours passer par `addLine()` dans les tests de suppression.

**Pattern "best-effort" notification — second `flush()` obligatoire (PR #232)**
Quand un `notifyXxx()` est placé après le premier `flush()` pour isoler les erreurs de notification, il faut un second `flush()` à l'intérieur du try-catch. Sans lui, la notification est `persist()`ée dans l'Unit of Work Doctrine mais jamais écrite en base.
```php
try {
    $this->notificationService->notifyCustomerOrderAccepted($order);
    $this->entityManager->flush(); // ← obligatoire même en mode best-effort
} catch (\Throwable $e) { ... }
```

## Gotchas frontend (voir aussi `apps/frontend/src/tests/`)

**`new Date('YYYY-MM-DD')` décale la date d'un jour à l'ouest d'UTC**
`new Date('2026-06-30')` est interprété comme minuit UTC → affiché comme `29/06` dans les
navigateurs FR/TN (UTC+1). Pour formater une date métier, splitter la chaîne directement :
```ts
const [year, month, day] = isoDate.split('-');
return `${day}/${month}/${year}`;
```

**`requestSeq` — ignorer les réponses en vol devenues obsolètes**
Quand un filtre change pendant qu'une requête est en vol, la réponse tardive peut écraser
la liste avec des données périmées. Pattern à appliquer dans toutes les pages admin paginées :
```ts
const requestSeq = useRef(0);
const load = useCallback(async () => {
  const seq = ++requestSeq.current;
  // ...
  if (requestSeq.current !== seq) return; // ignore stale response
  setData(result);
}, [deps]);
```

**`MerchantLocalProductOutput` exige `pack_quantity: number` (depuis Sprint 8)**
Les mocks de test qui omettent ce champ font échouer TypeScript CI silencieusement. Toujours inclure `pack_quantity: 1` dans les fixtures `createMerchantLocalProduct`.

**Testing Library — label avec `<span>` enfant → `{ exact: false }`**
`getByLabelText('Prix TND')` échoue si le label contient `<span>*</span>` (astérisque requis).
Utiliser `getByLabelText('Prix TND', { exact: false })`.

**`Store.logo_url` / `cover_url` absents de la réponse liste**
Ces champs sont item-only : retournés uniquement par `GET /{id}`, `POST` et `PATCH`. La réponse
collection (`GET /api/admin/stores`) ne les inclut pas → toujours `undefined` dans les vues liste.
Ne pas les utiliser comme indicateurs de complétude dans `AdminTable`.

**`navigator.clipboard` requiert HTTPS**
`navigator.clipboard.writeText()` n'est disponible qu'en contexte sécurisé. En HTTP, l'API est
`undefined` et lève un `TypeError`. Toujours entourer d'un `try/catch` avec feedback utilisateur.

**`useCallback` obligatoire pour les fonctions `load` dans les pages admin**
Sans `useCallback`, la dépendance `[load]` dans `useEffect` force une suppression du warning via
`eslint-disable-line` — piège si on ajoute des filtres plus tard (stale closure silencieuse).
Toutes les pages admin paginated suivent le pattern : `const load = useCallback(async () => {...}, [deps])`.

<!-- imported-from: codex:project:instructions -->
# AGENTS.md — Click & Collect Supérette Tunisie

## Project mission

Build and maintain a full-stack click & collect application for local supérettes in Tunisia.

The customer scans a shop QR code, opens the shop space, browses products, prepares a **Kadhia**, chooses a pickup slot, submits the order, then picks it up after merchant validation.

The repository now contains backend, frontend, admin, merchant and product documentation. `docs/project/source-of-truth.md` is the project entry point and precedence document. `AI_CONTEXT.md` remains the shared AI context for MVP scope, business vocabulary, order statuses and reference entities.

## Always read first

Before changing anything, read these files when present:

1. `AI_CONTEXT.md` — shared AI context for current project state, MVP scope, business vocabulary, order statuses and reference entities
2. `README.md` — development workflow and repository orientation
3. `docs/project/source-of-truth.md` — project entry point and documentation precedence
4. relevant `docs/product/`, sprint or roadmap documentation for the task

**Codex CLI** — then read: `Codex/instructions.md`, `Codex/workflows.md`, `Codex/checklist.md`

**Claude Code** — `CLAUDE.md` handles agent-specific config via `@imports` (`Claude/instructions.md`, `Claude/workflows.md`).

## Language and tone

- Respond in French by default.
- Keep product documentation clear, concrete and MVP-oriented.
- Use English comments in code when code is added.
- Preserve the business vocabulary defined in `AI_CONTEXT.md`: **Kadhia**, supérette, marchand, client, rendez-vous, retrait.

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

## Verification commands

Do not invent successful test results.

For this repository, run PHP, Symfony and Composer commands through Docker Compose or the existing `make` targets. Do not use host `php`, `composer` or `symfony` for project checks.

When a Symfony application is added, prefer these checks when available:

- `docker compose exec backend composer validate`
- `docker compose exec backend composer install`
- `docker compose exec backend php bin/console lint:container`
- `docker compose exec backend php bin/console doctrine:schema:validate`
- `docker compose exec backend php bin/phpunit`
- `docker compose exec backend vendor/bin/phpstan analyse`
- `docker compose exec backend vendor/bin/php-cs-fixer fix --dry-run --diff`

Equivalent `make` targets are preferred when available: `make test-backend`, `make lint-backend`, `make validate`, `make phpunit`.

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
