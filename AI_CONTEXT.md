# AI Context — Click & Collect Supérette Tunisie

Ce fichier est la base commune pour Claude, Codex et tout autre agent IA utilisé sur le projet.

## Produit

Application de click & collect pour les supérettes en Tunisie.

Le client scanne le QR code d'une supérette, accède au catalogue du magasin, prépare sa **Kadhia**, choisit un créneau de retrait, puis récupère sa commande après validation du marchand.

## Langues, devise et localisation

- Langues produit : français et arabe.
- L'arabe doit pouvoir être affiché en RTL.
- Devise : TND, dinar tunisien.
- Les libellés, dates, heures et montants doivent rester cohérents avec un usage tunisien.

## Périmètre MVP strict

Inclus :

- QR code magasin ;
- catalogue produit simple ;
- Kadhia / panier ;
- choix de rendez-vous ;
- soumission de commande ;
- validation ou refus par le marchand ;
- préparation ;
- commande prête ;
- QR code de retrait ;
- double validation client + marchand ;
- personnalisation visuelle par supérette (couleurs + police, Sprint 6) ;
- historique simple ;
- interface français / arabe ;
- montants en TND.

Exclus du MVP :

- paiement en ligne ;
- livraison ;
- programme de fidélité avancé ;
- marketplace multi-marchands avec panier partagé ;
- gestion de stock complexe multi-entrepôts.

## Parcours métier principal

1. Le client scanne le QR code de la supérette.
2. L'application ajoute ou ouvre la supérette dans l'espace client.
3. Le client consulte le catalogue.
4. Le client ajoute des produits à sa Kadhia.
5. Le client choisit un créneau de retrait.
6. Le client soumet la commande.
7. Le marchand valide, refuse ou accepte partiellement.
8. Le marchand prépare.
9. La commande passe à prête.
10. Le client présente le QR code de retrait.
11. Le marchand et le client valident la remise.
12. La commande est finalisée.

## Statuts de commande

- `draft`
- `submitted`
- `accepted`
- `partially_accepted` — le marchand a accepté une partie des lignes ; la Kadhia repasse en `draft` pour que le client la modifie et la re-soumette
- `rejected`
- `preparing`
- `ready`
- `pickup_pending`
- `completed`
- `cancelled`

## Entités métier de référence

- `Shop`
- `CustomerShop` ou `FavoriteShop`
- `ProductReference`
- `ProductReferenceProposal`
- `ProductVariant`
- `MerchantProductOffer`
- `ProductFoodInfo`
- `ProductExternalSource`
- `Kadhia`
- `KadhiaLine`
- `PickupSlot`
- `Order`
- `OrderLine`
- `PickupSession`
- `PlatformTheme`
- `ShopTheme`

## Stack cible recommandée

- API : Symfony 7 + API Platform.
- Base de données : PostgreSQL.
- ORM : Doctrine.
- Asynchrone : Symfony Messenger.
- Cache / file légère : Redis si nécessaire.
- Web temps réel : Mercure ou WebSocket selon le besoin.
- Front client : PWA mobile-first.
- Backoffice marchand : web responsive.

## Règles produit à respecter

- Ne jamais remplacer le terme **Kadhia** par un panier générique sans garder le vocabulaire métier.
- Ne pas ajouter de paiement en ligne dans le MVP sans décision explicite.
- Ne pas ajouter de livraison dans le MVP sans décision explicite.
- Les produits doivent pouvoir exister dans un référentiel commun, puis être proposés par marchand avec prix, disponibilité et visibilité propres.
- Le QR code magasin sert d'abord à ouvrir ou ajouter une supérette à la liste du client.
- Le retrait doit rester sécurisé par une double validation.

## Règles de travail IA

- Toujours commencer par lire ce fichier, puis `README.md`, puis la documentation utile dans `docs/`.
- Préférer de petits changements atomiques.
- Ne pas mélanger cadrage produit, architecture et code dans le même changement sauf demande explicite.
- Toute modification doit lister les fichiers changés, les hypothèses et les risques.
- Pour le code Symfony, préférer des services testables, des DTO clairs, des migrations Doctrine et des tests automatisés.
- Pour les documents produit, écrire en français clair, orienté MVP et décision.
