# Sprint 3 — Parcours marchand core

## Statut global

**Statut backend : termine.**

Sprint 3 livre le parcours marchand core depuis la reception d'une commande soumise jusqu'a la commande prete a retirer, avec gestion manuelle des creneaux de retrait, annulation client avant traitement, acceptation partielle, preparation ligne par ligne, historique de statuts et dashboard journalier.

Le sprint reste strictement MVP : pas de paiement en ligne, pas de livraison, pas de notifications, pas de retrait QR, pas de double validation de retrait.

## Rapport technique

- [Rapport technique de preparation Sprint 3](technical-readiness-report.md) — rapport historique de readiness, mis a jour avec l'etat livre.
- [Rapport de cloture Sprint 3](completion-report.md) — synthese de cloture, endpoints livres, tests et limites restantes.

## Parcours livre

```text
Nouvelle commande soumise
-> consultation liste marchand
-> consultation detail avec lignes, creneau et coordonnees client autorisees
-> decision : accepter / refuser / accepter partiellement
-> (si partiellement accepte) Kadhia repassee en draft puis resoumission possible
-> passage en preparation
-> preparation ligne par ligne
-> declaration prete si toutes les lignes sont preparees
-> commande prete pour le retrait (Sprint 4)
```

## Decisions produit livrees

- Un marchand ne voit que les commandes de la supérette dont il est propriétaire (`Shop.owner`).
- Les routes marchandes portent le `storeId` pour verifier la coherence supérette / commande.
- L'acceptation et le refus complet sont autorises uniquement depuis `submitted`.
- L'acceptation partielle est autorisee uniquement depuis `submitted`.
- L'acceptation partielle remet la Kadhia en `draft` avec les lignes acceptees ; les lignes refusees sont retirees de la Kadhia.
- La re-soumission apres acceptation partielle met a jour la commande existante.
- L'annulation client est autorisee uniquement depuis `submitted`.
- Le refus complet et l'annulation client liberent la capacite du creneau de retrait.
- L'acceptation partielle conserve le creneau reserve.
- La preparation ligne par ligne est autorisee uniquement sur une commande `preparing`.
- `mark-ready` est strict : toutes les lignes doivent etre `prepared=true`.
- Chaque transition livree en Sprint 3 ecrit un `OrderStatusLog`.
- Le dashboard journalier ne retourne pas de donnees client ni de lignes de commande.

## User stories Sprint 3

| US | Sujet | Statut backend | Tests principaux |
| --- | --- | --- | --- |
| US-040 | Historique des transitions de statut | Livre | `OrderStatusHistoryApiTest`, `OrderStatusLogDoctrineTest`, tests transitions dans `MerchantOrderApiTest`, `OrderCancelApiTest`, `SubmitOrderApiTest` |
| US-024 | Configurer les creneaux de retrait | Livre | `PickupSlotApiTest` |
| US-022 | Consulter la liste des commandes marchand | Livre | `MerchantOrderApiTest` |
| US-045 | Coordonnees client dans le detail marchand | Livre | `MerchantOrderApiTest` |
| US-005 | Accepter ou refuser une commande | Livre | `MerchantOrderApiTest` |
| US-036 | Annuler une commande client | Livre | `OrderCancelApiTest` |
| US-006 | Preparer une commande ligne par ligne | Livre | `MerchantOrderApiTest`, `OrderLine` via tests fonctionnels |
| US-023 | Declarer une commande prete | Livre | `MerchantOrderApiTest` |
| US-037 | Accepter partiellement une commande | Livre | `MerchantOrderApiTest`, `SubmitOrderApiTest` |
| US-051 | Dashboard journalier marchand | Livre | `MerchantDashboardApiTest` |

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

### Marchand — creneaux de retrait

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

Le dashboard retourne les compteurs du jour, les compteurs par statut, les commandes `submitted` urgentes avec creneau dans moins de 3 heures et les creneaux du jour avec capacite restante.

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
- Confidentialite : les coordonnees client sont absentes de la liste marchand et visibles uniquement dans le detail autorise.
- Transitions accept/reject/partially-accept/cancel/start-preparation/mark-ready limitees aux statuts attendus.
- Libération de capacite sur refus complet et annulation client.
- Conservation de capacite sur acceptation partielle.
- Kadhia repassee en `draft` apres acceptation partielle, avec uniquement les lignes acceptees.
- Resoumission apres acceptation partielle sur la meme commande.
- `OrderLine.prepared` persiste et est expose dans le detail marchand.
- `mark-ready` refuse les commandes dont toutes les lignes ne sont pas preparees.
- Historique chronologique client et marchand avec notes de refus/refus partiel.
- Dashboard limite a la supérette cible et aux commandes du jour.

## Limites connues

- Les notifications client/marchand restent hors Sprint 3 et sont prevues Sprint 4.
- Le QR code de retrait, `PickupSession`, scan marchand et double validation de retrait restent Sprint 4.
- Le passage metier vers `pickup_pending` et `completed` n'est pas expose par les endpoints Sprint 3.
- Les creneaux recurrents restent Sprint 3b.
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

Le backend Sprint 3 est considere termine lorsque :

1. le marchand consulte ses commandes et leur detail ;
2. les coordonnees client sont disponibles uniquement dans le detail marchand autorise ;
3. le marchand accepte, refuse ou accepte partiellement une commande ;
4. le client peut annuler une commande `submitted` ;
5. le marchand gere des creneaux ponctuels ;
6. le marchand passe une commande en preparation ;
7. le marchand prepare les lignes une par une ;
8. le marchand declare une commande prete uniquement si toutes les lignes sont preparees ;
9. chaque transition Sprint 3 est tracee dans `OrderStatusLog` ;
10. le dashboard journalier marchand est disponible ;
11. les suites Sprint 3b et Sprint 4 restent clairement hors perimetre.
