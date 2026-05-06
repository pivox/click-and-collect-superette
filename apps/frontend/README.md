# Frontend — Click & Collect Supérette

Interface web responsive du MVP.

## Périmètre

Cette application couvre les espaces suivants :

- espace client ;
- espace marchand ;
- espace admin plateforme.

Il n'y a pas d'application mobile native dans le MVP.

## Responsabilités

### Client

- accéder à une supérette via QR code ;
- consulter le catalogue du magasin ;
- composer sa Kadhia ;
- choisir un rendez-vous ;
- suivre le statut de la commande ;
- présenter le QR code de retrait.

### Marchand

- voir les commandes reçues ;
- accepter ou refuser une commande ;
- passer une commande en préparation ;
- indiquer qu'une commande est prête ;
- valider le retrait ;
- gérer les prix et disponibilités produits.

### Admin plateforme

- gérer les supérettes ;
- gérer les comptes marchands ;
- gérer le référentiel produits ;
- superviser les commandes.

## Règle importante

Le frontend affiche et orchestre les parcours utilisateur, mais le backend reste la source de vérité pour :

- les prix ;
- les statuts de commande ;
- les droits d'accès ;
- la validation des QR codes ;
- les règles métier.
