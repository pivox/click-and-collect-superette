# Checklist readiness production

> Mise à jour S7-008 — 2026-05-27. Basée sur l'audit Sprint 7 (`docs/Sprint7/completion-report.md`).
> Mise à jour 2026-10-14 : stack de production et runbooks ajoutés. Aucun déploiement réel n'a
> encore eu lieu : les cases qui dépendent d'un déploiement restent décochées.
>
> Références : [`docs/ops/deployment.md`](../ops/deployment.md) (déploiement, mise à jour, rollback),
> [`docs/ops/backup-restore.md`](../ops/backup-restore.md) (sauvegarde et restauration),
> [`docs/ops/env.prod.example`](../ops/env.prod.example) (variables d'environnement).

## Bloquants avant déploiement (risques R1 + R2)

- [x] **R1 — Extension PostgreSQL `unaccent`** : migration `Version20260526100000` crée l'extension (`CREATE EXTENSION IF NOT EXISTS unaccent`) et les index de recherche — résolu dans PR #152
- [x] **R2 — Transport Messenger async** : `messenger.yaml` configuré avec `doctrine://default?auto_setup=0`, `failure_transport`, `retry_strategy` ; table `messenger_messages` créée par `Version20260527100000` ; config Supervisor dans `docker/supervisor/` — résolu dans S7-009

## Transport Messenger

- [x] Transport async persistant configuré — `MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0` (surcharger en Redis en production si disponible)
- [x] `failure_transport: failed` configuré (file dédiée aux messages en échec, sans bloquer la file principale)
- [x] Index sur `messenger_messages(queue_name, available_at)` créé (`Version20260527100000`)
- [ ] Worker actif en production : service `worker` défini dans `docker-compose.prod.yml` (`messenger:consume async --time-limit=3600 --memory-limit=128M`) — **défini mais jamais démarré** ; à cocher après vérification sur le déploiement réel (`docs/ops/deployment.md` §5)
- [x] Worker supervisé via `restart: unless-stopped` dans `docker-compose.prod.yml` (healthcheck `pgrep` inclus) ; alternative : `docker/supervisor/messenger-worker.conf` — procédure : `docs/ops/messenger-worker.md`

## Variables d'environnement critiques

- [ ] `APP_ENV=prod`
- [ ] `APP_SECRET` (aléatoire, 32+ chars)
- [ ] `DATABASE_URL`
- [ ] `JWT_SECRET_KEY` / `JWT_PUBLIC_KEY`
- [ ] `CORS_ALLOW_ORIGIN`
- [ ] `MESSENGER_TRANSPORT_DSN` (doctrine ou redis — obligatoire pour les automatisations différées)
- [ ] `TRUSTED_PROXIES` (pour que `ip_address` dans `admin_audit_logs` soit fiable)

## Base de données

- [ ] Extension `unaccent` installée : `CREATE EXTENSION IF NOT EXISTS unaccent;`
- [ ] Migrations exécutées : `php bin/console doctrine:migrations:migrate`
  - `Version20260521100000` — `shops.archived_at`, `shops.archive_reason`
  - `Version20260521110000` — `users.deleted_at`, `users.last_login_at`
  - `Version20260521120000` — table `admin_audit_logs`
- [x] Drift schéma nettoyé — aucune action manuelle requise, contrairement à ce qui était indiqué ici. La migration `Version20260528072120` (committée le 2026-09-30) supprime la contrainte FK, l'index, la colonne `product_references.source_import_raw_id` puis la table `product_import_raw`, dans l'ordre imposé par PostgreSQL et avec des gardes `IF EXISTS`. Il suffit que les migrations soient appliquées.
- [ ] Sauvegarde automatique configurée — outillage et runbook présents (`scripts/ops/`, `docs/ops/backup-restore.md`), mais ni planification en place ni exercice de restauration réalisé ; ne cocher qu'après un exercice de restauration réussi

## Qualité statique (à valider avant déploiement)

- [ ] PHPStan niveau 8 : `vendor/bin/phpstan analyse --memory-limit=512M` → 0 erreur
- [ ] CS Fixer : `vendor/bin/php-cs-fixer fix --dry-run --diff` → 0 diff
- [ ] `symfony console doctrine:schema:validate` → mapping OK
- [ ] `symfony console lint:container` → OK

## Logs

- [ ] Chemin logs writeable — volume `backend_logs` défini, droits d'écriture non vérifiés (aucun conteneur démarré)
- [ ] Rotation logs configurée
- [ ] Logs structurés activés (actions critiques : `order.submitted`, `order.status_changed`, `store.archived`, `messenger.failure`)

## Monitoring

- [ ] Endpoint `/api/health` accessible depuis reverse proxy / load balancer — route Caddy et healthcheck conteneur définis, à vérifier sur le déploiement réel
- [ ] Alerting sur statut `/api/health`
- [ ] Commande diagnostics : `php bin/console app:diagnostics:check` → 0 anomalie

## Sécurité

- [ ] Routes admin → 401 anonyme (vérifié en local)
- [ ] Routes marchand → 401 anonyme + ownership vérifié
- [ ] Aucun token/password dans les réponses API
- [ ] `trusted_proxies` configuré si derrière un reverse proxy — `TRUSTED_PROXIES` câblé dans la stack (`framework.yaml`, `docker-compose.prod.yml`) ; effet réel sur `ip_address` non vérifié sans déploiement
