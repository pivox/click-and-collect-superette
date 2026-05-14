# Rapport de clôture Sprint 3 — backend

## Synthese

Le backend Sprint 3 est clôturé. Les user stories du parcours marchand core sont livrées, testées et alignées sur le périmètre MVP : traitement de commande par le marchand, annulation client avant traitement, gestion ponctuelle des créneaux de retrait, historique de statuts, préparation ligne par ligne, commande prête et dashboard journalier.

Cette clôture ne couvre pas le retrait sécurisé, les notifications, les créneaux récurrents, les fermetures exceptionnelles ni les automatisations de délai.

## US verifiees

| US | Resultat | Notes |
| --- | --- | --- |
| US-005 | Livré | Acceptation/refus depuis `submitted`, ownership marchand, raison de refus, libération créneau sur refus, log statut. |
| US-006 | Livré | `OrderLine.prepared` persistant, endpoint de préparation, détail marchand expose `prepared`. |
| US-022 | Livré | Liste marchand par `storeId`, filtres de statut, isolation supérette. |
| US-023 | Livré | `mark-ready` depuis `preparing` uniquement et seulement si toutes les lignes sont préparées. |
| US-024 | Livré | CRUD marchand de créneaux ponctuels, suppression logique par désactivation. |
| US-036 | Livré | Annulation client depuis `submitted` uniquement, libération créneau, commande conservée. |
| US-037 | Livré | Acceptation partielle, Kadhia en `draft`, lignes refusées retirées, resoumission même commande. |
| US-040 | Livré | `OrderStatusLog`, historique client et marchand, notes de refus/refus partiel. |
| US-045 | Livré | Coordonnées client dans le détail marchand autorisé, absentes de la liste. |
| US-051 | Livré | Dashboard journalier `/dashboard/today`, compteurs dont `pickup_pending_count`, 404 supérette inconnue testé, urgence, créneaux du jour, pas de PII. |

## Endpoints verifies

```http
GET    /api/merchant/stores/{storeId}/orders
GET    /api/merchant/stores/{storeId}/orders/{orderId}
POST   /api/merchant/stores/{storeId}/orders/{orderId}/accept
POST   /api/merchant/stores/{storeId}/orders/{orderId}/reject
POST   /api/merchant/stores/{storeId}/orders/{orderId}/partially-accept
POST   /api/merchant/stores/{storeId}/orders/{orderId}/start-preparation
PATCH  /api/merchant/stores/{storeId}/orders/{orderId}/lines/{merchantProductId}/preparation
POST   /api/merchant/stores/{storeId}/orders/{orderId}/mark-ready
POST   /api/me/orders/{orderId}/cancel
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
| `tests/Functional/Api/MerchantOrderApiTest.php` | Liste/détail marchand, accept/reject, acceptation partielle, start-preparation, mark-ready strict, préparation ligne par ligne, sécurité. |
| `tests/Functional/Api/OrderCancelApiTest.php` | Annulation client, mauvais statuts, mauvais rôle, libération créneau, historique. |
| `tests/Functional/Api/OrderStatusHistoryApiTest.php` | Historique client/marchand, ownership, ordre chronologique. |
| `tests/Functional/Api/PickupSlotApiTest.php` | CRUD créneaux marchand, collection publique, capacité, conflits, désactivation. |
| `tests/Functional/Api/MerchantDashboardApiTest.php` | Dashboard journalier, compteurs incluant `pickup_pending_count`, 404 supérette inconnue, créneaux, urgence, confidentialité. |
| `tests/Functional/Api/SubmitOrderApiTest.php` | Soumission et resoumission apres acceptation partielle. |
| `tests/Functional/Doctrine/OrderStatusLogDoctrineTest.php` | Persistence de l'historique de statuts. |

## Limites restantes

### Sprint 3b

- Créneaux récurrents.
- Fermetures exceptionnelles.
- Heures d'ouverture publiques.
- Délai de réponse marchand automatisé.
- Expiration automatique d'une acceptation partielle.
- Ruptures de stock en masse.
- Historique marchand complet avec filtres avances.

### Sprint 4

- Notifications client/marchand.
- QR code de retrait.
- `PickupSession`.
- Scan marchand.
- Double validation client + marchand.
- Passage opérationnel vers `pickup_pending` puis `completed`.

### Hors Sprint 3

- Export et statistiques avancées.
- Paiement en ligne.
- Livraison.
- Programme de fidelite.
- Marketplace multi-marchands avec Kadhia partagee.

## Décision de clôture

Sprint 3 est considéré terminé côté backend. Les prochaines PR doivent partir de cet état comme base stable et ne pas réintroduire d'anciens contrats marchands sans `storeId`.
