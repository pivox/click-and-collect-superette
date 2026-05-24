# Front marchand — Historique commandes marchand

Date : 2026-05-24

## Contexte

Le front marchand dispose déjà :

- d'un shell marchand avec navigation active vers `/merchant/commandes` ;
- d'une page `/merchant/commandes` qui liste les commandes actives ;
- d'un détail `/merchant/commandes/{orderId}` qui affiche la Kadhia et les actions selon le statut ;
- d'un service `listMerchantOrders` et d'un service `listMerchantOrderHistory` ;
- d'un endpoint backend `GET /api/merchant/stores/{storeId}/orders/history`.

Le chantier couvre la priorité P1 "Historique complet des commandes marchand" du document `docs/SprintFrontend/merchant-next-chantiers.md`.

## Objectif

Permettre au marchand de retrouver les commandes non présentes dans la liste active principale, sans sortir du parcours commandes.

Le marchand doit pouvoir :

- ouvrir l'onglet "Historique" depuis `/merchant/commandes` ;
- consulter les commandes "À retirer" ;
- consulter les commandes "Clôturées" ;
- paginer les résultats ;
- ouvrir le détail existant d'une commande.

## Périmètre MVP

Inclus :

- activation de l'onglet "Historique" dans `/merchant/commandes` ;
- consommation de `GET /api/merchant/stores/{storeId}/orders/history` ;
- filtres métier :
  - "À retirer" : `ready`, `pickup_pending` ;
  - "Clôturées" : `completed`, `cancelled`, `rejected` ;
- pagination simple avec `page` et `limit` ;
- ouverture du détail existant `/merchant/commandes/{orderId}` ;
- types frontend dédiés au payload history réel.

Exclus :

- recherche texte ;
- filtres par date ;
- export CSV ;
- mode lecture seule forcé selon la provenance "Historique" ;
- WebSocket, Mercure, push ou polling automatique ;
- paiement en ligne, livraison, fidélité, panier multi-marchands.

## Décisions UX

`/merchant/commandes` reste le point d'entrée unique pour les commandes marchand.

L'écran propose deux onglets :

- "Actives" : comportement existant, avec les statuts actifs déjà utilisés ;
- "Historique" : nouvelle liste historisée.

Dans l'onglet "Historique", le filtre par défaut est "À retirer", car il aide le marchand à retrouver rapidement les commandes prêtes ou en retrait en cours. Le filtre "Clôturées" permet ensuite de consulter les commandes finalisées, annulées ou refusées.

Chaque liste a ses propres états :

- chargement ;
- erreur avec action "Réessayer" ;
- liste vide ;
- pagination lorsque `total > limit`.

Une ligne d'historique affiche au minimum :

- numéro de commande si disponible, sinon identifiant ;
- statut ;
- total en TND ;
- rendez-vous de retrait si présent ;
- client si exposé par l'API ;
- date de dernière mise à jour ;
- lien vers `/merchant/commandes/{orderId}`.

Le détail commande reste déterminé par le statut de la commande, pas par la page d'origine. Une commande `ready` ouverte depuis l'historique garde donc le comportement actuel du détail, et une commande clôturée n'expose pas d'action active.

## Contrat API

Le frontend appelle :

```text
GET /api/merchant/stores/{storeId}/orders/history
```

Paramètres utilisés dans cette tranche :

- `page` : entier positif, défaut frontend `1` ;
- `limit` : entier positif, défaut frontend `20` ;
- `status` : liste CSV contrôlée par constantes frontend.

Valeurs `status` :

```text
ready,pickup_pending
completed,cancelled,rejected
```

Les paramètres backend `query`, `date_from` et `date_to` restent hors périmètre pour cette tranche.

## Types frontend

Le payload history backend ne doit pas être typé avec `MerchantOrderList`, car il diffère de la liste active.

Types à ajouter ou ajuster :

- `MerchantOrderHistoryCustomer`
- `MerchantOrderHistoryPickupSlot`
- `MerchantOrderHistoryItem`
- `MerchantOrderHistoryList`

Forme attendue côté frontend :

```ts
interface MerchantOrderHistoryList {
  items: MerchantOrderHistoryItem[];
  total: number;
  page: number;
  limit: number;
}
```

Le service `listMerchantOrderHistory(storeId, options)` reste l'entrée unique et retourne ce type dédié.

## Architecture frontend

La page `/merchant/commandes` peut rester un composant client unique pour cette tranche.

L'implémentation doit toutefois séparer clairement :

- l'état des commandes actives ;
- l'état de l'historique ;
- l'onglet sélectionné ;
- le filtre historique sélectionné ;
- la page courante de l'historique.

Des extractions légères sont acceptées si elles rendent le fichier plus lisible :

- ligne de commande active ;
- ligne de commande historique ;
- pagination ;
- boutons de filtre historique.

Il ne faut pas faire de refactor large du front marchand dans cette PR.

## Erreurs et sécurité

Message d'erreur dédié pour l'historique :

```text
Impossible de charger l'historique des commandes.
```

Les erreurs de validation backend sur `status`, `page` ou `limit` ne devraient pas arriver depuis l'UI, car les valeurs sont contrôlées par constantes frontend.

La sécurité reste assurée par le backend. Le frontend utilise uniquement `merchant.store.id` depuis le contexte marchand et ne permet pas de choisir librement une supérette.

## Tests attendus

Tests service :

- `listMerchantOrderHistory` appelle `/api/merchant/stores/{storeId}/orders/history` ;
- les paramètres `page`, `limit` et `status` sont transmis correctement.

Tests rendu :

- la page affiche les onglets "Actives" et "Historique" ;
- l'onglet actif conserve l'appel `listMerchantOrders` existant ;
- l'onglet historique charge `ready,pickup_pending` par défaut ;
- le filtre "Clôturées" recharge `completed,cancelled,rejected` ;
- une ligne d'historique pointe vers `/merchant/commandes/{orderId}` ;
- les états vide et erreur de l'historique sont lisibles.

## Vérification prévue

Depuis `apps/frontend/` :

```bash
npm run lint
npx tsc --noEmit
```

Lancer aussi les tests Vitest ciblés si la commande du projet est disponible pour les fichiers concernés.

Ne pas déclarer les tests passants sans les avoir exécutés.

## Risques

- Le payload history backend n'expose pas `order_number` dans les classes lues ; si le numéro lisible n'est pas disponible, la ligne doit afficher `id`.
- Le service frontend existe déjà mais son type est trop générique ; il faut l'aligner sans casser la liste active.
- La page `/merchant/commandes` peut grossir ; limiter les extractions à des composants locaux ou petits helpers si nécessaire.
