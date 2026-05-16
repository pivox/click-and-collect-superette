# Sprint 3b — Maturite operationnelle marchand

## Statut

**Statut : fondation documentaire.**

Sprint 3b demarre apres la cloture backend de Sprint 3 core et Sprint 4. Il ne change pas le parcours de retrait securise ; il complete l'outillage quotidien du marchand autour des creneaux, disponibilites, historiques et automatisations.

Cette fondation ne livre aucun endpoint applicatif. Elle fixe le perimetre, les contrats cibles et l'ordre recommande des futures PR backend.

## Objectif

Permettre au marchand de gerer son activite quotidienne avec moins d'operations manuelles :

- creer des creneaux recurrents ;
- declarer des fermetures exceptionnelles ;
- publier les heures d'ouverture ;
- consulter un historique complet des commandes ;
- gerer les ruptures de stock en masse ;
- automatiser les delais de reponse et d'expiration d'acceptation partielle.

## Périmètre

| US | Sujet | Objectif | Statut |
|---|---|---|---|
| US-047 | Creneaux recurrents | Definir des regles hebdomadaires et generer les creneaux ponctuels sur 4 semaines | A coder |
| US-056 | Fermeture exceptionnelle | Bloquer une plage de dates/heures sans supprimer les regles recurrentes | A coder |
| US-057 | Heures d'ouverture | Definir les horaires hebdomadaires et les exposer publiquement | A coder |
| US-053 | Historique complet marchand | Lister toutes les commandes avec filtres et pagination | A coder |
| US-052 | Ruptures de stock en masse | Mettre a jour la disponibilite de plusieurs produits marchand | A coder |
| US-043 | Delai de reponse marchand | Annuler automatiquement une commande non traitee avant 2h du creneau | A coder |
| US-049 | Expiration acceptation partielle | Annuler automatiquement si le client ne re-soumet pas avant 2h du creneau | A coder |

## Endpoints cibles

### US-047 — Creneaux recurrents

```http
GET    /api/merchant/stores/{storeId}/pickup-slot-rules
POST   /api/merchant/stores/{storeId}/pickup-slot-rules
PATCH  /api/merchant/stores/{storeId}/pickup-slot-rules/{id}
DELETE /api/merchant/stores/{storeId}/pickup-slot-rules/{id}
POST   /api/merchant/stores/{storeId}/pickup-slot-rules/generate
```

Règles métier :

- reserve a `ROLE_MERCHANT` ;
- ownership strict via `Shop.owner` ;
- `weekday`, `start_time`, `end_time`, `capacity` obligatoires ;
- generation sur 4 semaines glissantes ;
- generation idempotente : ne pas dupliquer un `PickupSlot` deja cree pour la meme supérette et la meme plage ;
- respecter les fermetures exceptionnelles lorsque US-056 sera livree ;
- ne pas supprimer les creneaux deja reserves.

### US-056 — Fermetures exceptionnelles

```http
GET    /api/merchant/stores/{storeId}/exceptional-closures
POST   /api/merchant/stores/{storeId}/exceptional-closures
PATCH  /api/merchant/stores/{storeId}/exceptional-closures/{id}
DELETE /api/merchant/stores/{storeId}/exceptional-closures/{id}
```

Règles métier :

- reserve a `ROLE_MERCHANT` ;
- ownership strict via `Shop.owner` ;
- une fermeture bloque la generation de nouveaux creneaux dans la plage ;
- elle ne supprime pas les regles recurrentes ;
- si des creneaux ponctuels actifs existent deja dans la plage, la PR devra trancher explicitement : refus de creation, desactivation sans commande reservee, ou signalement manuel.

### US-057 — Heures d'ouverture

```http
GET   /api/stores/{storeId}/opening-hours
GET   /api/merchant/stores/{storeId}/opening-hours
PATCH /api/merchant/stores/{storeId}/opening-hours
```

Règles métier :

- lecture publique pour la vitrine client ;
- modification reservee au marchand proprietaire ;
- structure hebdomadaire stable, compatible FR/AR/RTL cote frontend ;
- fuseau de reference : `Africa/Tunis`, sauf configuration future explicite.

### US-053 — Historique complet marchand

```http
GET /api/merchant/stores/{storeId}/orders/history?status=&date_from=&date_to=&customer_query=&page=&limit=
```

Filtres cibles :

- `status` ;
- `date_from` ;
- `date_to` ;
- `customer_query` ;
- `page` ;
- `limit`.

Règles métier :

- reserve au marchand proprietaire ;
- tous statuts inclus, y compris `completed`, `cancelled`, `rejected` et `partially_accepted` ;
- pagination obligatoire ;
- ne pas exposer plus de donnees client que le detail commande marchand deja autorise ;
- ne pas casser `GET /api/merchant/stores/{storeId}/orders`, qui reste la liste operationnelle existante.

### US-052 — Ruptures de stock en masse

```http
PATCH /api/merchant/stores/{storeId}/products/bulk-availability
```

Payload indicatif :

```json
{
  "merchant_product_ids": ["merchant-product-uuid"],
  "is_available": false,
  "merchant_note": "Rupture temporaire"
}
```

Règles métier :

- reserve au marchand proprietaire ;
- chaque `merchant_product_id` doit appartenir a la supérette cible ;
- action atomique : aucun produit modifie si un identifiant est invalide ;
- ne modifie pas les commandes deja soumises ;
- ne modifie pas le referentiel produit global.

### US-043 — Delai de reponse marchand automatique

Objectif : annuler automatiquement une commande non traitee avant 2h du creneau.

Règles cible :

- statuts concernes a confirmer dans la PR : au minimum `submitted` ;
- annulation automatique avant `pickupSlot.startsAt - 2h` ;
- liberation de la capacite du creneau si la commande est annulee ;
- creation d'un `OrderStatusLog` ;
- notification client in-app ;
- execution via Symfony Messenger avec `DelayStamp`.

### US-049 — Expiration acceptation partielle

Objectif : annuler automatiquement une acceptation partielle si le client ne re-soumet pas avant 2h du creneau.

Règles cible :

- statut concerne : `partially_accepted` ;
- si la Kadhia n'est pas re-soumise avant le delai, la commande est annulee ;
- libération éventuelle du créneau à trancher en PR selon l'état exact de reservation ;
- creation d'un `OrderStatusLog` ;
- notification client in-app ;
- execution via Symfony Messenger avec `DelayStamp`.

## Hors périmètre Sprint 3b

- Paiement en ligne.
- Livraison.
- Programme de fidelite.
- Marketplace multi-marchands avec Kadhia partagee.
- Refonte du retrait securise Sprint 4.
- Refonte Auth.
- Administration Sprint 5.
- Export CSV et statistiques avancees Sprint 7.
- Notifications push, SMS, email, Mercure/WebSocket.

## Ordre recommande des PR

1. **S3B-001 — Creneaux recurrents foundation**
   - Entite `PickupSlotRule`.
   - CRUD marchand.
   - Generation de `PickupSlot` sur 4 semaines.
2. **S3B-002 — Fermetures exceptionnelles**
   - Entite `ExceptionalClosure`.
   - CRUD marchand.
   - Blocage de generation sur periodes fermees.
3. **S3B-003 — Heures d'ouverture supérette**
   - Champ `Shop.openingHours`.
   - Lecture publique.
   - Modification marchand.
4. **S3B-004 — Historique complet commandes marchand**
   - Liste toutes commandes.
   - Filtres avances.
   - Pagination.
5. **S3B-005 — Ruptures stock en masse**
   - Action groupee catalogue marchand.
6. **S3B-006 — Delai reponse marchand automatique**
   - Message Messenger.
   - Annulation automatique.
   - Notification client.
   - `OrderStatusLog`.
7. **S3B-007 — Expiration acceptation partielle**
   - Message Messenger.
   - Annulation si client ne repond pas.
   - Notification client.
   - `OrderStatusLog`.
8. **S3B-008 — Audit + cloture Sprint 3b**

## Critere de sortie Sprint 3b

Sprint 3b sera termine lorsque le marchand pourra gerer ses creneaux recurrentement, declarer des fermetures, exposer ses horaires, consulter tout son historique, traiter des ruptures de stock en masse et beneficier d'automatisations fiables sur les commandes sans reponse ou partiellement acceptees.
