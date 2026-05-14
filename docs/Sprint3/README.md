# Sprint 3 — Parcours marchand core

## Statut global

**Statut backend : terminé.**

Sprint 3 livre le parcours marchand core depuis la réception d'une commande soumise jusqu'à la commande prête à retirer, avec gestion manuelle des créneaux de retrait, annulation client avant traitement, acceptation partielle, préparation ligne par ligne, historique de statuts et dashboard journalier.

Le sprint reste strictement MVP : pas de paiement en ligne, pas de livraison, pas de notifications, pas de retrait QR, pas de double validation de retrait.

## Rapport technique

- [Rapport technique de préparation Sprint 3](technical-readiness-report.md) — rapport historique de readiness, mis à jour avec l'état livré.
- [Rapport de clôture Sprint 3](completion-report.md) — synthèse de clôture, endpoints livrés, tests et limites restantes.

## Parcours livre

```text
Nouvelle commande soumise
-> consultation liste marchand
-> consultation détail avec lignes, créneau et coordonnées client autorisées
-> decision : accepter / refuser / accepter partiellement
-> (si partiellement accepté) Kadhia repassée en draft puis resoumission possible
-> passage en préparation
-> preparation ligne par ligne
-> déclaration prête si toutes les lignes sont préparées
-> commande prête pour le retrait (Sprint 4)
```

## Decisions produit livrees

- Un marchand ne voit que les commandes de la supérette dont il est propriétaire (`Shop.owner`).
- Les routes marchandes portent le `storeId` pour vérifier la cohérence supérette / commande.
- L'acceptation et le refus complet sont autorisés uniquement depuis `submitted`.
- L'acceptation partielle est autorisée uniquement depuis `submitted`.
- L'acceptation partielle remet la Kadhia en `draft` avec les lignes acceptées ; les lignes refusées sont retirées de la Kadhia.
- La re-soumission après acceptation partielle met à jour la commande existante.
- L'annulation client est autorisée uniquement depuis `submitted`.
- Le refus complet et l'annulation client libèrent la capacité du créneau de retrait.
- L'acceptation partielle conserve le créneau réservé.
- La préparation ligne par ligne est autorisée uniquement sur une commande `preparing`.
- `mark-ready` est strict : toutes les lignes doivent être `prepared=true`.
- Chaque transition livree en Sprint 3 ecrit un `OrderStatusLog`.
- Le dashboard journalier ne retourne pas de données client ni de lignes de commande.

## User stories Sprint 3

| US | Sujet | Statut backend | Tests principaux |
| --- | --- | --- | --- |
| US-040 | Historique des transitions de statut | Livré | `OrderStatusHistoryApiTest`, `OrderStatusLogDoctrineTest`, tests transitions dans `MerchantOrderApiTest`, `OrderCancelApiTest`, `SubmitOrderApiTest` |
| US-024 | Configurer les créneaux de retrait | Livré | `PickupSlotApiTest` |
| US-022 | Consulter la liste des commandes marchand | Livré | `MerchantOrderApiTest` |
| US-045 | Coordonnées client dans le détail marchand | Livré | `MerchantOrderApiTest` |
| US-005 | Accepter ou refuser une commande | Livré | `MerchantOrderApiTest` |
| US-036 | Annuler une commande client | Livré | `OrderCancelApiTest` |
| US-006 | Préparer une commande ligne par ligne | Livré | `MerchantOrderApiTest`, `OrderLine` via tests fonctionnels |
| US-023 | Déclarer une commande prête | Livré | `MerchantOrderApiTest` |
| US-037 | Accepter partiellement une commande | Livré | `MerchantOrderApiTest`, `SubmitOrderApiTest` |
| US-051 | Dashboard journalier marchand | Livré | `MerchantDashboardApiTest` |

## Endpoints livres

### Marchand — commandes

```http
GET   /api/merchant/stores/{storeId}/orders
GET   /api/merchant/stores/{storeId}/orders/{orderId}
POST  /api/merchant/stores/{storeId}/orders/{orderId}/accept
POST  /api/merchant/stores/{storeId}/orders/{orderId}/reject
POST  /api/merchant/stores/{storeId}/orders/{orderId}/partially-accept
POST  /api/merchant/stores/{storeId}/orders/{orderId}/start-preparation
PATCH /api/merchant/stores/{storeId}/orders/{orderId}/lines/{merchantProductId}/preparation
POST  /api/merchant/stores/{storeId}/orders/{orderId}/mark-ready
GET   /api/merchant/stores/{storeId}/orders/{orderId}/status-history
```

### Marchand — créneaux de retrait

```http
GET    /api/merchant/stores/{storeId}/pickup-slots
POST   /api/merchant/stores/{storeId}/pickup-slots
PATCH  /api/merchant/stores/{storeId}/pickup-slots/{slotId}
DELETE /api/merchant/stores/{storeId}/pickup-slots/{slotId}
```

`DELETE` desactive le creneau plutot que de le supprimer physiquement.

### Marchand — dashboard

```http
GET /api/merchant/stores/{storeId}/dashboard/today
```

Le dashboard retourne les compteurs du jour, les compteurs par statut, les commandes `submitted` urgentes avec créneau dans moins de 3 heures et les créneaux du jour avec capacité restante.

### Client — commandes

```http
GET  /api/me/orders
GET  /api/me/orders/{id}
POST /api/me/orders/{orderId}/cancel
GET  /api/me/orders/{orderId}/status-history
```

## Regles metier testees

- Separation stricte client / marchand / anonyme.
- Ownership marchand via `Shop.owner`.
- Coherence `storeId` / `order.shop`.
- Confidentialité : les coordonnées client sont absentes de la liste marchand et visibles uniquement dans le détail autorisé.
- Transitions accept/reject/partially-accept/cancel/start-preparation/mark-ready limitees aux statuts attendus.
- Libération de capacite sur refus complet et annulation client.
- Conservation de capacite sur acceptation partielle.
- Kadhia repassee en `draft` apres acceptation partielle, avec uniquement les lignes acceptees.
- Resoumission apres acceptation partielle sur la meme commande.
- `OrderLine.prepared` persiste et est exposé dans le détail marchand.
- `mark-ready` refuse les commandes dont toutes les lignes ne sont pas preparees.
- Historique chronologique client et marchand avec notes de refus/refus partiel.
- Dashboard limite a la supérette cible et aux commandes du jour.

## Limites connues

- Les notifications client/marchand restent hors Sprint 3 et sont prevues Sprint 4.
- Le QR code de retrait, `PickupSession`, scan marchand et double validation de retrait restent Sprint 4.
- Le passage metier vers `pickup_pending` et `completed` n'est pas expose par les endpoints Sprint 3.
- Les créneaux récurrents restent Sprint 3b.
- Les fermetures exceptionnelles restent Sprint 3b.
- Le delai automatique de reponse marchand reste Sprint 3b.
- L'expiration automatique d'une acceptation partielle reste Sprint 3b.
- Les exports et statistiques avancees restent hors Sprint 3.
- Le dashboard utilise le fuseau `Africa/Tunis`.

## Elements reportes

### Sprint 3b — maturite operationnelle marchand

- Creneaux recurrents.
- Fermetures exceptionnelles.
- Delai de reponse marchand automatise.
- Expiration d'acceptation partielle.
- Ruptures de stock en masse.
- Historique marchand complet avec filtres avances.
- Heures d'ouverture publiques.

### Sprint 4 — retrait securise et notifications

- Notifications persistantes client/marchand.
- QR code de retrait.
- `PickupSession`.
- Scan marchand.
- Double validation client + marchand.
- Finalisation `completed`.

## Definition de fini Sprint 3

Le backend Sprint 3 est considéré terminé lorsque :

1. le marchand consulte ses commandes et leur détail ;
2. les coordonnées client sont disponibles uniquement dans le détail marchand autorisé ;
3. le marchand accepte, refuse ou accepte partiellement une commande ;
4. le client peut annuler une commande `submitted` ;
5. le marchand gère des créneaux ponctuels ;
6. le marchand passe une commande en preparation ;
7. le marchand prepare les lignes une par une ;
8. le marchand déclare une commande prête uniquement si toutes les lignes sont préparées ;
9. chaque transition Sprint 3 est tracee dans `OrderStatusLog` ;
10. le dashboard journalier marchand est disponible ;
11. les suites Sprint 3b et Sprint 4 restent clairement hors perimetre.
