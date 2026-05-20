# CLAUDE.md — Click & Collect Supérette Tunisie

@AGENTS.md
@AI_CONTEXT.md
@Claude/instructions.md
@Claude/workflows.md
@Claude/checklist.md

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

## Commandes projet

### Backend (`apps/backend/`)

```bash
cd apps/backend
composer install
symfony console lexik:jwt:generate-keypair   # première installation uniquement
symfony console doctrine:migrations:migrate
symfony server:start
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=512M
vendor/bin/php-cs-fixer fix --dry-run --diff  # vérifier
vendor/bin/php-cs-fixer fix                   # corriger
php bin/console debug:router | grep "mon-pattern"   # vérifier les routes après ajout
vendor/bin/phpunit tests/Functional/Api/MonTest.php --testdox  # test ciblé
```

### Frontend (`apps/frontend/`)

```bash
cd apps/frontend
npm install
npm run dev    # dev sur http://localhost:3000
npm run build
npm run lint
```
