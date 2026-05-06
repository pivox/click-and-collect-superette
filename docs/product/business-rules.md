# Règles métier — MVP

## Objectif

Formaliser les règles minimales nécessaires au fonctionnement du MVP Click & Collect Supérette Tunisie.

## Accès magasin

- Chaque supérette possède un QR code magasin unique.
- Le QR code ouvre l'espace de commande de la supérette concernée.
- Le client ne commande que dans la supérette ouverte via le QR code.
- Le QR code magasin peut être régénéré par l'administrateur ou le marchand autorisé.

## Catalogue

- Le catalogue affiché au client est le catalogue marchand, pas le référentiel global complet.
- Un produit doit être visible et disponible pour être commandable.
- Le prix affiché est le prix défini par la supérette.
- Un produit indisponible peut rester visible ou être masqué selon le choix marchand.
- Le référentiel global sert de base à l'ajout de produits au catalogue marchand.

## Kadhia

- Une Kadhia correspond au panier d'un client dans une supérette donnée.
- Une Kadhia ne peut pas mélanger plusieurs supérettes dans le MVP.
- Une ligne de Kadhia contient un produit marchand, une quantité et un prix au moment de l'ajout.
- Le total est calculé en TND.
- Le prix doit être figé au moment de la soumission de commande.

## Rendez-vous

- Le client choisit un créneau de retrait avant de soumettre sa commande.
- Le marchand peut accepter ou refuser selon la disponibilité des produits et du créneau.
- Le MVP peut démarrer avec des créneaux simples configurés par supérette.
- La gestion avancée de capacité par créneau est hors MVP strict, mais doit être prévue dans le modèle.

## Commande

### Statuts

| Statut | Description |
|---|---|
| `draft` | Kadhia en cours. |
| `submitted` | Commande envoyée au marchand. |
| `accepted` | Commande acceptée. |
| `rejected` | Commande refusée. |
| `preparing` | Commande en préparation. |
| `ready` | Commande prête à retirer. |
| `pickup_pending` | Retrait en cours de validation. |
| `completed` | Commande finalisée. |
| `cancelled` | Commande annulée. |

### Transitions autorisées

```text
draft -> submitted
submitted -> accepted
submitted -> rejected
accepted -> preparing
preparing -> ready
ready -> pickup_pending
pickup_pending -> completed
submitted -> cancelled
accepted -> cancelled
```

## Validation marchand

- Le marchand doit pouvoir accepter une commande.
- Le marchand doit pouvoir refuser une commande avec une raison.
- Une commande refusée ne peut plus être préparée.
- Une commande acceptée doit pouvoir passer en préparation puis prête.

## Retrait sécurisé

- Une commande acceptée génère un QR code de retrait.
- Le QR code de retrait est lié à une commande précise.
- Le QR code ne doit plus être utilisable après finalisation.
- Le retrait nécessite une validation côté marchand.
- Le MVP prévoit une double validation client + marchand lorsque possible.

## Administration

- L'administrateur peut gérer les supérettes.
- L'administrateur peut gérer les comptes marchands.
- L'administrateur peut valider ou corriger les produits proposés.
- L'administrateur peut désactiver une supérette ou un marchand.

## Localisation

- La devise par défaut est le TND.
- L'interface doit prévoir le français et l'arabe.
- Les champs traduisibles doivent prévoir une version française et une version arabe.
- L'affichage RTL doit être prévu pour l'arabe.

## Paiement

- Le paiement en ligne est hors MVP.
- Le MVP peut considérer que le paiement se fait en magasin au retrait.
- Le statut de paiement n'est pas nécessaire pour le MVP strict, mais peut être prévu pour extension future.
