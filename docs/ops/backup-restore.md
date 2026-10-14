# Sauvegarde et restauration — runbook production

Ce document décrit comment sauvegarder, restaurer et **vérifier** les deux seuls actifs
persistants de l'application :

1. la base PostgreSQL 16 (commandes, Kadhias, marchands, supérettes, notifications…) ;
2. le répertoire d'images produit `apps/backend/public/uploads/products`
   (paramètre `app.product_image.upload_dir`), monté en volume Docker nommé en production.

Outillage : `scripts/ops/` (scripts Bash, messages en anglais).

| Script | Rôle |
| --- | --- |
| `backup-postgres.sh` | `pg_dump -Fc`, vérification, chiffrement `age` |
| `backup-uploads.sh` | archive complète des uploads, chiffrée `age` |
| `restore-postgres.sh` | restauration (destructive, confirmation explicite, `--dry-run`) |
| `prune-backups.sh` | rétention quotidienne / hebdomadaire / mensuelle |

## Ce qu'une sauvegarde est, et n'est pas

- Un **snapshot de VPS n'est PAS une sauvegarde applicative.** Il est pris sur le même
  fournisseur, souvent dans le même datacenter et le même compte ; une base copiée en
  plein écriture n'est pas garantie cohérente ; il ne permet pas de restaurer une seule
  table ni de revenir sur une base saine seule ; et il disparaît avec le compte ou la
  machine. Gardez-le comme filet de sécurité supplémentaire, jamais comme seule protection.
- Une sauvegarde qui reste **sur le serveur** ne protège pas d'une perte du serveur.
  La copie **hors-site** (object storage S3-compatible, autre fournisseur ou au minimum
  autre compte/région) est obligatoire (voir plus bas).
- Une sauvegarde **jamais restaurée n'est pas une sauvegarde.** Voir la section
  « Vérifier que la sauvegarde fonctionne ».

## Choix du chiffrement : `age`

`age` plutôt que `gpg` :

- chiffrement asymétrique : le serveur ne détient que la **clé publique** ; si le serveur
  est compromis, l'attaquant ne peut pas déchiffrer les anciennes sauvegardes ;
- pas de trousseau, pas d'agent, pas de base de confiance : un fichier de clé et une
  commande, donc moins de pannes silencieuses en cron ;
- chiffrement en flux (rapide sur des archives de plusieurs Go), format simple et stable ;
- `gpg` reste valable mais son modèle (trousseau, expiration de clés, `gpg-agent`) est
  une source classique de sauvegardes qui échouent sans bruit.

Mise en place (une fois, **sur un poste de confiance, pas sur le serveur**) :

```bash
age-keygen -o clickcollect-backup.key
# affiche : Public key: age1xxxxxxxx...
```

- La **clé publique** (`age1...`) va dans `BACKUP_AGE_RECIPIENT` sur le serveur.
- La **clé privée** (`clickcollect-backup.key`) est stockée hors du serveur : gestionnaire
  de mots de passe **et** copie hors-ligne chez un second responsable. **Sans elle, toutes
  les sauvegardes sont illisibles.** Prévoyez deux destinataires (`BACKUP_AGE_RECIPIENTS_FILE`,
  un destinataire par ligne) pour qu'une seule perte de clé ne soit pas fatale.
- Le plaintext n'est jamais conservé : `backup-postgres.sh` écrit un dump transitoire dans
  un répertoire `700` (pour le vérifier) puis le supprime à la sortie, y compris en cas d'erreur.

## Nommage

```
<BACKUP_PREFIX>-postgres-<AAAAMMJJ>T<HHMMSS>Z.dump.age   (+ .sha256)
<BACKUP_PREFIX>-uploads-<AAAAMMJJ>T<HHMMSS>Z.tar.age     (+ .sha256)
```

Horodatage en **UTC**. `BACKUP_PREFIX` vaut `clickcollect` par défaut. Le fichier `.sha256`
permet de détecter une corruption ou une troncature survenue pendant un transfert.

## Variables d'environnement

| Variable | Scripts | Obligatoire | Rôle |
| --- | --- | --- | --- |
| `BACKUP_DIR` | backup-*, prune | oui | répertoire des sauvegardes (créé en `700`) |
| `BACKUP_AGE_RECIPIENT` ou `BACKUP_AGE_RECIPIENTS_FILE` | backup-* | oui (l'un des deux) | clé publique age / fichier de destinataires |
| `DATABASE_URL` | backup-postgres | oui\* | DSN `postgresql://user:pass@host:5432/db?serverVersion=16&charset=utf8` (\*ou `PGHOST`, `PGUSER`, `PGDATABASE` + `PGPASSWORD`/`~/.pgpass`) |
| `BACKUP_UPLOADS_DIR` | backup-uploads | oui | point de montage du volume d'uploads |
| `RESTORE_DATABASE_URL` | restore | oui | base **cible** (jamais `DATABASE_URL`, voir sécurité) |
| `RESTORE_AGE_IDENTITY_FILE` | restore | oui | clé privée age pour déchiffrer |
| `RESTORE_CONFIRM_DB` | restore `--force` | avec `--force` | nom de la base cible, saisi explicitement |
| `BACKUP_PREFIX` | tous | non | préfixe (défaut `clickcollect`) |
| `BACKUP_TMP_DIR` / `RESTORE_TMP_DIR` | backup-postgres / restore | non | scratch du dump en clair |
| `BACKUP_EXCLUDE_MESSENGER` | backup-postgres | non | `1` = exclure les lignes de `messenger_messages` (défaut `0`) |
| `BACKUP_VERIFY_FULL` | backup-postgres | non | `1` = relire toute l'archive (défaut `1`) |
| `BACKUP_MIN_FILES` | backup-uploads | non | refuse d'archiver moins de N fichiers (défaut 1) |
| `KEEP_DAILY` / `KEEP_WEEKLY` / `KEEP_MONTHLY` | prune | non | rétention (défaut 7 / 4 / 6) |

Aucun secret n'est codé en dur et aucun mot de passe n'est passé en argument : le mot de
passe de `DATABASE_URL` est exporté en `PGPASSWORD` dans l'environnement du processus
(invisible dans `ps`). Placez ces variables dans un fichier `600` appartenant à l'utilisateur
qui exécute les sauvegardes, par exemple `/etc/clickcollect/backup.env` (non versionné).

```bash
# /etc/clickcollect/backup.env  (chmod 600)
BACKUP_DIR=/var/backups/clickcollect
BACKUP_AGE_RECIPIENT=age1...
DATABASE_URL='postgresql://app:***@db-host:5432/clickcollect?serverVersion=16&charset=utf8'
BACKUP_UPLOADS_DIR=/var/lib/docker/volumes/<volume-uploads>/_data
```

> Les scripts nécessitent `pg_dump`/`pg_restore`/`psql` **16 ou plus**, `age`, GNU `tar`,
> `sha256sum`, `flock` et GNU `date`. Si le client PostgreSQL n'est pas installé sur
> l'hôte, exécutez-les depuis un conteneur `postgres:16` relié au réseau de la base.

## Planification

Ordre de grandeur (images : original ≤ 2 Mo + 4 variantes WebP + fallback JPEG, soit
1,5 à 2,5 Mo par image) : **5 000 images ≈ 8 à 12 Go**. Les images sont déjà compressées,
l'archive n'est donc pas gzippée et ne rétrécit pas.

Chaque sauvegarde d'uploads est une archive **complète** (choix MVP : une restauration
n'a besoin que d'un fichier, pas d'une chaîne fragile). Avec la rétention par défaut,
prévoyez jusqu'à 17 copies : ≈ 140 à 200 Go pour 5 000 images, en local **et** hors-site.
Si le volume dépasse ce que le disque ou le budget permet, passez les uploads en
hebdomadaire (les images sont écrites une fois et jamais modifiées) ou étudiez un outil
dédupliquant (restic/borg) : à traiter comme évolution, non livré ici.

Cohérence : la base référence les images. Lancez les deux sauvegardes à quelques minutes
d'intervalle, base en premier.

### Cron (utilisateur `backup`)

```cron
# m h dom mon dow  command
15 3 * * *  set -a; . /etc/clickcollect/backup.env; set +a; /opt/clickcollect/scripts/ops/backup-postgres.sh >>/var/log/clickcollect-backup.log 2>&1
30 3 * * *  set -a; . /etc/clickcollect/backup.env; set +a; /opt/clickcollect/scripts/ops/backup-uploads.sh  >>/var/log/clickcollect-backup.log 2>&1
45 3 * * *  set -a; . /etc/clickcollect/backup.env; set +a; rclone copy "$BACKUP_DIR" offsite:clickcollect-backups --include "*.age" --include "*.sha256" >>/var/log/clickcollect-backup.log 2>&1
15 4 * * *  set -a; . /etc/clickcollect/backup.env; set +a; /opt/clickcollect/scripts/ops/prune-backups.sh >>/var/log/clickcollect-backup.log 2>&1
```

Les lignes ci-dessus utilisent `set -a` pour exporter le contenu du fichier d'environnement ;
le fichier lui-même reste de simples `VAR=valeur`. Adaptez à votre variante de cron.

### Alternative : timer systemd

`/etc/systemd/system/clickcollect-backup.service` :

```ini
[Unit]
Description=Click & Collect backup (PostgreSQL + uploads)

[Service]
Type=oneshot
User=backup
EnvironmentFile=/etc/clickcollect/backup.env
ExecStart=/opt/clickcollect/scripts/ops/backup-postgres.sh
ExecStart=/opt/clickcollect/scripts/ops/backup-uploads.sh
ExecStart=/opt/clickcollect/scripts/ops/prune-backups.sh
```

`/etc/systemd/system/clickcollect-backup.timer` :

```ini
[Unit]
Description=Daily Click & Collect backup

[Timer]
OnCalendar=*-*-* 03:15:00 UTC
Persistent=true
RandomizedDelaySec=300

[Install]
WantedBy=timers.target
```

Activation : `systemctl enable --now clickcollect-backup.timer`. Avantage sur cron :
un échec marque l'unité en erreur (`systemctl --failed`, `OnFailure=` pour alerter).
Ajoutez la copie hors-site comme `ExecStart` supplémentaire.

## Copie hors-site (obligatoire)

La destination est un **object storage S3-compatible** (par exemple Scaleway, OVH,
Backblaze B2, Cloudflare R2 ou AWS S3), chez un fournisseur et un compte différents de
ceux du serveur. Recommandations :

- identifiants **dédiés** en écriture seule (pas de droit de suppression) pour que
  l'hôte compromis ne puisse pas effacer l'historique ;
- versioning ou *object lock* activé sur le bucket si disponible ;
- `rclone copy` (et non `sync`) : l'élagage local ne doit pas supprimer la copie distante ;
  la rétention distante se règle par règle de cycle de vie du bucket (≥ rétention locale) ;
- les fichiers sont déjà chiffrés : le fournisseur ne voit jamais de données en clair.

`prune-backups.sh` n'agit que sur le répertoire local.

## Traitement de `messenger_messages` (décision)

La table `messenger_messages` contient la file Symfony Messenger persistante : jobs en
attente avec leur délai (rappel de retrait 1 h, expiration du délai de réponse marchand,
rappels d'acceptation partielle…) et messages en échec (`failed`). Restaurer une file
ancienne risque de **rejouer des notifications** ou de déclencher des minuteries
périmées.

Décision :

- **la sauvegarde est complète par défaut** (la table et ses lignes sont dans le dump),
  parce qu'on ne peut pas recréer des données après coup ;
- **la restauration ignore par défaut les lignes** de `messenger_messages` (la table est
  recréée vide) et l'annonce dans le plan affiché ; `--with-messenger-queue` les restaure ;
- `BACKUP_EXCLUDE_MESSENGER=1` permet en plus de les exclure dès la sauvegarde (dump plus
  petit, mais la file est alors irrécupérable).

Conséquence de l'exclusion à la restauration : les minuteries planifiées avant l'incident
sont perdues. Après une restauration, examinez les commandes `submitted` et
`partially_accepted` dont le créneau approche ou est passé, et traitez-les à la main ou
via les commandes de rattrapage disponibles. Les rappels de retrait déjà dus sont perdus.

## Procédure de restauration pas à pas

> La restauration est **destructive** pour la base cible. Entraînez-vous d'abord sur une
> base de test (voir section suivante).

1. **Choisir la sauvegarde.** Récupérez le fichier voulu (local ou depuis le bucket)
   avec son `.sha256` dans le même répertoire.
2. **Préparer la clé.** Mettez la clé privée age sur la machine de restauration
   (droits `600`), supprimez-la ensuite.
3. **Arrêter les écritures.** Arrêtez le backend et le worker Messenger : des connexions
   ouvertes bloquent les `DROP` et de nouvelles écritures seraient perdues.
4. **Créer la base cible si elle n'existe pas** (rôle propriétaire = utilisateur applicatif) :
   `createdb -h HOST -U ADMIN -O app clickcollect`.
5. **Dry-run** : déchiffre, valide l'archive et affiche le plan sans rien modifier.

   ```bash
   export RESTORE_DATABASE_URL='postgresql://app:***@db-host:5432/clickcollect'
   export RESTORE_AGE_IDENTITY_FILE=/secure/clickcollect-backup.key
   ./scripts/ops/restore-postgres.sh --dry-run /var/backups/clickcollect/clickcollect-postgres-20261014T031500Z.dump.age
   ```

   Relisez : fichier, base cible, nombre de tables qui seront écrasées, présence de
   `unaccent`, traitement de la file Messenger.
6. **Restaurer.** Sans `--dry-run`, le script demande de **retaper le nom de la base**.
   En mode non interactif : `RESTORE_CONFIRM_DB=clickcollect ./restore-postgres.sh --force FICHIER`.
   La restauration est faite en une seule transaction : en cas d'erreur, la cible reste
   inchangée.
7. **Contrôles.** Le script vérifie que l'extension `unaccent` existe (créée par la
   migration `Version20260526100000` ; elle est incluse dans le dump et recréée par la
   restauration) et échoue avec la commande de correction sinon.
   Puis : `php bin/console doctrine:migrations:status` (via Docker Compose) pour comparer
   la version du schéma à celle du code déployé, et `doctrine:schema:validate`.
8. **Uploads** (si nécessaire), vers un répertoire vide ou le volume :

   ```bash
   sha256sum --check clickcollect-uploads-20261014T033000Z.tar.age.sha256
   age --decrypt -i /secure/clickcollect-backup.key clickcollect-uploads-20261014T033000Z.tar.age \
     | tar --extract --directory /chemin/vers/uploads/products
   ```
9. **Redémarrer** backend et worker, puis exécuter un parcours de contrôle (ouvrir une
   supérette, afficher un produit avec image, consulter une commande récente).
10. **Supprimer** la clé privée et les dumps déchiffrés de la machine de restauration.

Sécurité : la cible vient de `RESTORE_DATABASE_URL` et jamais de `DATABASE_URL` ; les
variables `PG*` héritées sont ignorées. Un shell qui a les identifiants de production ne
peut donc pas restaurer sur la production par réflexe : il faut y mettre explicitement
la cible.

## Vérifier que la sauvegarde fonctionne

Contrôles automatiques à chaque exécution de `backup-postgres.sh` : taille minimale du
dump, lecture de la table des matières, présence de données de tables, présence de
l'extension `unaccent`, **relecture complète de l'archive** (`pg_restore` vers `/dev/null`,
qui détecte une troncature), en-tête age et somme `sha256`. Ils prouvent que le fichier
est cohérent, pas que vous saurez le restaurer. D'où :

### 1. Surveiller les sauvegardes (quotidien)

- le journal ne contient aucune ligne `ERROR` ; une alerte part si la tâche échoue
  (`OnFailure=` systemd, ou un service type healthchecks.io appelé en fin de script) ;
- le plus récent fichier a moins de 30 h (`prune-backups.sh` avertit sinon) ;
- la taille ne s'écroule pas par rapport à la veille ;
- le bucket hors-site contient bien le même fichier que le local.

### 2. Exercice de restauration (au moins mensuel, et avant la mise en production)

Sur une machine **jetable**, avec une base PostgreSQL 16 vide qui n'est pas la production :

```bash
createdb scratch_restore
export RESTORE_DATABASE_URL='postgresql://postgres:***@localhost:5432/scratch_restore'
export RESTORE_AGE_IDENTITY_FILE=/secure/clickcollect-backup.key
./scripts/ops/restore-postgres.sh --dry-run FICHIER.dump.age
./scripts/ops/restore-postgres.sh FICHIER.dump.age       # tapez : scratch_restore
psql "$RESTORE_DATABASE_URL" -c "SELECT extname FROM pg_extension WHERE extname='unaccent';"
psql "$RESTORE_DATABASE_URL" -c "SELECT count(*) FROM \"order\";"   # adaptez au nom de table réel
```

Comparez les compteurs (commandes, utilisateurs, supérettes, produits) avec la production.
Faites aussi démarrer le backend contre cette base et ouvrez un écran réel. Pour les
uploads, extrayez l'archive dans un répertoire temporaire et vérifiez qu'une image
connue et ses variantes WebP s'ouvrent. Notez la **durée totale** : c'est votre RTO réel.
Consignez la date et le résultat de chaque exercice.

### 3. Tester la rétention sans risque

`./scripts/ops/prune-backups.sh --dry-run` liste ce qui est conservé (`KEEP`, avec la raison)
et ce qui serait supprimé (`DELETE`).

## Rétention proposée

| Niveau | Défaut | Règle |
| --- | --- | --- |
| Quotidien | 7 | la plus récente de chacun des 7 derniers jours avec sauvegarde |
| Hebdomadaire | 4 | la plus récente de chacune des 4 dernières semaines ISO |
| Mensuel | 6 | la plus récente de chacun des 6 derniers mois |

L'union des trois règles est conservée ; la sauvegarde la plus récente d'un type est
toujours conservée. Chaque suppression est affichée avant d'être faite. Seuls les fichiers
au nom strictement conforme (et leurs `.sha256`, ainsi que les `.partial` périmés) sont
touchés. RPO : jusqu'à 24 h de données avec une sauvegarde quotidienne ; si c'est trop
pour la production, planifiez `backup-postgres.sh` plus souvent (par exemple toutes les
6 h) et ajustez la rétention en conséquence.

## Limites connues

- Pas d'archivage WAL / restauration à un instant précis (PITR) : la granularité est celle
  de la fréquence des sauvegardes.
- Les uploads sont en archive complète (pas d'incrémental) ; voir la section Planification.
- Le dump transitoire en clair demande de l'espace disque dans `BACKUP_TMP_DIR` (≈ taille
  de la base) pendant l'exécution.
- Les scripts n'ont pas été exécutés contre une vraie base lors de leur livraison : seul
  l'exercice de restauration ci-dessus en apporte la preuve. Il doit être fait avant de
  cocher « Sauvegarde automatique configurée » dans `docs/production/readiness-checklist.md`.
