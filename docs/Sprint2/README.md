# Sprint 2 — Parcours client et reconnaissance de supérette

## Objectif du sprint

Sprint 2 doit permettre au client d'identifier une supérette, d'accéder à son espace public et de commencer sa Kadhia dans le bon store.

Le QR code n'est plus seulement un raccourci vers le catalogue. Il devient un mécanisme de reconnaissance du store par le client.

## Parcours cible

```text
QR code ou recherche
→ reconnaissance du store
→ fiche publique de la supérette
→ relation client/supérette si client connecté
→ catalogue
→ Kadhia
→ créneau de retrait
→ commande
```

## Décisions produit

- Le QR code identifie une supérette active via un token public.
- Le token ne doit pas exposer un identifiant interne sensible.
- Un client connecté peut créer une relation avec une supérette.
- Cette relation peut venir d'un QR code, d'une recherche ou d'une future commande.
- Le client doit aussi pouvoir rechercher une supérette par nom ou ville.
- Les données publiques d'un store ne doivent pas exposer les données privées du marchand.

## User stories concernées

| US | Sujet | Statut documentation |
| --- | --- | --- |
| US-001 | Reconnaître une supérette par QR code | Recadrée |
| US-031 | Voir les informations de la supérette | Existante |
| US-032 | Associer un client à une supérette | Ajoutée |
| US-033 | Rechercher une supérette | Ajoutée |

## Modèle métier à prévoir

Relation cible :

```text
Client 1..n ClientStore n..1 Store
```

Champs minimaux proposés :

```text
client_store
- id
- customer_id
- store_id
- source
- first_seen_at
- last_seen_at
- is_favorite
- status
- created_at
- updated_at
```

Contrainte importante :

```text
UNIQUE(customer_id, store_id)
```

## Endpoints candidats

```http
GET /api/stores/by-qr/{qrCodeToken}
GET /api/stores/search?query=amen&city=tunis
GET /api/stores/{storeId}
GET /api/me/stores
POST /api/me/stores/{storeId}/visit
PATCH /api/me/stores/{storeId}/favorite
DELETE /api/me/stores/{storeId}
```

## Hors périmètre Sprint 2

- Carte interactive.
- Géolocalisation avancée.
- Notation des magasins.
- Statistiques de scan.
- Recommandation automatique.
- Gestion admin du store.
- Droits marchand.

## Définition de fini globale

Le Sprint 2 est cohérent lorsque le client peut :

1. reconnaître une supérette par QR code ;
2. rechercher une supérette par nom ou ville ;
3. voir la fiche publique du store ;
4. créer ou mettre à jour sa relation avec le store s'il est connecté ;
5. continuer vers le catalogue de cette même supérette.