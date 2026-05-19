# Sprint 7 — Production et localisation

## Objectif du sprint

Sprint 7 prépare le MVP pour une exploitation réelle : production, localisation, conformité minimale, support opérateur, accessibilité et PWA.

C'est le dernier sprint MVP identifié dans la roadmap actuelle.

## État actuel

Sprint 7 n'est pas encore démarré côté documentation dédiée.

Ce document initialise le dossier `docs/Sprint7/` afin de cadrer les prochains lots sans mélanger production, conformité, PWA et fonctionnalités admin.

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

## User stories concernées

| US | Sujet | Statut |
|---|---|---|
| US-008 | Basculer la langue de l'interface FR/AR | Indiqué complété dans la roadmap, à vérifier |
| US-058 | Fermeture définitive d'une supérette | À faire |
| US-059 | PWA installable et mode hors ligne | À faire |
| US-060 | Accessibilité WCAG 2.1 AA | À faire |
| US-061 | Export données commandes marchand CSV | À faire |
| US-062 | Politique de conservation et suppression des données | À faire |
| US-063 | Audit trail des actions admin | À faire |

## Découpage recommandé

| Ticket | Sujet | Type |
|---|---|---|
| S7-001 | Fermeture définitive d'une supérette | Backend admin |
| S7-002 | Export CSV commandes marchand | Backend marchand |
| S7-003 | Conservation et suppression des données | Backend conformité |
| S7-004 | Audit trail admin | Backend admin |
| S7-005 | Observabilité production | Backend / infra |
| S7-006 | PWA installable et offline | Frontend / PWA |
| S7-007 | Accessibilité WCAG 2.1 AA | Frontend / qualité |
| S7-008 | Audit + clôture Sprint 7 | Documentation / audit |

## Critères de sortie du sprint

Sprint 7 sera cohérent lorsque :

1. L'admin peut archiver définitivement une supérette sans supprimer l'historique.
2. Le marchand peut exporter ses commandes au format CSV.
3. Les règles minimales de conservation et suppression des données sont documentées et implémentées côté backend.
4. Les actions admin critiques sont tracées dans un audit trail.
5. Les prérequis production sont documentés : logs, workers Messenger, healthcheck, variables critiques.
6. La PWA est installable et dispose d'un comportement offline MVP.
7. Les principaux parcours frontend respectent les exigences d'accessibilité WCAG 2.1 AA au niveau MVP.

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
