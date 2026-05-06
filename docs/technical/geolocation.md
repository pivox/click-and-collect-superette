# Géolocalisation — Décision MVP

## Objectif

Clarifier la place de la géolocalisation dans le MVP Click & Collect Supérette Tunisie.

## Décision MVP

La géolocalisation n'est pas obligatoire pour le MVP initial.

Le parcours prioritaire repose sur le QR code magasin : le client scanne un QR code présent dans ou devant la supérette et accède directement à l'espace de commande de ce commerce.

## Pourquoi ne pas la rendre obligatoire au MVP ?

- Le QR code suffit pour identifier la supérette.
- La géolocalisation ajoute une permission utilisateur supplémentaire.
- Elle complexifie l'expérience mobile, notamment sur iOS.
- Elle n'est pas nécessaire pour tester le coeur du produit : catalogue, Kadhia, rendez-vous, validation marchand et retrait.

## Usage futur

La géolocalisation pourra être ajoutée après le MVP pour :

- trouver les supérettes proches ;
- proposer les commerces autour de l'utilisateur ;
- calculer une distance approximative ;
- améliorer le référencement local ;
- filtrer les supérettes ouvertes ou proches.

## Règles futures

- Toujours demander le consentement utilisateur.
- Prévoir un mode sans géolocalisation.
- Ne pas bloquer l'accès au service si l'utilisateur refuse la permission.
- Ne pas stocker de position précise sans raison produit claire.
- Préférer une localisation approximative lorsque cela suffit.

## Implication technique MVP

Le modèle `Store` peut prévoir des champs optionnels pour préparer l'avenir :

```yaml
latitude: decimal|null
longitude: decimal|null
city: string|null
address: string|null
```

Ces champs ne sont pas bloquants pour le MVP.

## Priorité

Priorité basse pour le MVP.

Priorité moyenne après validation du parcours QR code et catalogue marchand.
