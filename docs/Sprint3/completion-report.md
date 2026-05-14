# Rapport de cloture Sprint 3 — backend

## Synthese

Le backend Sprint 3 est cloture. Les user stories du parcours marchand core sont livrees, testees et alignees sur le perimetre MVP : traitement de commande par le marchand, annulation client avant traitement, gestion ponctuelle des creneaux de retrait, historique de statuts, preparation ligne par ligne, commande prete et dashboard journalier.

Cette cloture ne couvre pas le retrait securise, les notifications, les creneaux recurrents, les fermetures exceptionnelles ni les automatisations de delai.

## US verifiees

| US | Resultat | Notes |
| --- | --- | --- |
| US-005 | Livre | Acceptation/refus depuis `submitted`, ownership marchand, raison de refus, liberation creneau sur refus, log statut. |
| US-006 | Livre | `OrderLine.prepared` persistant, endpoint de preparation, detail marchand expose `prepared`. |
| US-022 | Livre | Liste marchand par `storeId`, filtres de statut, isolation supérette. |
| US-023 | Livre | `mark-ready` depuis `preparing` uniquement et seulement si toutes les lignes sont preparees. |
| US-024 | Livre | CRUD marchand de creneaux ponctuels, suppression logique par desactivation. |
| US-036 | Livre | Annulation client depuis `submitted` uniquement, liberation creneau, commande conservee. |
| US-037 | Livre | Acceptation partielle, Kadhia en `draft`, lignes refusees retirees, resoumission meme commande. |
| US-040 | Livre | `OrderStatusLog`, historique client et marchand, notes de refus/refus partiel. |
| US-045 | Livre | Coordonnees client dans le detail marchand autorise, absentes de la liste. |
| US-051 | Livre | Dashboard journalier `/dashboard/today`, compteurs, urgence, creneaux du jour, pas de PII. |

## Endpoints verifies

```http
GET    /api/merchant/stores/{storeId}/orders
GET    /api/merchant/stores/{storeId}/orders/{orderId}
POST   /api/merchant/stores/{storeId}/orders/{orderId}/accept
POST   /api/merchant/stores/{storeId}/orders/{orderId}/reject
POST   /api/me/orders/{orderId}/cancel
PATCH  /api/merchant/stores/{storeId}/orders/{orderId}/lines/{merchantProductId}/preparation
POST   /api/merchant/stores/{storeId}/orders/{orderId}/mark-ready
POST   /api/merchant/stores/{storeId}/orders/{orderId}/partially-accept
GET    /api/me/orders/{orderId}/status-history
GET    /api/merchant/stores/{storeId}/orders/{orderId}/status-history
GET    /api/merchant/stores/{storeId}/pickup-slots
POST   /api/merchant/stores/{storeId}/pickup-slots
PATCH  /api/merchant/stores/{storeId}/pickup-slots/{slotId}
DELETE /api/merchant/stores/{storeId}/pickup-slots/{slotId}
GET    /api/merchant/stores/{storeId}/dashboard/today
```

## Tests principaux

| Fichier | Couverture Sprint 3 |
| --- | --- |
| `tests/Functional/Api/MerchantOrderApiTest.php` | Liste/detail marchand, accept/reject, acceptation partielle, start-preparation, mark-ready strict, preparation ligne par ligne, securite. |
| `tests/Functional/Api/OrderCancelApiTest.php` | Annulation client, mauvais statuts, mauvais role, liberation creneau, historique. |
| `tests/Functional/Api/OrderStatusHistoryApiTest.php` | Historique client/marchand, ownership, ordre chronologique. |
| `tests/Functional/Api/PickupSlotApiTest.php` | CRUD creneaux marchand, collection publique, capacite, conflits, desactivation. |
| `tests/Functional/Api/MerchantDashboardApiTest.php` | Dashboard journalier, compteurs, creneaux, urgence, confidentialite. |
| `tests/Functional/Api/SubmitOrderApiTest.php` | Soumission et resoumission apres acceptation partielle. |
| `tests/Functional/Doctrine/OrderStatusLogDoctrineTest.php` | Persistence de l'historique de statuts. |

## Limites restantes

### Sprint 3b

- Creneaux recurrents.
- Fermetures exceptionnelles.
- Heures d'ouverture publiques.
- Delai de reponse marchand automatise.
- Expiration automatique d'une acceptation partielle.
- Ruptures de stock en masse.
- Historique marchand complet avec filtres avances.

### Sprint 4

- Notifications client/marchand.
- QR code de retrait.
- `PickupSession`.
- Scan marchand.
- Double validation client + marchand.
- Passage operationnel vers `pickup_pending` puis `completed`.

### Hors Sprint 3

- Export et statistiques avancees.
- Paiement en ligne.
- Livraison.
- Programme de fidelite.
- Marketplace multi-marchands avec Kadhia partagee.

## Decision de cloture

Sprint 3 est considere termine cote backend. Les prochaines PR doivent partir de cet etat comme base stable et ne pas reintroduire d'anciens contrats marchands sans `storeId`.
