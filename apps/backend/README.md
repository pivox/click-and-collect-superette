# Backend — Click & Collect Supérette

API centrale du MVP.

## Périmètre

Le backend porte la logique métier, la sécurité et la persistance de l'application.

## Responsabilités principales

- authentification ;
- autorisation par rôles ;
- gestion des clients ;
- gestion des marchands ;
- gestion des supérettes ;
- référentiel produits Tunisie ;
- offres produits par marchand ;
- prix courants et historique de prix ;
- commandes ;
- statuts de commande ;
- créneaux de retrait ;
- QR codes magasin ;
- QR codes de retrait ;
- audit et supervision.

## Source de vérité

Le backend est la source de vérité pour :

- le prix au moment de la commande ;
- les transitions de statut ;
- les autorisations ;
- la validation d'une commande ;
- la validation d'un retrait ;
- les données du référentiel produit.

## Architecture attendue

L'implémentation cible peut être réalisée avec Symfony et API Platform.

Structure indicative :

```text
apps/backend/
├── config/
├── migrations/
├── public/
├── src/
│   ├── Entity/
│   ├── Repository/
│   ├── Application/
│   ├── Domain/
│   ├── Infrastructure/
│   └── Controller/
├── tests/
├── composer.json
└── README.md
```

## Règle importante

Aucune règle métier critique ne doit dépendre uniquement du frontend.
