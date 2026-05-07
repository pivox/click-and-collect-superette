# Epics — Click & Collect Supérette Tunisie

Ce document liste les epics du MVP et leur mapping avec les sprints de développement.

---

## EPIC-001 — Onboarding par QR code

**Objectif** : Permettre au client d'accéder instantanément à l'espace digital d'une supérette en scannant son QR code.

**Valeur produit** : Zéro friction à l'entrée. Le client n'a pas besoin de chercher la supérette, il scanne et il est dedans.

**Sprint** : Sprint 2 — Parcours client

**User stories** :
- US-001 — Scanner le QR code d'une supérette
- US-031 — Voir les informations de la supérette

**Critère de sortie** : Un client scanne un QR code, arrive sur la page de la supérette et voit son catalogue.

---

## EPIC-002 — Catalogue produits

**Objectif** : Permettre au client de consulter, rechercher et filtrer les produits disponibles dans la supérette.

**Valeur produit** : Le client trouve rapidement ce qu'il cherche sans parcourir tous les rayons physiques.

**Sprint** : Sprint 2 — Parcours client

**User stories** :
- US-002 — Consulter le catalogue marchand
- US-017 — Rechercher un produit par nom ou marque
- US-018 — Filtrer le catalogue par catégorie

**Critère de sortie** : Un client peut trouver un produit comme « Lait demi-écrémé Vitalait 1L » en moins de trois actions.

---

## EPIC-003 — Gestion Kadhia

**Objectif** : Permettre au client de composer sa liste de courses, modifier les quantités et visualiser le total en TND.

**Valeur produit** : Le client prépare ses courses à l'avance depuis chez lui.

**Sprint** : Sprint 2 — Parcours client

**User stories** :
- US-003 — Ajouter un produit à la Kadhia
- US-019 — Modifier la quantité ou retirer un produit de la Kadhia
- US-020 — Visualiser le récapitulatif de la Kadhia avec le total en TND

**Critère de sortie** : Un client peut composer une Kadhia avec plusieurs produits, ajuster les quantités et voir le montant total en TND.

---

## EPIC-004 — Rendez-vous et soumission de commande

**Objectif** : Permettre au client de choisir un créneau de retrait disponible et de soumettre sa commande au marchand.

**Valeur produit** : Le client choisit quand il vient. Le marchand sait quand préparer.

**Sprint** : Sprint 2 — Parcours client

**User stories** :
- US-004 — Choisir un créneau de retrait
- US-021 — Soumettre la commande

**Critère de sortie** : Un client peut soumettre une commande avec un créneau valide et recevoir une confirmation.

---

## EPIC-005 — Validation marchand

**Objectif** : Permettre au marchand de recevoir les commandes soumises, les consulter et décider de les accepter ou de les refuser.

**Valeur produit** : Le marchand garde le contrôle. Il ne prépare que ce qu'il peut honorer.

**Sprint** : Sprint 3 — Parcours marchand

**User stories** :
- US-022 — Consulter la liste des commandes soumises
- US-005 — Accepter ou refuser une commande

**Critère de sortie** : Un marchand reçoit une commande, consulte son détail et prend une décision d'acceptation ou de refus avec raison.

---

## EPIC-006 — Préparation de commande

**Objectif** : Permettre au marchand ou à l'employé de préparer la commande acceptée et de la déclarer prête au retrait.

**Valeur produit** : Le client arrive quand la commande est prête. Le marchand organise sa préparation.

**Sprint** : Sprint 3 — Parcours marchand

**User stories** :
- US-006 — Préparer une commande ligne par ligne
- US-023 — Déclarer une commande prête

**Critère de sortie** : Une commande acceptée peut être préparée produit par produit et passée au statut « prête ».

---

## EPIC-007 — Retrait sécurisé

**Objectif** : Finaliser la remise de la commande par un QR code de retrait et une double validation client + marchand.

**Valeur produit** : Le retrait est sécurisé sans papier ni ambiguïté. Les deux parties valident.

**Sprint** : Sprint 4 — Retrait sécurisé

**User stories** :
- US-025 — Afficher le QR code de retrait côté client
- US-007 — Valider le retrait par double validation
- US-026 — Consulter l'historique des commandes

**Critère de sortie** : Une commande prête peut être retirée avec un QR code, validée des deux côtés et finalisée.

---

## EPIC-008 — Localisation français / arabe

**Objectif** : Proposer l'interface en français et en arabe, avec support RTL et montants en TND.

**Valeur produit** : L'application est accessible à tous les usagers tunisiens.

**Sprint** : Sprint 2 (intégré), Sprint 7 (affinage)

**User stories** :
- US-008 — Basculer la langue de l'interface

**Critère de sortie** : L'interface bascule entre français et arabe. Les montants sont affichés en TND. Le mode RTL fonctionne sur les vues principales.

---

## EPIC-009 — Administration plateforme

**Objectif** : Permettre à l'administrateur de gérer les supérettes, les comptes marchands et le référentiel produit.

**Valeur produit** : La plateforme reste cohérente et maîtrisée. Le support est possible sans intervention directe en base.

**Sprint** : Sprint 5 — Administration minimale

**User stories** :
- US-009 — Créer et gérer les supérettes
- US-028 — Gérer les comptes marchands
- US-029 — Superviser le référentiel produit global
- US-030 — Valider les propositions de nouveaux produits

**Critère de sortie** : L'administrateur peut créer une supérette, activer un marchand et corriger un produit du référentiel.

---

## EPIC-010 — Personnalisation visuelle

**Objectif** : Permettre à l'administrateur de définir un thème global par défaut (couleurs + police), et à chaque marchand de personnaliser l'identité visuelle de sa supérette lors de l'onboarding.

**Valeur produit** : Chaque supérette peut avoir son identité visuelle. La plateforme reste cohérente par défaut.

**Sprint** : Sprint 6 — Personnalisation visuelle

**Périmètre MVP** : couleurs (5 champs), police et taille de base uniquement. Upload d'image de fond exclu du MVP (ADR-0004).

**User stories** :
- US-010 — Configurer le thème global (admin)
- US-011 — Personnaliser le thème de la supérette lors de l'onboarding
- US-012 — Afficher le storefront avec le thème actif

**Critère de sortie** : La PWA client reflète le thème de la supérette via `GET /api/stores/{storeId}/theme`, avec fallback sur le thème global si aucun `ShopTheme` n'existe.

---

## EPIC-011 — Référentiel produit et catalogue marchand

**Objectif** : Permettre au marchand de trouver des produits existants dans un référentiel global tunisien et de construire son catalogue avec ses propres prix et disponibilités.

**Valeur produit** : Le marchand ne ressaisit pas tout. Les produits connus (Vitalait, Délice, Président…) sont déjà là.

**Sprint** : Sprint 1 — Référentiel produit et catalogue marchand

**User stories** :
- US-013 — Rechercher un produit dans le référentiel global
- US-014 — Ajouter un produit du référentiel à son catalogue
- US-015 — Définir le prix et la disponibilité d'un produit de son catalogue
- US-016 — Proposer un nouveau produit au référentiel

**Critère de sortie** : Un marchand peut rechercher « Lait Vitalait 1L », l'ajouter, fixer son prix à 2,800 TND et le rendre visible.

---

## EPIC-012 — Gestion des créneaux de retrait

**Objectif** : Permettre au marchand de configurer ses créneaux de retrait et de gérer leur capacité.

**Valeur produit** : Le marchand ne reçoit que ce qu'il peut préparer. Les créneaux évitent les conflits de charge.

**Sprint** : Sprint 3 — Parcours marchand

**User stories** :
- US-024 — Configurer les créneaux de retrait de la supérette

**Critère de sortie** : Le marchand peut créer des créneaux horaires avec une capacité maximale. Les clients ne voient que les créneaux disponibles.
