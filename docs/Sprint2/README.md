# Sprint 2 — Parcours client

## Objectif du sprint

Sprint 2 couvre l'intégralité du parcours client depuis la découverte d'une supérette jusqu'à la soumission de la commande.

Le client peut identifier une supérette (via QR code ou recherche), consulter son catalogue, composer sa Kadhia, choisir un créneau de retrait et envoyer sa commande au marchand.

## Parcours cible

```text
QR code ou recherche
→ reconnaissance du store
→ fiche publique de la supérette
→ relation client/supérette si client connecté
→ catalogue (navigation, recherche, filtrage)
→ Kadhia (création explicite, ajout, modification des quantités, récapitulatif)
→ créneau de retrait
→ soumission de commande
→ confirmation
```

## Décisions produit

- Le QR code identifie une supérette active via un token public opaque (`qr_code_token`).
- Le token ne doit pas exposer un identifiant interne sensible.
- Un client connecté crée automatiquement une relation avec la supérette lors du premier scan.
- Cette relation peut aussi être créée via une recherche ou une future commande.
- La source de découverte (`qr_code`, `search`, `order`) est conservée à la création et ne change plus.
- `last_seen_at` est mis à jour à chaque consultation reconnue.
- Les données publiques du store ne doivent pas exposer les données privées du marchand.
- Un client peut créer plusieurs Kadhia pour une même supérette.
- La création d'une Kadhia est explicite et se fait par `POST`.
- Un `GET` ne doit jamais créer de Kadhia.
- Une Kadhia reste en état `draft` jusqu'à la soumission ; elle n'est plus modifiable après.
- Une Kadhia `submitted` reste listable et consultable par le client.
- Les prix sont figés à l'ajout en Kadhia (snapshot) et ne sont pas recalculés à la soumission.
- La capacité du créneau est décrémentée à la soumission, pas à la sélection.
- La soumission est transactionnelle : créneau et Kadhia sont revalidés atomiquement.
- Une commande soumise passe en statut `submitted` ; le marchand prend le relai au Sprint 3.

## User stories concernées

| US | Sujet | Epic | Statut documentation |
| --- | --- | --- | --- |
| US-001 | Scanner le QR code d'une supérette | EPIC-001 | Recadrée |
| US-031 | Voir les informations de la supérette | EPIC-001 | Existante |
| US-032 | Associer un client à une supérette | EPIC-001 | Ajoutée |
| US-033 | Rechercher une supérette | EPIC-001 | Ajoutée |
| US-002 | Consulter le catalogue marchand | EPIC-002 | Existante |
| US-017 | Rechercher un produit par nom ou marque | EPIC-002 | Ajoutée |
| US-018 | Filtrer le catalogue par catégorie | EPIC-002 | Ajoutée |
| US-003-A | Créer une Kadhia | EPIC-003 | Ajoutée |
| US-003-B | Lister mes Kadhia | EPIC-003 | Ajoutée |
| US-003-C | Consulter le détail d'une Kadhia | EPIC-003 | Ajoutée |
| US-003-D | Ajouter un produit à une Kadhia draft | EPIC-003 | Ajoutée |
| US-019-A | Modifier les notes d'une Kadhia draft | EPIC-003 | Ajoutée |
| US-019-B | Modifier la quantité d'un produit dans une Kadhia draft | EPIC-003 | Ajoutée |
| US-019-C | Retirer un produit d'une Kadhia draft | EPIC-003 | Ajoutée |
| US-020 | Récapitulatif de la Kadhia avec total TND | EPIC-003 | Ajoutée |
| US-021-A | Soumettre une Kadhia précise | EPIC-004 | Ajoutée |
| US-021-B | Lister mes Kadhia soumises | EPIC-004 | Ajoutée |
| US-004 | Choisir un créneau de retrait | EPIC-004 | Existante |

Le détail des user stories Kadhia multiple est documenté dans [`kadhia-multiple-user-stories.md`](./kadhia-multiple-user-stories.md).

## Modèle métier à prévoir

### Relation client / supérette

```text
Client 1..n CustomerShop n..1 Shop
```

Champs minimaux :

```text
customer_shop
- id
- customer_id
- shop_id
- source          (qr_code | search | manual | order)
- first_seen_at
- last_seen_at
- is_favorite
- status          (active | hidden)
- created_at
- updated_at

UNIQUE(customer_id, shop_id)
```

### Kadhia et commande

```text
Client 1..n Kadhia n..1 Shop
Kadhia 1..n KadhiaLine
Kadhia 0..1 Order
```

```text
kadhia
- id
- customer_id
- shop_id
- status          (draft | submitted)
- notes
- created_at
- updated_at

kadhia_line
- id
- kadhia_id
- merchant_product_id
- name_fr         (snapshot)
- name_ar         (snapshot)
- brand           (snapshot)
- unit_price_tnd  (snapshot)
- quantity
- line_total_tnd

pickup_slot
- id
- shop_id
- starts_at
- ends_at
- timezone        (Africa/Tunis)
- capacity
- reserved_count

order
- id
- kadhia_id
- customer_id
- shop_id
- pickup_slot_id
- status          (submitted | accepted | rejected | preparing | ready | pickup_pending | completed | cancelled)
- total_tnd
- submitted_at
- created_at
- updated_at
```

## Endpoints candidats

### Supérette

```http
GET  /api/stores/by-qr/{qrCodeToken}
GET  /api/stores/search?query=amen&city=tunis
GET  /api/stores/{storeId}
GET  /api/me/stores
POST /api/me/stores/{storeId}/visit
PATCH /api/me/stores/{storeId}/favorite
DELETE /api/me/stores/{storeId}
```

### Catalogue

```http
GET /api/stores/{storeId}/catalog
GET /api/stores/{storeId}/catalog?query=lait
GET /api/stores/{storeId}/catalog?category=lait
GET /api/stores/{storeId}/catalog?query=vitalait&category=lait
```

### Kadhia

```http
POST   /api/me/stores/{storeId}/kadhias
GET    /api/me/kadhias
GET    /api/me/kadhias?status=draft
GET    /api/me/kadhias?status=submitted
GET    /api/me/kadhias?store_id={storeId}
GET    /api/me/kadhias/{kadhiaId}
PATCH  /api/me/kadhias/{kadhiaId}
PUT    /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}
DELETE /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}
```

### Créneau et commande

```http
GET  /api/stores/{storeId}/pickup-slots?from=today&available=true
POST /api/me/kadhias/{kadhiaId}/submit
```

## Hors périmètre Sprint 2

- Carte interactive.
- Géolocalisation avancée.
- Notation des magasins.
- Statistiques de scan.
- Recommandation automatique.
- Gestion admin du store.
- Droits marchand.
- Validation et traitement de la commande côté marchand (Sprint 3).
- QR code de retrait (Sprint 4).

## Définition de fini globale

Le Sprint 2 est cohérent lorsque le client peut :

1. reconnaître une supérette par QR code ou par recherche ;
2. voir la fiche publique du store ;
3. créer ou mettre à jour sa relation avec le store s'il est connecté ;
4. consulter le catalogue, rechercher un produit et filtrer par catégorie ;
5. créer explicitement une ou plusieurs Kadhia pour une supérette ;
6. lister ses Kadhia en cours ou soumises ;
7. ajouter des produits à une Kadhia `draft`, modifier les quantités et voir le total en TND ;
8. choisir un créneau de retrait disponible ;
9. soumettre une Kadhia précise et recevoir une confirmation avec le numéro de commande.
