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

## Contexte pour les reviews automatiques GitHub Actions

### Stack technique

- **Backend** : PHP 8.2, Symfony 7, API Platform 3
- **ORM** : Doctrine ORM, PostgreSQL
- **Asynchrone** : Symfony Messenger
- **Tests** : PHPUnit, base SQLite en mémoire pour les tests fonctionnels
- **Qualité** : PHPStan, PHP-CS-Fixer

### Architecture

- API REST via API Platform avec séparation lecture/écriture (DTOs pour les opérations d'écriture).
- Sécurité JWT RS256 stateless — trois rôles séparés : `ROLE_CUSTOMER`, `ROLE_MERCHANT`, `ROLE_ADMIN`.
- Logique métier dans des services dédiés, jamais dans les contrôleurs.
- Référentiel produit partagé (`ProductReference`) séparé de l'offre marchand (`MerchantProductOffer`).
- Snapshots de prix gelés à la création des lignes de Kadhia — ne jamais re-fetcher le prix live.

### Ce que Claude doit particulièrement surveiller dans les reviews

- Transitions de statut de commande incohérentes (voir les statuts dans `AI_CONTEXT.md`).
- Mélange de rôles client/marchand/admin dans le même endpoint.
- Exposition d'IDs internes au lieu d'UUIDs dans les routes publiques.
- Requêtes N+1 Doctrine (jointures manquantes dans les QueryBuilder, lazy loading en boucle).
- Changements de schéma Doctrine sans migration accompagnante.
- DTOs absents pour les opérations d'écriture (couplage entité/API).
- Logique métier dans les contrôleurs ou processors au lieu des services.
- Absence de `#[IsGranted]` sur les opérations API Platform.
- Prix ou montants non exprimés en TND.
- Terme "panier" utilisé à la place de **Kadhia** dans le code ou les messages.
- Introduction de paiement en ligne, livraison ou marketplace sans décision explicite.

## Commandes projet

Le backend Symfony se trouve dans `apps/backend/`.

```bash
cd apps/backend
composer install
bin/console doctrine:migrations:migrate
symfony server:start
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
```
