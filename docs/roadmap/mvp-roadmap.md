# Roadmap MVP — Click & Collect Supérette Tunisie

## Objectif

Structurer le démarrage du MVP autour d'un socle produit clair, versionné et directement exploitable par le développement.

Le MVP doit permettre à une supérette tunisienne de proposer un parcours click & collect simple : accès par QR code, consultation du catalogue marchand, composition de la Kadhia, choix d'un rendez-vous, validation par le marchand, préparation puis retrait sécurisé.

## Principes MVP

- Le client accède à une supérette via un QR code magasin.
- Le client commande dans l'espace d'une supérette donnée.
- Le produit utilise le vocabulaire local, notamment la Kadhia.
- Le produit est conçu pour la Tunisie : TND, français, arabe, usages locaux.
- Le paiement en ligne et la livraison sont exclus du MVP initial.
- Le catalogue marchand s'appuie sur un référentiel produit global.

## Sprint 0 — Cadrage produit et socle documentaire

### Objectif

Transformer la vision produit en documentation opérationnelle pour commencer le développement sans ambiguïté.

### Livrables

- PRD MVP.
- Règles métier MVP.
- Positionnement marché Tunisie.
- Modèle référentiel produit / catalogue marchand.
- Taxonomie produit initiale.
- Règles de normalisation produit.
- Sources de données produit.
- Modèle de données fonctionnel.
- Contrat API initial.
- UX de recherche produit.

### Critère de sortie

Le développement peut commencer lorsque les entités principales, les parcours MVP, les règles de commande et le modèle produit sont compréhensibles sans discussion orale supplémentaire.

## Sprint 1 — Référentiel produit et catalogue marchand

### Objectif

Permettre au marchand de retrouver des produits existants et de construire son catalogue sans tout ressaisir.

### Fonctionnalités

- Créer le référentiel produit global.
- Créer les catégories produit.
- Créer les marques.
- Gérer les unités et volumes.
- Ajouter un produit du référentiel au catalogue marchand.
- Définir le prix marchand.
- Définir la disponibilité produit.
- Importer un seed CSV initial.

### Entités principales

- ProductReference.
- Brand.
- Category.
- ProductUnit.
- MerchantProduct.
- Merchant.
- Store.

### Critère de sortie

Un marchand peut rechercher un produit comme `Lait demi-écrémé Vitalait 1L`, l'ajouter à sa supérette, définir son prix et le rendre visible aux clients.

## Sprint 2 — Parcours client

### Objectif

Permettre au client de scanner un QR code, consulter les produits d'une supérette et composer sa Kadhia.

### Fonctionnalités

- Accès à la supérette via QR code.
- Affichage des informations de la supérette.
- Consultation du catalogue marchand.
- Recherche produit.
- Filtrage par catégorie.
- Ajout au panier / Kadhia.
- Modification des quantités.
- Suppression d'un produit du panier.
- Choix d'un créneau de retrait.
- Soumission de la commande.

### Critère de sortie

Un client peut scanner un QR code, composer une Kadhia et envoyer une demande de commande au marchand.

## Sprint 3 — Parcours marchand

### Objectif

Permettre au marchand de traiter les demandes de commande.

### Fonctionnalités

- Liste des commandes soumises.
- Consultation du détail d'une Kadhia.
- Acceptation d'une commande.
- Refus d'une commande avec raison.
- Passage en préparation.
- Passage en prêt à retirer.
- Suivi des statuts.

### Critère de sortie

Le marchand peut accepter ou refuser une commande, puis suivre sa préparation jusqu'au statut prêt.

## Sprint 4 — Retrait sécurisé

### Objectif

Finaliser la remise de la commande avec un QR code de retrait et une double validation.

### Fonctionnalités

- Génération d'un QR code de retrait.
- Présentation du QR code par le client.
- Contrôle du QR code côté marchand.
- Passage en retrait en cours.
- Double validation client + marchand.
- Finalisation de la commande.

### Critère de sortie

Une commande acceptée peut être préparée, retirée et finalisée avec une preuve de retrait simple.

## Sprint 5 — Administration minimale

### Objectif

Permettre à la plateforme de gérer les supérettes, les marchands et les données structurantes.

### Fonctionnalités

- Créer une supérette.
- Gérer les comptes marchands.
- Consulter les commandes.
- Gérer les catégories.
- Gérer les marques.
- Corriger les produits du référentiel.
- Valider les demandes d'ajout produit.

### Critère de sortie

L'administrateur peut maintenir les données nécessaires au bon fonctionnement du MVP.

## Hors périmètre MVP

- Paiement en ligne.
- Livraison à domicile.
- Programme de fidélité avancé.
- Gestion multi-entrepôts.
- Marketplace multi-marchands avec panier partagé.
- Géolocalisation obligatoire.
- Application mobile native obligatoire.

## Décision géolocalisation

La géolocalisation n'est pas obligatoire pour le MVP. Le parcours prioritaire repose sur le QR code magasin. La géolocalisation pourra être ajoutée ensuite pour trouver les supérettes proches.

## Priorités immédiates

1. Référentiel produit.
2. Modèle catalogue marchand.
3. Taxonomie produit.
4. Modèle de données.
5. Contrat API.
6. Parcours client QR code -> Kadhia -> rendez-vous.
7. Parcours marchand validation -> préparation -> retrait.
