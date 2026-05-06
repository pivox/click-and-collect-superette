# ADR 0001 — MVP limité à Frontend + Backend

## Statut

Accepté.

## Contexte

Le produit Click & Collect Supérette Tunisie doit avancer rapidement vers un MVP exploitable par les clients, les marchands et l'administrateur plateforme.

La création d'applications mobiles natives iOS et Android augmenterait le coût, le délai de livraison et la complexité produit sans être indispensable pour valider le marché.

## Décision

Pour le MVP, le projet est structuré autour de deux applications uniquement :

1. **Frontend web** : interface responsive pour les clients, les marchands et l'administration plateforme.
2. **Backend API** : API centrale, logique métier, persistance, sécurité, commandes, produits, magasins et référentiel.

Aucune application mobile native n'est prévue dans la V1.

## Conséquences

- Le dépôt reste simple à comprendre et à maintenir.
- Les parcours client, marchand et admin sont gérés par le même frontend, avec séparation par rôles.
- Le backend expose les endpoints nécessaires au frontend.
- Les futures applications mobiles pourront être ajoutées plus tard sans bloquer le MVP.
- Toute création de dossier `mobile/`, `ios/` ou `android/` est hors périmètre MVP sauf décision contraire documentée par une nouvelle ADR.

## Structure cible

```text
click-and-collect-superette/
├── apps/
│   ├── frontend/
│   └── backend/
├── docs/
│   ├── adr/
│   ├── architecture/
│   └── product/
└── README.md
```
