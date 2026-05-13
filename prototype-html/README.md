# Prototype HTML statique

Ce dossier contient un prototype statique pour conceptualiser l'application Click & Collect Supérette.

## Pages disponibles

- `index.html` : page d'entrée du prototype
- `user-client-flow.html` : nouvelle maquette du parcours client mobile complet
- `user-web-flow.html` : nouvelle maquette du parcours client web desktop
- `client-mobile.html` : parcours client mobile initial
- `merchant-mobile.html` : parcours marchand mobile
- `merchant-web.html` : espace marchand web
- `admin-web.html` : administration plateforme web

## Parcours client mobile

La page `user-client-flow.html` couvre les écrans principaux côté utilisateur final :

1. accueil ;
2. reconnaissance / accès magasin ;
3. fiche supérette ;
4. catalogue ;
5. Kadhia ;
6. choix du créneau de retrait ;
7. suivi de commande ;
8. QR code de retrait.

Le style dédié est dans `user-client-flow.css`.

## Parcours client web

La page `user-web-flow.html` propose une version desktop du même parcours avec :

- navigation latérale ;
- recherche globale ;
- fiche store reconnue ;
- catalogue en grille ;
- panier latéral ;
- sélection du créneau ;
- suivi de commande ;
- QR code de retrait.

Le style dédié est dans `user-web-flow.css`.

## Utilisation

Ouvrir directement `prototype-html/index.html` dans un navigateur.

Aucun framework, aucune dépendance externe.

## Objectif

Ces écrans servent à valider :

- les parcours métier ;
- la navigation ;
- les statuts de commande ;
- la logique QR code ;
- la présentation bilingue français / arabe ;
- l'utilisation de la devise TND.
