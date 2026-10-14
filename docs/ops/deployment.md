# Déploiement production — runbook

Ce document décrit comment déployer, mettre à jour et annuler une version de l'application
(Click & Collect Supérette Tunisie, « Kadhia ») sur un hôte unique avec Docker Compose.

Il documente la stack existante, il ne la redéfinit pas :

- `docker-compose.prod.yml` — définition des services ;
- `docker/caddy/Caddyfile`, `docker/backend/Dockerfile.prod`, `docker/backend/php-prod.ini`,
  `docker/frontend/Dockerfile.prod` — edge et images ;
- `docs/ops/env.prod.example` — modèle de configuration ;
- `docs/ops/backup-restore.md` — sauvegarde et restauration (à lire avant la mise en production) ;
- `docs/ops/messenger-worker.md` — détail du worker Messenger.

> **Statut de vérification.** Ce runbook a été écrit sans démon Docker : aucune image n'a été
> construite et aucun conteneur n'a été démarré. Lisez la section
> [Limites et points non vérifiés](#limites-et-points-non-vérifiés) **avant** le premier déploiement.
> Le premier déploiement réel est lui-même la première vérification de la stack.

## 1. Vue d'ensemble

| Service | Rôle | Port publié |
| --- | --- | --- |
| `caddy` | Edge unique : TLS (ACME), sert `/uploads/*`, FastCGI vers php-fpm pour `/api/*`, proxy vers Next.js pour le reste | 80/tcp, 443/tcp, 443/udp |
| `backend` | Symfony / API Platform sur php-fpm | aucun |
| `worker` | `messenger:consume async` : transitions différées | aucun |
| `frontend` | Next.js (build `standalone`) | aucun |
| `postgres` | PostgreSQL 16, **optionnel** (supprimable au profit d'une base managée) | aucun |

Volumes nommés (6) :

| Volume | Contenu | Criticité |
| --- | --- | --- |
| `uploads_products` | Images produit (écrites par php-fpm, lues par Caddy) | À sauvegarder : n'existent nulle part ailleurs |
| `backend_jwt` | Keypair JWT | Doit survivre à toutes les releases |
| `backend_logs` | Logs Monolog (fichiers rotatifs) | Diagnostic |
| `caddy_data` | Compte ACME et certificats | À conserver (limites de débit Let's Encrypt) |
| `caddy_config` | Configuration Caddy générée | Faible |
| `postgres_data` | Données PostgreSQL (si service conservé) | À sauvegarder |

Frontend et API partagent **une seule origine** : `/api/*` va à php-fpm, tout le reste à Next.js.

### Commandes

Les cibles Makefile `prod-*` encapsulent
`docker compose --env-file .env.prod -f docker-compose.prod.yml` (variable `DOCKER_COMPOSE_PROD`).
Voir `make help`. Ce runbook donne la cible Makefile et, entre parenthèses, la commande brute.

## 2. Prérequis

### Hôte

- Docker Engine et plugin Compose v2 (`docker compose`).
- Ports **80/tcp, 443/tcp et 443/udp** joignables depuis Internet (pare-feu et fournisseur
  compris). Les ports 80 et 443 sont nécessaires aux challenges ACME ; 443/udp sert HTTP/3.
- Aucun autre service ne doit déjà écouter sur ces ports.

### DNS

- L'enregistrement `A` (et `AAAA` si IPv6) de `CADDY_DOMAIN` doit **résoudre vers l'IP publique
  de l'hôte avant le premier démarrage**. Au démarrage, Caddy demande un certificat à l'autorité
  ACME ; si le DNS ne pointe pas encore sur l'hôte, l'émission échoue (voir dépannage).
- Vérification : `dig +short kadhia.example.tn` doit renvoyer l'IP de l'hôte.

### Services externes

- Un serveur SMTP (variable `MAILER_DSN`) : e-mails de réinitialisation de mot de passe et
  d'invitation marchand.
- Si PostgreSQL est managé : une instance 16, un rôle autorisé à `CREATE EXTENSION` (voir
  [Migrations](#6-migrations)), et `DATABASE_URL` avec `sslmode=require`.

## 3. Préparer `.env.prod`

```bash
cp docs/ops/env.prod.example .env.prod
chmod 600 .env.prod
```

`.env.prod` est ignoré par git (règle `.gitignore`). Il contient des secrets : ne le
committez pas, ne l'incluez pas dans des sauvegardes non chiffrées.

Remplissez toutes les variables marquées **REQUIRED** dans le fichier. Points d'attention :

- **Règle mono-domaine** : `CADDY_DOMAIN` (hôte seul, sans schéma), `FRONTEND_URL`,
  `DEFAULT_URI`, `NEXT_PUBLIC_API_URL`, `NEXT_PUBLIC_APP_URL`, `SOCIAL_CALLBACK_ORIGIN` et
  `CORS_ALLOW_ORIGIN` désignent le **même hôte**. Pas de `/` final dans les URL.
- `APP_SECRET` : 32+ caractères aléatoires (`openssl rand -hex 32`).
- `JWT_PASSPHRASE` : doit correspondre au keypair généré à l'étape suivante.
- Un mot de passe contenant `$` doit s'écrire `$$` (interpolation Compose), ou éviter `$`.
- `DATABASE_URL` : avec le service `postgres` fourni, hôte `postgres` et identifiants identiques
  à `POSTGRES_*`.
- Les variables `SOCIAL_*` doivent exister même si elles sont vides.

### Les `NEXT_PUBLIC_*` sont figés au build

Les six variables `NEXT_PUBLIC_*` (`NEXT_PUBLIC_USE_MOCKS`, `NEXT_PUBLIC_API_URL`,
`NEXT_PUBLIC_APP_URL`, `NEXT_PUBLIC_APP_VERSION`, `NEXT_PUBLIC_ENVIRONMENT`,
`NEXT_PUBLIC_VAPID_PUBLIC_KEY`) sont des **arguments de build** inclus dans le bundle JavaScript.

- Changer l'une d'elles exige `build frontend` puis `up -d`. Un simple `up -d` ou un redémarrage
  **n'a aucun effet**.
- `NEXT_PUBLIC_USE_MOCKS` doit valoir exactement `0`. Toute autre valeur (absente, vide, `1`)
  fait échouer le build volontairement : sans ce garde-fou, le frontend servirait des données
  fictives.

### Valider la configuration

```bash
make prod-config      # docker compose --env-file .env.prod -f docker-compose.prod.yml config
```

Une variable obligatoire manquante fait échouer la commande avec un message explicite.
Attention : la sortie contient les secrets résolus, ne la collez pas dans un ticket ou un canal.

## 4. Premier déploiement

Ordre exact. Chaque étape suppose la précédente réussie.

1. **Construire les images.**

   ```bash
   make prod-build
   ```

   C'est l'étape la plus susceptible d'échouer au premier essai (voir limites).

2. **Démarrer la base seule** (service `postgres` fourni ; ignorer avec une base managée) :

   ```bash
   docker compose --env-file .env.prod -f docker-compose.prod.yml up -d postgres
   docker compose --env-file .env.prod -f docker-compose.prod.yml ps   # attendre « healthy »
   ```

3. **Générer le keypair JWT, une seule fois.**

   ```bash
   make prod-jwt-keys
   ```

   Les clés sont écrites dans le volume `backend_jwt`, protégées par `JWT_PASSPHRASE`.
   **Ne relancez jamais cette commande lors d'une release** : un nouveau keypair invalide tous
   les tokens déjà émis (clients, marchands, administrateurs). Sauvegardez le volume
   `backend_jwt` avec le reste.

4. **Appliquer les migrations** (geste manuel, voir [section 6](#6-migrations)) :

   ```bash
   make prod-migrate
   ```

5. **Démarrer toute la stack.**

   ```bash
   make prod-up
   make prod-status
   ```

   Les 5 services ont un healthcheck conteneur. Attendez qu'ils passent `healthy`
   (`start_period` jusqu'à 40 s pour le backend). Au premier démarrage, Caddy obtient son
   certificat : quelques secondes à une minute.

6. **Vérifier** : section suivante.

7. **Créer le premier compte administrateur** selon la procédure d'amorçage en vigueur du
   projet (hors périmètre de ce runbook : voir la documentation d'administration).

## 5. Vérification post-déploiement

À faire après le premier déploiement **et** après chaque release.

| Contrôle | Commande / action | Attendu |
| --- | --- | --- |
| Santé applicative | `curl -fsS https://VOTRE_DOMAINE/api/health` (ou `CADDY_DOMAIN=... make prod-health`) | HTTP 200, `"status":"ok"` |
| Santé des conteneurs | `make prod-status` | 5 services `healthy` (4 si base managée) |
| HTTPS et redirection | `curl -sI http://VOTRE_DOMAINE/` | redirection vers `https://` |
| Frontend réel | ouvrir le site, parcourir une supérette réelle | pas de données fictives (sinon : dépannage) |
| Worker vivant | `make prod-logs SERVICE=worker` | process `messenger:consume` actif, pas de boucle d'erreurs |
| Volume d'uploads | téléverser **une vraie image produit** via l'interface admin, puis l'ouvrir | l'image s'affiche via `/uploads/products/...` |
| Specs machine | `curl -fsS https://VOTRE_DOMAINE/api/docs.json` | JSON OpenAPI |

Notes :

- **Worker** : il est indispensable. Sans lui, aucune transition différée ne part : expiration
  du délai de réponse marchand, rappel et expiration d'acceptation partielle, rappel de retrait.
  Contrôlez aussi la file : `GET /api/admin/ops/messenger` (admin).
- **Upload réel** : l'écriture se fait par php-fpm (`backend`), la lecture par Caddy. Seul un
  upload réel prouve que le volume partagé `uploads_products` est monté correctement des deux
  côtés (droits d'écriture de `www-data`, chemin lu par Caddy).
- **Swagger UI** `/api/docs.html` est dégradé en mono-domaine (ses assets `/bundles/*` ne sont
  pas exposés par l'edge). Ce n'est pas une panne : utilisez les specs machine.

## 6. Migrations

- Les migrations ne se lancent **jamais automatiquement** (règle `.claude/rules/migrations.md`).
  Aucune cible `prod-*` ne les enchaîne avec `up` : c'est un geste séparé et délibéré.
- Commande : `make prod-migrate`
  (`docker compose ... run --rm backend php bin/console doctrine:migrations:migrate --no-interaction`).
- Lecture seule : `make prod-migrate-status`.
- L'extension PostgreSQL `unaccent` est créée par la migration `Version20260526100000`
  (`CREATE EXTENSION IF NOT EXISTS unaccent`). Le rôle de `DATABASE_URL` doit donc être autorisé
  à `CREATE EXTENSION` (superuser, ou extension « trusted » selon la version et le fournisseur).
  Sinon, faites créer l'extension par un administrateur de la base avant la migration.
- Ne modifiez jamais une migration déjà fusionnée sur `main`.
- **Avant** toute migration sur une base contenant des données : sauvegarde fraîche
  (`docs/ops/backup-restore.md`).

## 7. Mise à jour (release)

Prérequis : une sauvegarde PostgreSQL récente et le tag de la version actuellement en
production noté (voir rollback).

1. Récupérer le code de la release : `git fetch && git checkout <tag-ou-commit>`.
2. Relire `docs/ops/env.prod.example` : une nouvelle variable obligatoire doit être ajoutée à
   `.env.prod`. `make prod-config` le signale.
3. Choisir un tag d'image explicite pour pouvoir revenir en arrière : renseigner `IMAGE_TAG`
   (ex. `IMAGE_TAG=2026-10-14` dans `.env.prod`). Par défaut le tag est `latest`, qui est
   écrasé à chaque build et ne permet pas le rollback.
4. Construire : `make prod-build`. Si un `NEXT_PUBLIC_*` a changé, le build du frontend est
   obligatoire (il est de toute façon inclus ici).
5. **Migrations** si la release en contient : `make prod-migrate-status`, puis
   `make prod-migrate`. Une migration non rétro-compatible avec l'ancien code expose
   l'ancienne version pendant la fenêtre qui suit : planifiez en conséquence.
6. Redémarrer les services avec les nouvelles images : `make prod-up`.
   Les conteneurs dont l'image a changé sont recréés ; les volumes sont conservés
   (**ne lancez pas** `prod-jwt-keys`).
7. Rejouer la [vérification post-déploiement](#5-vérification-post-déploiement).

Le worker est recréé avec le backend (même image) : un message en cours est repris selon la
stratégie de retry Messenger.

## 8. Rollback

Il y a deux niveaux. Choisissez selon que le schéma de base a changé ou non.

### A. Release sans migration : retour de l'image

1. Dans `.env.prod`, remettre `IMAGE_TAG` sur le tag précédent (l'image précédente doit
   toujours être présente localement ou dans un registre ; sinon la reconstruire depuis le
   tag git correspondant avec `git checkout <ancien-tag> && make prod-build`).
2. Si le frontend précédent avait d'autres `NEXT_PUBLIC_*`, ils sont figés dans son image : la
   reconstruction à partir de l'ancien code **et** de l'ancien `.env.prod` est nécessaire.
3. `make prod-up`, puis la vérification post-déploiement.

### B. Release avec migration : le code seul ne suffit pas

Le retour de l'image ne défait pas le schéma. Deux options, par ordre de préférence :

1. **Restaurer la sauvegarde pré-migration** (procédure
   `docs/ops/backup-restore.md`, section « Procédure de restauration pas à pas » : arrêter
   `backend` et `worker`, restaurer, revenir à l'image précédente, redémarrer). Les écritures
   faites depuis la migration sont **perdues**.
2. `doctrine:migrations:migrate prev` si la méthode `down()` de la migration a été testée
   (règle du projet : `down()` doit inverser `up()`), en connaissance des pertes de données
   possibles. À n'utiliser que si le `down()` a été relu pour cette release.

### Ce qui ne se « rollback » pas

- Le keypair JWT : ne jamais le régénérer pour revenir en arrière.
- Les fichiers de `uploads_products` : sauvegardés séparément (`backup-uploads.sh`).
- `docker compose down -v` **supprime les volumes** (dont la base, les images et le keypair).
  Ne l'utilisez jamais en production. `make prod-down` n'utilise pas `-v`.

## 9. Dépannage

| Symptôme | Cause | Correction |
| --- | --- | --- |
| Le site affiche des données fictives (catalogue de démonstration, API ignorée) | Le frontend a été construit avec `NEXT_PUBLIC_USE_MOCKS` différent de `0`, ou le bundle date d'avant le changement | Mettre `NEXT_PUBLIC_USE_MOCKS=0` dans `.env.prod`, puis `docker compose ... build frontend` et `make prod-up`. Un simple redémarrage ne change rien. |
| Erreur 413 à l'upload d'image | Limite de 4 Mo à l'edge (`request_body max_size 4MB` dans le Caddyfile). php-fpm accepte `upload_max_filesize=6M` / `post_max_size=8M`, mais Caddy refuse avant | Réduire/compresser l'image à moins de 4 Mo. Toute hausse de la limite exige de modifier la configuration de la stack, hors de ce runbook. |
| Pas de certificat HTTPS, erreur TLS, navigateur en avertissement | Challenge ACME échoué : DNS ne pointant pas encore vers l'hôte, ports 80/443 bloqués, ou `CADDY_DOMAIN` erroné | Vérifier `dig`, le pare-feu et `CADDY_DOMAIN` (hôte seul). Lire `make prod-logs SERVICE=caddy`. Corriger puis `docker compose ... restart caddy`. Garder `caddy_data` : des échecs répétés avec effacement du volume risquent d'atteindre les limites de débit Let's Encrypt. |
| Les commandes n'expirent pas, aucun rappel de retrait, notifications différées absentes | Worker arrêté ou en boucle d'erreurs | `make prod-status` (état du service `worker`), `make prod-logs SERVICE=worker`. Le redémarrer : `docker compose ... up -d worker`. Il s'arrête volontairement toutes les heures (`--time-limit=3600`) et est relancé par `restart: unless-stopped`. Vérifier les messages en échec via `GET /api/admin/ops/messenger`. |
| Tous les utilisateurs sont déconnectés / tous les tokens sont invalides après un déploiement | Le keypair JWT a été régénéré (ou le volume `backend_jwt` recréé), ou `JWT_PASSPHRASE` modifiée | Restaurer le volume `backend_jwt` depuis une sauvegarde et remettre la `JWT_PASSPHRASE` d'origine. Sans sauvegarde, la seule issue est un nouveau keypair : tous les utilisateurs doivent se reconnecter (JWT 1 h, pas de refresh token). |
| `make prod-config` / `up` : « X is required » | Variable obligatoire absente de `.env.prod` | La renseigner (message explicite fourni par Compose). |
| Le build frontend échoue sur `NEXT_PUBLIC_USE_MOCKS` | Valeur différente de `0`, garde-fou voulu | Mettre `0`. |
| Erreur à la migration sur `CREATE EXTENSION unaccent` | Rôle sans droit | Faire créer l'extension par un rôle autorisé, relancer `make prod-migrate`. |
| `ip_address` des audits ou limitation de débit voient une seule IP | `TRUSTED_PROXIES` vide ou ne couvre pas le réseau Docker | Garder la valeur par défaut du modèle (plages RFC1918) ou la faire couvrir le réseau Docker réel. |

## 10. Limites et points non vérifiés

### Vérifié

- `docker compose --env-file docs/ops/env.prod.example -f docker-compose.prod.yml config` : sortie 0,
  stderr vide.
- `caddy validate` sur le `Caddyfile` avec Caddy 2.10.0 : « Valid configuration », sortie 0 ;
  redirections HTTP vers HTTPS automatiques confirmées.
- `node --check apps/frontend/next.config.mjs` : OK.
- `bash -n` et `shellcheck -S style` sur les 4 scripts de `scripts/ops/` : 0 remarque.
- Rétention testée sur 41 faux dumps : `--dry-run` n'efface rien ; l'exécution réelle conserve
  10 dumps et leurs 10 sidecars.
- Les cibles Makefile `prod-*` s'expansent (`make -n`) et apparaissent dans `make help`.

### NON vérifié

- **Aucune image n'a été construite et aucun conteneur n'a été démarré** : il n'y a pas de démon
  Docker dans l'environnement de rédaction, seulement le client CLI. En conséquence ne sont pas
  vérifiés :
  - les noms de paquets Alpine des Dockerfiles ;
  - `pecl install redis` ;
  - le `cache:warmup` au build, qui peut échouer si une variable d'environnement requise manque
    au moment du build ;
  - la génération effective de `.next/standalone` ;
  - le démarrage de quoi que ce soit (services, healthchecks conteneur, montage des volumes,
    droits d'écriture sur `uploads_products`, émission du certificat ACME).
- `lint:container` et `doctrine:schema:validate` n'ont pas été lancés : `AGENTS.md` interdit les
  vérifications projet via `php`/`composer` de l'hôte, et il n'y a pas de démon Docker.
- Aucun chemin exigeant une vraie base PostgreSQL n'a été exercé (migrations, `unaccent`,
  restauration).
- Aucun exercice de restauration de sauvegarde n'a été réalisé.
- Les procédures de mise à jour et de rollback ci-dessus sont des procédures rédigées, jamais
  jouées sur une stack réelle.

### Limites connues

- **Drift de schéma** : la table `product_import_raw` et la colonne
  `product_references.source_import_raw_id` sont orphelines (zéro référence dans `src/` et
  `tests/`). À nettoyer avant la production, mais **hors** de la PR qui introduit ce runbook.
- **Swagger UI** `/api/docs.html` dégradé en mono-domaine (assets `/bundles/*` non exposés par
  l'edge). Les specs machine `/api/docs.json`, `/api/docs.jsonopenapi` et `/api/docs.yamlopenapi`
  fonctionnent.
- **Caddy épinglé** sur `caddy:2.10-alpine` : le `Caddyfile` utilise `log_skip` (Caddy 2.7+).
  Rejouer `caddy validate` avant de changer ce tag.
- **Base managée** : si le service `postgres` est supprimé, retirer aussi le volume
  `postgres_data` et les deux blocs `depends_on: postgres` (voir les commentaires du fichier
  Compose) ; cette variante n'a pas été exercée.
