# Sprint 7 — Production et localisation

## Objectif du sprint

Sprint 7 prépare le MVP pour une exploitation réelle : production, localisation, conformité minimale, support opérateur, accessibilité et PWA.

C'est le dernier sprint MVP identifié dans la roadmap actuelle.

## État actuel

- S7-001 — Fermeture définitive d'une supérette : **livré**.
  - `PATCH /api/admin/stores/{storeId}/archive`
  - Annulation automatique des commandes actives (submitted, accepted, partially_accepted, preparing, ready, pickup_pending)
  - Désactivation immédiate (QR révoqué via `active = false`)
  - 19 tests fonctionnels, PHPStan niveau 8 clean, CS Fixer clean
  - Migration `Version20260521100000` : colonnes `archived_at` et `archive_reason` sur `shops`
- S7-002 — Export CSV des commandes marchand : **livré**.
  - `GET /api/merchant/stores/{storeId}/orders/export.csv?date_from=&date_to=&status=`
  - `StreamedResponse`, séparateur `;`, RFC 4180, charset UTF-8
  - Paramètres `date_from`/`date_to` obligatoires, plage max 92 jours
  - 17 tests fonctionnels (accès, filtres, données, privacy, escaping), PHPStan niveau 8 clean, CS Fixer clean
  - Aucune migration — pas de nouveau champ Doctrine
- S7-003 — Conservation et suppression des données client : **livré côté backend**.
  - `DELETE /api/me/account`
  - Soft delete du `User` via `deletedAt`
  - Anonymisation minimale du compte client
  - Invalidation des `PasswordResetToken`
  - Blocage de connexion via `DeletedUserChecker`
  - `lastLoginAt` ajouté et alimenté après login JWT réussi
  - Commandes et lignes de commande conservées pour l'historique marchand
  - Politique documentée dans `docs/Sprint7/data-retention-policy.md`

- S7-004 — Audit trail des actions admin critiques : **livré côté backend**.
  - `GET /api/admin/audit-logs` (lecture seule, paginé, filtres `action` / `resource_type` / `resource_id`)
  - Entité `AdminAuditLog` append-only, service `AdminAuditLogger` injecté dans 9 processors
  - Actions loggées : `merchant.create`, `merchant.suspend`, `merchant.activate`, `store.activate`, `store.deactivate`, `store.qr_regenerate`, `store.archive`, `product_proposal.approve`, `product_proposal.reject`
  - `metadata` ne contient jamais password, token ni secret
  - 21 tests fonctionnels, PHPStan niveau 8 clean, CS Fixer clean
  - Migration `Version20260521120000` : table `admin_audit_logs` avec FK RESTRICT sur `users`

Sprint 7 en cours — ce document sera complété au fil des livraisons.

## Fonctionnalités prévues

- Localisation FR/AR/RTL.
- PWA installable et mode hors ligne.
- Accessibilité WCAG 2.1 AA.
- Conservation et suppression des données.
- Fermeture définitive d'une supérette.
- Export CSV des commandes marchand.
- Audit trail des actions admin.
- Observabilité production.
- Analytics MVP.
- Outils de support opérateur.
- Transport async Messenger persistant + supervision du worker en production.

## User stories concernées

| US | Sujet | Statut |
|---|---|---|
| US-008 | Basculer la langue de l'interface FR/AR | Indiqué complété dans la roadmap, à vérifier |
| US-058 | Fermeture définitive d'une supérette | Livré (S7-001) |
| US-059 | PWA installable et mode hors ligne | À faire |
| US-060 | Accessibilité WCAG 2.1 AA | À faire |
| US-061 | Export données commandes marchand CSV | Livré (S7-002) |
| US-062 | Politique de conservation et suppression des données | Livré backend S7-003 |
| US-063 | Audit trail des actions admin | Livré (S7-004) |
| US-066 | Garantir la fiabilité des automatisations différées en production via un transport Messenger persistant | À faire (S7-009) |

## Découpage recommandé

| Ticket | Sujet | Type |
|---|---|---|
| S7-001 | Fermeture définitive d'une supérette | Backend admin — Livré, PR #122 |
| S7-002 | Export CSV commandes marchand | Backend marchand — Livré |
| S7-003 | Conservation et suppression des données | Backend conformité — Livré |
| S7-004 | Audit trail admin | Backend admin — Livré |
| S7-005 | Observabilité production | Backend / infra |
| S7-006 | PWA installable et offline | Frontend / PWA |
| S7-007 | Accessibilité WCAG 2.1 AA | Frontend / qualité |
| S7-008 | Audit + clôture Sprint 7 | Documentation / audit |
| S7-009 | Transport async Messenger persistant + supervision worker | Backend / infra |

## Critères de sortie du sprint

Sprint 7 sera cohérent lorsque :

1. L'admin peut archiver définitivement une supérette sans supprimer l'historique.
2. Le marchand peut exporter ses commandes au format CSV.
3. Les règles minimales de conservation et suppression des données sont documentées et implémentées côté backend.
4. Les actions admin critiques sont tracées dans un audit trail.
5. Les prérequis production sont documentés : logs, workers Messenger, healthcheck, variables critiques.
6. La PWA est installable et dispose d'un comportement offline MVP.
7. Les principaux parcours frontend respectent les exigences d'accessibilité WCAG 2.1 AA au niveau MVP.
8. Les automatisations différées (rappels retrait, expirations commande) sont garanties par un transport Messenger async persistant et un worker supervisé, documentés et testés en staging.

## Entités / champs prévus par la roadmap

- `Shop.archivedAt`
- `Shop.archiveReason`
- `User.deletedAt`
- `User.lastLoginAt`
- `AdminAuditLog`

## Contraintes importantes

- Ne pas supprimer physiquement les données métier critiques.
- Ne pas exposer de données sensibles dans les exports ou logs.
- Ne pas cacher les routes protégées dans le service worker PWA.
- Ne pas ajouter de dépendances lourdes sans justification.
- Garder chaque PR petite et vérifiable.

## Hors périmètre MVP

- Paiement en ligne.
- Livraison.
- Application mobile native.
- Push mobile / SMS / email.
- BI avancée.
- Intégration comptable.
- Infrastructure observabilité externe obligatoire.

## Notes production

Les automatisations différées déjà livrées reposent sur Symfony Messenger avec `DelayStamp`.

En production, un transport async persistant et un worker supervisé sont nécessaires. Un transport `sync://` ne suffit pas pour garantir les rappels ou expirations différées.

## US-066 — Garantir la fiabilité des automatisations différées en production via un transport Messenger persistant

**Rôle** : opérateur plateforme.

**Besoin** : Je veux que les rappels de retrait (1h avant le créneau), les expirations de délai marchand et les expirations d'acceptation partielle s'exécutent de manière fiable en production, même si le serveur redémarre entre la planification et l'exécution.

**Bénéfice** : Aujourd'hui, ces automatisations reposent sur `DelayStamp` avec le transport par défaut (`sync://` ou en mémoire). Si le processus PHP s'arrête avant l'échéance, le message est perdu. En production, cela se traduit par des commandes bloquées en `submitted`, des clients non notifiés et des expirations jamais déclenchées.

**Préconditions** :
- Sprint 3b et Sprint 4 sont livrés (les messages Messenger existent déjà).
- L'infrastructure dispose de PostgreSQL ou Redis pour un transport persistant.

**Scénario nominal** :
1. L'opérateur configure un transport async persistant dans `messenger.yaml` (Doctrine DBAL ou Redis).
2. Les messages différés (`PickupReminderMessage`, `ExpireMerchantResponseMessage`, `ExpirePartialAcceptanceMessage`) sont acheminés vers ce transport.
3. Un worker Symfony (`messenger:consume`) est supervisé (Supervisor ou systemd).
4. En cas de redémarrage serveur, les messages en attente sont relus depuis le transport persistant et exécutés à leur échéance.
5. L'opérateur dispose d'une commande ou d'un endpoint de healthcheck pour vérifier que le worker tourne.

**Règles métier** :
- Aucun message différé ne doit être perdu en cas de redémarrage du serveur ou du processus PHP.
- Le délai configuré par `DelayStamp` doit être respecté à ±1 minute en production.
- Le worker doit être redémarré automatiquement par le superviseur en cas de crash.
- Les messages consommés avec erreur doivent être reroutés vers une file de messages en échec (`failure_transport`) et ne pas bloquer la file principale.

**Critères d'acceptation** :
- [ ] `messenger.yaml` configure un transport `doctrine://` ou `redis://` pour les messages différés.
- [ ] Les trois types de messages différés (`PickupReminder`, `ExpireMerchantResponse`, `ExpirePartialAcceptance`) utilisent ce transport.
- [ ] Un fichier de configuration Supervisor ou systemd est fourni dans `docker/` ou `docs/`.
- [ ] Un redémarrage du worker en cours de test ne perd aucun message planifié.
- [ ] Un `failure_transport` est configuré pour capturer les messages en échec.
- [ ] La documentation décrit les variables d'environnement requises (`MESSENGER_TRANSPORT_DSN`).

**Notes techniques** :
- Transport recommandé : `doctrine://default?auto_setup=0` (réutilise PostgreSQL déjà présent) ou `redis://localhost:6379/messages` si Redis est disponible.
- Ne pas utiliser `sync://` en production — les messages sont exécutés de façon synchrone dans la requête HTTP et les `DelayStamp` sont ignorés.
- Le worker doit tourner en continu : `php bin/console messenger:consume async --time-limit=3600` relancé par Supervisor.
- Ajouter un index sur la table `messenger_messages` (`(queue_name, available_at)`) pour éviter les scans complets à forte charge.
- Hors périmètre de cette US : push mobile, SMS, email — les notifications restent in-app.
