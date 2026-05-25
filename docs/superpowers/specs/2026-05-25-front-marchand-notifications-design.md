# Front marchand — notifications

Date : 2026-05-25

## Objectif

Livrer l'interface marchand de notifications in-app pour aider le marchand à réagir aux événements importants de sa supérette :

1. voir les notifications récentes ;
2. distinguer les notifications non lues ;
3. ouvrir la commande liée quand elle existe ;
4. marquer une notification comme lue ;
5. marquer toutes les notifications comme lues ;
6. voir un badge de notifications non lues dans la navigation marchand.

Cette tranche reste strictement MVP : pas de push, SMS, email, WebSocket, Mercure, paiement, livraison, fidélité ou panier marketplace multi-marchands.

## Périmètre

Inclus :

- route frontend `/merchant/notifications` ;
- entrée "Notifications" active dans `MerchantShell` ;
- badge de notifications non lues dans la navigation marchand ;
- lecture paginée des notifications marchand ;
- filtre "Toutes" / "Non lues" ;
- bouton "Actualiser" ;
- action unitaire "Marquer comme lu" ;
- action globale "Tout marquer comme lu" ;
- lien vers `/merchant/commandes/{order_id}` quand une notification est liée à une commande ;
- états chargement, vide et erreur ;
- tests Vitest ciblés sur services, page et shell.

Exclus :

- polling automatique ;
- panneau dropdown dans le header ;
- notifications temps réel ;
- préférences de notification ;
- suppression ou archivage de notifications ;
- modification backend ;
- test backend.

## Contrat API existant

Les endpoints backend sont déjà livrés côté Sprint 4 :

```http
GET   /api/merchant/notifications?page=1&unread=true
PATCH /api/merchant/notifications/{id}/read
PATCH /api/merchant/notifications/read-all
```

La réponse liste contient :

- `items` ;
- `total` ;
- `page`.

Elle ne contient pas `unread_count`. Le badge utilise donc le `total` retourné par `GET /api/merchant/notifications?unread=true`.

Chaque item contient :

- `id` ;
- `order_id` nullable ;
- `title_fr` ;
- `title_ar` ;
- `body_fr` ;
- `body_ar` ;
- `is_read` ;
- `created_at`.

Le front marchand consomme les champs français dans cette tranche. Les champs arabes restent typés pour une future étape i18n/RTL.

## Architecture frontend

### Route

Ajouter `apps/frontend/src/app/merchant/notifications/page.tsx`.

La page est un composant client dans le layout marchand existant. Elle conserve un état local :

- `items` ;
- `total` ;
- `page` ;
- `filter` avec `all` ou `unread` ;
- `isLoading` ;
- `isMutating` ;
- `error`.

La pagination reste simple : page courante, bouton précédent et bouton suivant si `total` dépasse le nombre d'items affichés. Le backend utilise une taille de page implicite de 20 notifications.

### Navigation

Mettre à jour `apps/frontend/src/components/merchant/MerchantShell.tsx` :

- ajouter une entrée active "Notifications" vers `/merchant/notifications` ;
- afficher un badge sur cette entrée si le compteur non lu est supérieur à zéro ;
- charger le compteur au montage du shell avec `GET /api/merchant/notifications?unread=true` ;
- masquer le badge en cas d'erreur de chargement, sans bloquer le backoffice.

Le shell ne fait pas de polling. Il met à jour le badge au chargement initial et quand la page notifications déclenche un rafraîchissement explicite après une action.

Le rafraîchissement inter-composants utilise un événement navigateur interne nommé `merchant-notifications:refresh`. `MerchantShell` écoute cet événement et relance uniquement le chargement du compteur non lu. Cette solution évite d'introduire un nouveau contexte global pour une seule interaction.

### Services

Créer `apps/frontend/src/lib/services/merchant-notifications.service.ts` avec :

- `listMerchantNotifications(options)` ;
- `markMerchantNotificationRead(notificationId)` ;
- `markAllMerchantNotificationsRead()`.

`listMerchantNotifications` accepte :

- `page?: number` ;
- `unread?: boolean`.

Les mutations `PATCH` envoient un corps `{}` pour rester cohérentes avec les endpoints Symfony/API Platform utilisant `input: false`.

### Types

Étendre `apps/frontend/src/lib/types/merchant.types.ts` avec :

- `MerchantNotificationItem` ;
- `MerchantNotificationList` ;
- `MerchantNotificationListOptions` ;
- `MerchantNotificationReadResult`.

Les clés restent en `snake_case`, comme les réponses backend.

## Comportement UI

### État initial

La page affiche :

- titre "Notifications" ;
- bouton "Actualiser" ;
- filtre "Toutes" / "Non lues" ;
- liste des notifications ou état vide.

Le filtre "Toutes" charge `/api/merchant/notifications?page=N`. Le filtre "Non lues" charge `/api/merchant/notifications?page=N&unread=true`.

### Notification

Chaque notification affiche :

- un indicateur visuel si elle est non lue ;
- titre français ;
- corps français ;
- date de création formatée ;
- lien "Voir la commande" si `order_id` est présent ;
- bouton "Marquer comme lu" si `is_read` vaut `false`.

Le lien commande pointe vers :

```text
/merchant/commandes/{order_id}
```

Si la commande n'est plus accessible, la page de détail existante gère l'erreur. La notification reste affichée.

### Marquage lu

Après un marquage unitaire réussi :

1. la page recharge la liste courante ;
2. la page émet `merchant-notifications:refresh` ;
3. le badge de navigation est recalculé par `MerchantShell`.

Après "Tout marquer comme lu" :

1. la page recharge la liste courante ;
2. si le filtre actif est "Non lues", l'état vide "Aucune notification non lue" est affiché ;
3. la page émet `merchant-notifications:refresh` ;
4. le badge disparaît après recalcul par `MerchantShell`.

L'action "Tout marquer comme lu" est visible si la page contient au moins une notification non lue ou si le filtre actif est "Non lues" avec `total > 0`.

### Actualisation manuelle

Le bouton "Actualiser" recharge la liste courante puis émet `merchant-notifications:refresh` pour recalculer le badge. Il remplace le polling automatique dans cette tranche.

## Gestion des erreurs

La page extrait `response.data.detail` quand disponible. Sinon elle affiche un message générique :

> "Les notifications n'ont pas pu être chargées. Réessaie dans un instant."

Une erreur de marquage lu ne retire pas la notification de la liste. La page affiche un message local et conserve l'état courant.

Une erreur de badge dans `MerchantShell` ne bloque pas le backoffice et n'affiche pas d'alerte globale.

## Tests

Ajouter ou mettre à jour :

- `apps/frontend/src/tests/merchant.notifications.service.test.ts` :
  - liste toutes les notifications au bon endpoint ;
  - liste les notifications non lues avec `unread=true` ;
  - transmet la page demandée ;
  - marque une notification comme lue avec `PATCH` et `{}` ;
  - marque toutes les notifications comme lues avec `PATCH` et `{}`.
- `apps/frontend/src/tests/merchant.notifications.test.tsx` :
  - état de chargement puis liste ;
  - état vide global ;
  - état vide non lues ;
  - filtre "Non lues" appelle le service avec `unread=true` ;
  - lien commande pointe vers `/merchant/commandes/{order_id}` ;
  - marquage unitaire recharge la liste ;
  - marquage global recharge la liste et le badge ;
  - erreur de chargement affiche un message et permet de réessayer.
- `apps/frontend/src/tests/merchant.shell.test.tsx` :
  - entrée "Notifications" active ;
  - badge affiché quand `GET unread` retourne `total > 0` ;
  - badge absent quand `total` vaut zéro ;
  - erreur de compteur ne bloque pas le rendu du shell.

Vérifications attendues :

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.notifications.service.test.ts src/tests/merchant.notifications.test.tsx src/tests/merchant.shell.test.tsx
npx tsc --noEmit
npm run lint
npm run build
```

## Risques

- Le badge repose sur un appel liste filtré `unread=true` car le backend ne fournit pas `unread_count`.
- Sans polling, le badge ne se met pas à jour automatiquement si une nouvelle commande arrive pendant que le marchand reste sur une autre page.
- Les champs arabes sont typés mais non utilisés dans cette tranche ; l'interface i18n/RTL reste une étape séparée.
- Le backend ne fournit pas de taille de page dans la réponse ; le front déduit la pagination à partir de `total`, `page` et la taille implicite de 20.
