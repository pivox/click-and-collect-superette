# Checklist readiness production

> Mise à jour S7-008 — 2026-05-27. Basée sur l'audit Sprint 7 (`docs/Sprint7/completion-report.md`).

## Bloquants avant déploiement (risques R1 + R2)

- [ ] **R1 — Extension PostgreSQL `unaccent`** : exécuter `CREATE EXTENSION IF NOT EXISTS unaccent;` sur la base de production avant de démarrer le backend (la recherche retourne HTTP 500 sans cette extension)
- [ ] **R2 — Transport Messenger async** : configurer `doctrine://` ou `redis://` dans `messenger.yaml` ; ne pas utiliser `sync://` en production — les `DelayStamp` sont ignorés et les messages perdus au redémarrage

## Transport Messenger

- [ ] Transport async persistant configuré (PostgreSQL ou Redis, pas sync) — variable `MESSENGER_TRANSPORT_DSN`
- [ ] Worker actif : `php bin/console messenger:consume async --time-limit=3600`
- [ ] Worker supervisé (Supervisor ou systemd — redémarrage automatique en cas de crash)
- [ ] `failure_transport` configuré pour les messages en échec (ne pas bloquer la file principale)
- [ ] Index sur `messenger_messages(queue_name, available_at)` créé pour éviter les scans complets

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
- [ ] Drift schéma nettoyé (table `product_import_raw` + colonne `product_references.source_import_raw_id` à supprimer manuellement avant prod)
- [ ] Sauvegarde automatique configurée

## Qualité statique (à valider avant déploiement)

- [ ] PHPStan niveau 8 : `vendor/bin/phpstan analyse --memory-limit=512M` → 0 erreur
- [ ] CS Fixer : `vendor/bin/php-cs-fixer fix --dry-run --diff` → 0 diff
- [ ] `symfony console doctrine:schema:validate` → mapping OK
- [ ] `symfony console lint:container` → OK

## Logs

- [ ] Chemin logs writeable
- [ ] Rotation logs configurée
- [ ] Logs structurés activés (actions critiques : `order.submitted`, `order.status_changed`, `store.archived`, `messenger.failure`)

## Monitoring

- [ ] Endpoint `/api/health` accessible depuis reverse proxy / load balancer
- [ ] Alerting sur statut `/api/health`
- [ ] Commande diagnostics : `php bin/console app:diagnostics:check` → 0 anomalie

## Sécurité

- [ ] Routes admin → 401 anonyme (vérifié en local)
- [ ] Routes marchand → 401 anonyme + ownership vérifié
- [ ] Aucun token/password dans les réponses API
- [ ] `trusted_proxies` configuré si derrière un reverse proxy
