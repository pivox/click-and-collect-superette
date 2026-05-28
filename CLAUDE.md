# CLAUDE.md — Click & Collect Supérette Tunisie

@AGENTS.md
@AI_CONTEXT.md
@Claude/instructions.md
@Claude/workflows.md

## Règles prioritaires

1. Lire `AGENTS.md` et `AI_CONTEXT.md` avant toute proposition.
2. Respecter le périmètre MVP.
3. Répondre en français par défaut.
4. Ne pas introduire paiement, livraison ou marketplace multi-marchands sans demande explicite.
5. Préserver le vocabulaire métier : **Kadhia**, supérette, marchand, client, rendez-vous, retrait.
6. Pour chaque changement, expliquer : fichiers modifiés, raison, risque, test ou vérification.

## Architecture

Monorepo : `apps/backend/` (Symfony 7 · API Platform 4 · PostgreSQL · Doctrine)
et `apps/frontend/` (Next.js 14 · React Query · Tailwind CSS).

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

**Globales (`~/.claude/commands/`) :**
- `/review-pr` — revue de PR complète (multi-agents)
- `/feature-dev` — workflow implémentation feature (discovery → clarification → architecture → code)
- `/revise-claude-md` — met à jour CLAUDE.md avec les apprentissages de la session
- `/claude-md-improver` — audite et améliore les fichiers CLAUDE.md

**Hook automatique :** coller une URL `github.com/pivox/click-and-collect-superette/pull/{N}` dans le prompt déclenche automatiquement une revue de PR sans commande explicite.

## Workflow features

Les specs de chaque feature sont dans `prompts/` (ex. `prompts/s7-003-data-retention.md`).
Commande type : `traite @prompts/s7-XXX-nom.md et pousse une pr`.
Avant d'implémenter, vérifier si la feature est déjà livrée : `git log --oneline | grep s7-XXX`.

Prompts Sprint 7 disponibles : `s7-003-data-retention`, `s7-004-admin-audit-trail`, `s7-005-production-observability`, `s7-006-pwa-offline`, `s7-007-accessibility-wcag`, `s7-008-sprint7-audit`.

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
