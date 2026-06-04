# Runbook ops — Worker Messenger async

## Objectif

Valider et exploiter en production le worker async Symfony Messenger qui traite les jobs différés du MVP :

- rappel de retrait 1h avant le rendez-vous ;
- expiration du délai de réponse marchand ;
- rappel d'acceptation partielle ;
- expiration d'acceptation partielle.

La configuration applicative existe déjà. En Docker Compose, le worker est lancé dans un conteneur dédié `worker`. En déploiement Supervisor, les fichiers `docker/supervisor/` restent la référence. Ce runbook ne change pas le transport : il sert à vérifier que le worker est bien déployé, supervisé, redémarrable sans perte et que le `failure_transport` peut être inspecté puis rejoué.

## Références repo

- Transport Messenger : `apps/backend/config/packages/messenger.yaml`
- Service Docker Compose : `docker-compose.yml` (`worker`)
- Worker Supervisor : `docker/supervisor/messenger-worker.conf`
- Superviseur : `docker/supervisor/supervisord.conf`
- Table durable : `apps/backend/migrations/Version20260527100000.php`

## Configuration attendue

En production, `MESSENGER_TRANSPORT_DSN` doit pointer vers le transport Doctrine durable :

```dotenv
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
```

Le worker Docker Compose doit consommer la file `async` :

```bash
php bin/console messenger:consume async --time-limit=3600 --memory-limit=128M
```

Le worker supervisé doit consommer la même file depuis son répertoire applicatif :

```bash
php /var/www/html/bin/console messenger:consume async --time-limit=3600 --memory-limit=128M
```

Les messages en échec doivent être routés vers le transport `failed`.

## Validation initiale après déploiement

Exécuter les commandes depuis l'hôte de production ou dans le conteneur backend, selon la plateforme de déploiement.

### 1. Vérifier le conteneur Docker Compose

Si la production utilise Docker Compose :

```bash
docker compose ps worker
docker compose logs --tail=100 worker
```

Résultat attendu :

- le service `worker` est actif ;
- les logs ne montrent pas de boucle d'erreur ;
- la commande consommée est `messenger:consume async --time-limit=3600 --memory-limit=128M`.

### 2. Vérifier le process Supervisor

Si la production utilise Supervisor :

```bash
supervisorctl status messenger-worker
```

Résultat attendu :

```text
messenger-worker                 RUNNING
```

Si le process n'est pas `RUNNING`, consulter les logs :

```bash
tail -n 100 /var/log/supervisor/messenger-worker.err.log
tail -n 100 /var/log/supervisor/messenger-worker.out.log
tail -n 100 /var/log/supervisor/supervisord.log
```

### 3. Vérifier que la table durable existe

```bash
php /var/www/html/bin/console doctrine:query:sql "select count(*) from messenger_messages"
```

Résultat attendu :

- la commande retourne un compteur ;
- aucune erreur `relation does not exist`.

### 4. Vérifier les files Messenger

```bash
php /var/www/html/bin/console messenger:stats
```

Résultat attendu :

- la file `async` est visible ;
- la file `failed` est visible ;
- aucun volume anormalement croissant n'est observé sur `async`.

## Redémarrage worker sans perte

Le redémarrage doit être gracieux pour laisser Symfony terminer le message en cours quand c'est possible.

```bash
php /var/www/html/bin/console messenger:stop-workers
supervisorctl restart messenger-worker
supervisorctl status messenger-worker
```

Avec Docker Compose :

```bash
docker compose exec backend php bin/console messenger:stop-workers
docker compose restart worker
docker compose ps worker
```

Résultat attendu :

- le worker repasse à `RUNNING` ;
- les messages non traités restent en base dans `messenger_messages` ;
- les jobs différés reprennent après redémarrage.

## Test manuel de rejeu après arrêt

Ce test valide qu'un message non consommé n'est pas perdu lors d'un arrêt worker.

1. Arrêter le worker :

```bash
php /var/www/html/bin/console messenger:stop-workers
supervisorctl stop messenger-worker
```

Avec Docker Compose :

```bash
docker compose exec backend php bin/console messenger:stop-workers
docker compose stop worker
```

2. Déclencher dans l'application un événement qui planifie un job async connu, par exemple un rappel de retrait ou une expiration liée à une Kadhia soumise.

3. Vérifier que le message est durablement stocké :

```bash
php /var/www/html/bin/console messenger:stats
php /var/www/html/bin/console doctrine:query:sql "select queue_name, count(*) from messenger_messages group by queue_name"
```

4. Redémarrer le worker :

```bash
supervisorctl start messenger-worker
supervisorctl status messenger-worker
```

Avec Docker Compose :

```bash
docker compose start worker
docker compose ps worker
```

5. Vérifier que le message est consommé :

```bash
php /var/www/html/bin/console messenger:stats
tail -n 100 /var/log/supervisor/messenger-worker.out.log
```

Avec Docker Compose :

```bash
docker compose exec backend php bin/console messenger:stats
docker compose logs --tail=100 worker
```

Résultat attendu :

- le message apparaît pendant l'arrêt ;
- il est consommé après redémarrage ;
- aucune transition métier n'est perdue pour la Kadhia ou le retrait concerné.

## Monitoring de file

L'état de la file Messenger est exposé côté API admin :

```text
GET /api/admin/ops/messenger
```

L'accès est réservé aux administrateurs plateforme. La réponse contient :

- `status` : `ok` ou `degraded` ;
- `pending` : messages encore en attente sur `async` ;
- `failed` : messages présents dans le transport `failed` ;
- `oldest_age_s` : âge en secondes du plus vieux message `async` non consommé ;
- `last_consumed_at` : dernier message traité par le worker `async`, si disponible ;
- `thresholds` : seuils actifs pour basculer en `degraded`.

Seuils configurables :

```dotenv
MESSENGER_MONITOR_PENDING_THRESHOLD=100
MESSENGER_MONITOR_OLDEST_AGE_THRESHOLD_SECONDS=900
```

Le statut passe en `degraded` si la file dépasse le seuil de messages en attente, si le plus vieux message dépasse le seuil d'âge, ou si au moins un message est présent dans `failed`.

## Gestion des échecs

### Visualiser les messages échoués

```bash
php /var/www/html/bin/console messenger:failed:show
```

Pour inspecter un message précis :

```bash
php /var/www/html/bin/console messenger:failed:show <id>
```

### Rejouer un message échoué

```bash
php /var/www/html/bin/console messenger:failed:retry <id>
```

Pour rejouer tous les messages échoués, uniquement après diagnostic :

```bash
php /var/www/html/bin/console messenger:failed:retry
```

### Supprimer un message non rejouable

Utiliser cette commande uniquement si la cause est comprise et documentée.

```bash
php /var/www/html/bin/console messenger:failed:remove <id>
```

## Vérification de l'autorestart

Ce test valide le superviseur de process ou la politique de redémarrage Docker, pas le code applicatif.

### Docker Compose

```bash
docker compose kill worker
docker compose ps worker
docker compose logs --tail=100 worker
```

Résultat attendu :

- Docker relance le service `worker` grâce à `restart: unless-stopped` ;
- le service revient actif ;
- les logs ne montrent pas de boucle de crash.

### Supervisor

1. Identifier le PID :

```bash
supervisorctl pid messenger-worker
```

2. Terminer le process :

```bash
kill -TERM <pid>
```

3. Vérifier le redémarrage automatique :

```bash
supervisorctl status messenger-worker
tail -n 100 /var/log/supervisor/supervisord.log
```

Résultat attendu :

- Supervisor relance `messenger-worker` ;
- le statut revient à `RUNNING` ;
- les logs ne montrent pas de boucle de crash.

## Journal de validation production

Remplir ce journal à chaque validation de déploiement.

| Contrôle | Commande | Résultat attendu | Résultat observé | Date | Opérateur |
| --- | --- | --- | --- | --- | --- |
| Worker actif | `supervisorctl status messenger-worker` | `RUNNING` | À renseigner | À renseigner | À renseigner |
| Worker Docker actif | `docker compose ps worker` | service actif | À renseigner | À renseigner | À renseigner |
| Table durable | `doctrine:query:sql "select count(*) from messenger_messages"` | compteur sans erreur | À renseigner | À renseigner | À renseigner |
| Files visibles | `messenger:stats` | `async` et `failed` visibles | À renseigner | À renseigner | À renseigner |
| Redémarrage gracieux | `messenger:stop-workers` puis `supervisorctl restart messenger-worker` | worker `RUNNING`, pas de message perdu | À renseigner | À renseigner | À renseigner |
| Rejeu après arrêt | test manuel de message async | message consommé après redémarrage | À renseigner | À renseigner | À renseigner |
| Failed transport | `messenger:failed:show` puis `messenger:failed:retry <id>` | message inspectable et rejouable | À renseigner | À renseigner | À renseigner |
| Autorestart | `kill -TERM <pid>` | Supervisor relance le process | À renseigner | À renseigner | À renseigner |

## Signaux d'alerte

- `messenger-worker` reste en `STOPPED`, `FATAL` ou `BACKOFF`.
- le service Docker `worker` reste arrêté ou redémarre en boucle.
- `messenger_messages` grossit sans baisse sur la file `async`.
- `messenger:failed:show` liste des messages métiers récents non diagnostiqués.
- Les logs Supervisor affichent une boucle de redémarrage.
- Les clients ne reçoivent plus les rappels de retrait ou les expirations de Kadhia ne se déclenchent plus.

## Escalade

En cas d'incident :

1. Ne pas vider `messenger_messages` manuellement.
2. Capturer `supervisorctl status messenger-worker`, `messenger:stats` et les 100 dernières lignes de logs.
3. Inspecter les messages échoués avec `messenger:failed:show`.
4. Corriger la cause applicative ou de configuration.
5. Rejouer les messages avec `messenger:failed:retry`.
6. Documenter l'incident dans le journal de validation ou l'outil de suivi ops.
