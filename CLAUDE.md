# CLAUDE.md — Click & Collect Supérette Tunisie

@AGENTS.md
@AI_CONTEXT.md
@Claude/instructions.md
@Claude/workflows.md

## Rôle attendu

Tu es l'assistant IA de développement et de cadrage produit du projet **Click & Collect Supérette Tunisie**.

Tu dois aider à produire :

- documentation produit MVP ;
- user stories ;
- architecture Symfony/API Platform ;
- modèle de données PostgreSQL/Doctrine ;
- API contracts ;
- backoffice marchand ;
- parcours client mobile-first ;
- décisions techniques traçables.

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
symfony console doctrine:migrations:migrate
symfony server:start
vendor/bin/phpunit
vendor/bin/phpunit --filter testMethodName    # cibler une méthode de test
vendor/bin/phpstan analyse --memory-limit=512M
vendor/bin/php-cs-fixer fix --dry-run --diff  # vérifier
vendor/bin/php-cs-fixer fix                   # corriger
grep -rn '\\sprintf\|\\array_map\|\\count(' apps/backend/src apps/backend/tests --include="*.php"  # \prefix non détecté par CS Fixer (§8)
php bin/console debug:router | grep "mon-pattern"   # vérifier les routes après ajout
vendor/bin/phpunit tests/Functional/Api/MonTest.php --testdox  # test ciblé
symfony console doctrine:migrations:diff                         # générer une migration
```

### Commandes slash disponibles

- `/init-context` — charge le contexte complet + choisit le sous-agent (démarrage recommandé)
- `/api-resource` — conçoit ou révise une ressource API Platform
- `/mvp-check` — vérifie qu'une demande reste dans le périmètre MVP
- `/product-reference` — workflow référentiel produit
- `/review` — revue de PR complète
- `/security-review` — audit sécurité des changements en cours
- `/simplify` — simplifie le code récemment écrit
- `/revise-claude-md` — met à jour CLAUDE.md avec les apprentissages de la session
- `/claude-md-improver` — audite et améliore les fichiers CLAUDE.md

## Workflow features

Les specs de chaque feature sont dans `prompts/` (ex. `prompts/s5-008-admin-product-proposals.md`).
Commande type : `traite @prompts/s5-XXX-nom.md et pousse une pr`.
Avant d'implémenter, vérifier si la feature est déjà livrée : `git log --oneline | grep s5-XXX`.

### Frontend (`apps/frontend/`)

```bash
cd apps/frontend
npm install
npm run dev    # dev sur http://localhost:3000
npm run build
npm run lint
```
