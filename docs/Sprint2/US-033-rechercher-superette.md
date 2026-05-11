# US-033 — Rechercher une supérette

## Sprint

Sprint 2 — Parcours client.

## Objectif produit

Permettre au client de trouver une supérette sans scanner de QR code.

## Récit utilisateur

En tant que client,
je veux rechercher une supérette par nom ou par ville,
afin de choisir le magasin dans lequel je veux faire ma Kadhia.

## Critères MVP

- nom de la supérette ;
- ville ;
- pays ;
- quartier ou zone si disponible ;
- statut actif.

## API cible

```http
GET /api/stores/search?query=amen&city=tunis
```

## Définition de fini

La story est terminée lorsque le client peut rechercher une supérette active, ouvrir sa fiche publique et poursuivre vers son catalogue.