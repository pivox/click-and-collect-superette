# Front marchand — état livré et chantiers restants

Date de réalignement : 2026-06-04

## Contexte

L'espace marchand Next.js est désormais un backoffice opérationnel pour une supérette Kadhia. Il consomme les API backend existantes et reste dans le périmètre MVP strict : pas de paiement en ligne, pas de livraison, pas de programme de fidélité et pas de panier multi-marchand.

## Écrans livrés

```text
/merchant/login                    → connexion marchand
/merchant                          → dashboard marchand
/merchant/commandes                → commandes actives, historique, export CSV
/merchant/commandes/[orderId]      → détail commande et actions métier
/merchant/retrait                  → retrait sécurisé par QR, code ou mode manuel
/merchant/notifications            → notifications marchand
/merchant/catalogue                → catalogue marchand
/merchant/creneaux                 → créneaux, règles, fermetures, horaires
/merchant/onboarding               → onboarding guidé
/merchant/qr-code                  → QR magasin
/merchant/apparence                → thème supérette
/merchant/parametres               → hub paramètres
/merchant/parametres/profil        → profil public supérette
/merchant/parametres/compte        → compte marchand
/merchant/parametres/langue        → langue marchand FR/AR
```

## Fonctionnalités résolues

### Commandes, retrait, historique, notifications

Livré par PRs #134, #135, #136, #138 et #139 :

- login, contexte marchand, shell, dashboard ;
- liste commandes actives ;
- détail commande ;
- acceptation, refus, acceptation partielle ;
- préparation ligne par ligne et passage `ready` ;
- retrait sécurisé par token QR, confirmation marchand et force completion ;
- historique avec filtres "À retirer" / "Clôturées" et pagination ;
- notifications avec badge non lu, filtres, rafraîchissement manuel, marquage lu.

### Catalogue marchand

Livré par PR #141 et checkpoints associés :

- page `/merchant/catalogue` ;
- recherche, filtres, états chargement/vide/erreur ;
- édition prix TND, disponibilité, visibilité, note marchand ;
- rupture en masse limitée ;
- ajout depuis référentiel avec garde anti-doublon ;
- produit local marchand vendable immédiatement ;
- catégories marchand propres à la supérette ;
- assistant guidé.

### Créneaux, horaires et fermetures

Livré :

- page `/merchant/creneaux` ;
- CRUD créneaux ponctuels ;
- affichage et génération depuis règles récurrentes ;
- fermetures exceptionnelles ;
- consultation/modification des horaires d'ouverture ;
- warning de couverture des créneaux.

### Onboarding, QR, thème et paramètres

Livré :

- page `/merchant/onboarding` consommant `GET /api/merchant/onboarding` et `PATCH /api/merchant/onboarding/complete` ;
- page `/merchant/qr-code` consommant `GET /api/merchant/stores/{storeId}/qr-code` ;
- rendu QR avec `react-qr-code`, copie/affichage URL, téléchargement SVG et impression ;
- page `/merchant/apparence` pour le thème supérette ;
- hub paramètres, profil supérette, compte et langue FR/AR ;
- export CSV depuis `/merchant/commandes`.

## Chantiers restants

### P1 — Fiabilité production et observabilité

Priorité produit recommandée avant les nouveaux écrans :

1. #352 — valider le worker async en production.
2. #353 — monitorer les jobs asynchrones.
3. #354 — instrumenter les KPI terrain.

Justification : la bêta dépend des rappels, expirations et jobs différés. Le marchand doit pouvoir faire confiance aux automatismes avant l'activation terrain.

### P2 — Activation terrain

- #355 — QR magasin imprimable PNG/PDF, au-delà du SVG/tirage navigateur actuel.
- #356 — checklist d'activation supérette.
- #357 — journal opérationnel marchand minimal si besoin terrain confirmé.

### P3 — Mobile et accessibilité

- #375 — PWA marchand.
- #376 — push notifications.
- #379 — accessibilité minimum WCAG.

## Points d'attention

- Composer une URL absolue côté front pour toute impression QR.
- Ne pas créer de logique de capacité uniquement côté frontend.
- Garder les dates/heures compréhensibles localement.
- Utiliser les clés i18n, pas uniquement les labels backend en français.
- Ne jamais ajouter paiement, livraison, fidélité ou panier multi-supérette sans décision explicite.
