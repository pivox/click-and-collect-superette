# Design — Frontend marchand détail commande et actions jusqu'à ready

**Date** : 2026-05-24
**Scope** : espace marchand frontend, détail commande, traitement marchand jusqu'au statut `ready`
**Approche retenue** : détail commande comme centre d'action

---

## Objectif

Étendre les fondations frontend marchand livrées par la PR #134 avec un écran de travail permettant au marchand de traiter une Kadhia depuis une commande `submitted` jusqu'à une commande `ready`.

La PR doit permettre au marchand de consulter le détail d'une commande active, d'accepter, refuser, accepter partiellement, démarrer la préparation, marquer les lignes préparées puis déclarer la commande prête pour le retrait.

Le retrait sécurisé reste explicitement hors périmètre de cette PR.

---

## Inclus

- Lien depuis `/merchant/commandes` vers un détail commande.
- Nouvelle route `/merchant/commandes/[orderId]`.
- Chargement du détail via `GET /api/merchant/stores/{storeId}/orders/{orderId}`.
- Actions marchand :
  - accepter une commande `submitted` ;
  - refuser une commande `submitted` avec motif ;
  - accepter partiellement une commande `submitted` ;
  - démarrer la préparation d'une commande `accepted` ;
  - marquer une ligne comme préparée pendant `preparing` ;
  - marquer une commande `preparing` comme `ready`.
- Rechargement du détail après chaque action.
- États de chargement, erreurs API, conflits de statut et validations frontend minimales.
- Tests frontend ciblés sur les services, la liste, le détail et les règles d'affichage des actions.

---

## Exclusions explicites

- Scan QR de retrait.
- Passage en `pickup_pending`.
- Confirmation marchand de retrait.
- Confirmation client.
- Force completion.
- Historique complet des commandes.
- Catalogue marchand.
- Créneaux.
- QR code supérette téléchargeable.
- Onboarding marchand.
- Thème de supérette.
- Paiement en ligne.
- Livraison.
- Fidélité.
- Marketplace multi-marchands ou Kadhia partagée entre plusieurs marchands.

---

## Routes frontend

### `/merchant/commandes`

La liste reste une vue opérationnelle compacte.

Chaque commande active affiche les informations déjà disponibles : numéro ou id, statut, nombre de lignes, rendez-vous de retrait et total en TND.

Chaque ligne devient cliquable vers :

```text
/merchant/commandes/{orderId}
```

Aucune action métier n'est ajoutée dans la liste. Les actions restent dans le détail pour éviter les erreurs sur mobile et préserver la lisibilité.

### `/merchant/commandes/[orderId]`

Le détail devient l'écran de travail principal du marchand.

Il utilise le `storeId` du `MerchantAuthContext`, puis charge la commande avec :

```http
GET /api/merchant/stores/{storeId}/orders/{orderId}
```

Si le backend expose déjà l'historique de statut via :

```http
GET /api/merchant/stores/{storeId}/orders/{orderId}/status-history
```

la page peut l'afficher en lecture seule, sans en faire une condition de sortie.

---

## Données affichées

Le haut de page affiche :

- numéro de commande lisible si disponible, sinon id ;
- statut actuel ;
- total en TND ;
- rendez-vous de retrait ;
- coordonnées client si le payload détail les expose ;
- notes client si présentes.

La section **Kadhia** affiche chaque ligne :

- nom produit ;
- quantité ;
- prix unitaire ;
- total ligne ;
- état de préparation si disponible ;
- contrôle de préparation uniquement quand la commande est en `preparing`.

Le vocabulaire visible conserve les termes métier : Kadhia, marchand, client, rendez-vous, retrait.

---

## Actions par statut

### `submitted`

Actions visibles :

- accepter ;
- refuser ;
- accepter partiellement.

L'acceptation appelle :

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/accept
```

Le refus ouvre une confirmation avec motif obligatoire, puis appelle :

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/reject
```

L'acceptation partielle ouvre une modale de sélection des lignes acceptées.

### `accepted`

Action visible :

- démarrer la préparation.

Elle appelle :

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/start-preparation
```

### `partially_accepted`

Aucune action de préparation n'est visible.

La page affiche un état expliquant que le client doit ajuster sa Kadhia et la re-soumettre avant que le marchand puisse continuer.

### `preparing`

Actions visibles :

- marquer les lignes préparées ;
- marquer la commande prête.

La préparation d'une ligne appelle :

```http
PATCH /api/merchant/stores/{storeId}/orders/{orderId}/lines/{merchantProductId}/preparation
```

Le passage en prête appelle :

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/mark-ready
```

Le bouton **Commande prête** est visible uniquement en `preparing`. Le frontend peut avertir si aucune ligne n'est marquée préparée, mais le backend reste l'autorité métier finale.

### `ready`

La page affiche un état final pour cette PR :

```text
Commande prête pour le retrait.
```

Aucune action de retrait n'est proposée.

---

## Acceptation partielle

La modale d'acceptation partielle affiche toutes les lignes de Kadhia avec un choix accepté / non disponible.

Règles frontend minimales :

- au moins une ligne doit rester acceptée ;
- au moins une ligne doit être marquée non disponible ;
- un motif court est demandé si le contrat backend l'exige ou si une ligne est refusée ;
- le bouton de validation est désactivé pendant l'appel API.

La mutation appelle :

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/partially-accept
```

Le payload exact doit suivre le contrat backend existant. Si le contrat local diverge de la documentation, l'implémentation frontend doit s'aligner sur les DTOs et tests backend présents dans `apps/backend`.

---

## Services frontend

Créer ou étendre les services marchand :

- `getMerchantOrder(storeId, orderId)`;
- `getMerchantOrderStatusHistory(storeId, orderId)` si utilisé ;
- `acceptMerchantOrder(storeId, orderId)`;
- `rejectMerchantOrder(storeId, orderId, payload)`;
- `partiallyAcceptMerchantOrder(storeId, orderId, payload)`;
- `startMerchantOrderPreparation(storeId, orderId)`;
- `setMerchantOrderLinePrepared(storeId, orderId, merchantProductId, payload)`;
- `markMerchantOrderReady(storeId, orderId)`.

Ces fonctions restent dans le périmètre `apps/frontend/src/lib/services/merchant-orders.service.ts` ou dans un fichier marchand voisin si le fichier devient trop chargé.

Les types associés restent dans `apps/frontend/src/lib/types/merchant.types.ts`, sauf si leur taille justifie un découpage explicite.

---

## États et erreurs

La page couvre :

- chargement initial ;
- commande introuvable ou non rattachée à la supérette du marchand ;
- session expirée avec redirection existante vers `/merchant/login` ;
- erreur API générique avec bouton **Réessayer** ;
- statut déjà changé entre le chargement et l'action ;
- erreur de validation backend sur refus ou acceptation partielle ;
- état rare de commande sans lignes ;
- mutation en cours avec boutons désactivés.

Après chaque mutation réussie, la page recharge le détail depuis le backend.

Pour les réponses `409` ou `422`, le message backend est affiché si exploitable. Sinon, la page affiche un message métier court et propose de recharger.

---

## Tests

### Frontend

Tests recommandés :

- le service détail commande appelle `/api/merchant/stores/{storeId}/orders/{orderId}` ;
- les services de mutation appellent les bons endpoints ;
- `/merchant/commandes` rend un lien vers `/merchant/commandes/{orderId}` ;
- le détail affiche les actions `submitted` ;
- le détail affiche **Démarrer préparation** uniquement en `accepted` ;
- le détail affiche les contrôles de ligne et **Commande prête** uniquement en `preparing` ;
- le détail n'affiche aucune action de retrait en `ready` ;
- l'acceptation partielle impose au moins une ligne acceptée et une ligne non disponible ;
- une erreur API affiche un message et laisse l'utilisateur réessayer.

### Backend

Pas de changement backend prévu dans cette PR.

Si un contrat nécessaire manque ou diverge, le changement backend doit rester minimal, explicite, couvert par tests fonctionnels, et documenté dans la PR.

---

## Risques et décisions

- Le détail commande peut dépendre d'un payload backend plus riche que la liste. Il faut inspecter les DTOs/providers existants avant d'implémenter l'UI.
- L'acceptation partielle est plus risquée que les autres actions, car elle dépend du format exact des lignes acceptées/refusées. Elle doit être testée au niveau service.
- Le frontend ne doit pas inventer de règles métier concurrentes : le backend reste l'autorité sur les transitions de statut.
- La PR doit rester limitée au traitement jusqu'à `ready` pour éviter de mélanger préparation et retrait sécurisé.

---

## Critère de sortie

Un marchand connecté peut ouvrir une commande active, traiter une Kadhia depuis `submitted` jusqu'à `ready`, voir les erreurs métier utiles, et revenir à une liste de commandes qui pointe vers le détail.

Le scan QR et la validation du retrait restent absents de l'interface.
