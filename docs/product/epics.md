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

**Sprint** : Sprint 2 (intégré), Sprint 7 (affinage), Sprint 14 (câblage AR complet livré côté client)

**User stories** :
- US-008 — Basculer la langue de l'interface
- US-093 — Arabe / RTL câblé dans l'application *(livré côté client via S14-004 / #401 ; #377 fermée)*

**Critère de sortie** : L'interface client bascule entre français et arabe. Les montants sont affichés en TND. Le mode RTL fonctionne sur les vues principales client. Le marchand dispose d'un contexte langue FR/AR léger ; les extensions PWA/push/WCAG restent dans EPIC-022.

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
- US-041 — Afficher les photos des produits dans le catalogue *(livrée S13-005 côté code/docs ; #391 encore ouverte GitHub au 4 juin 2026)*

**Critère de sortie** : Un marchand peut rechercher « Lait Vitalait 1L », l'ajouter, fixer son prix à 2,800 TND, le rendre visible et l'afficher avec une image produit optimisée quand elle existe.

---

## EPIC-012 — Gestion des créneaux de retrait

**Objectif** : Permettre au marchand de configurer ses créneaux de retrait et de gérer leur capacité.

**Valeur produit** : Le marchand ne reçoit que ce qu'il peut préparer. Les créneaux évitent les conflits de charge.

**Sprint** : Sprint 3 — Parcours marchand

**User stories** :
- US-024 — Configurer les créneaux de retrait de la supérette

**Critère de sortie** : Le marchand peut créer des créneaux horaires avec une capacité maximale. Les clients ne voient que les créneaux disponibles.

---

## EPIC-013 — Compte client

**Objectif** : Permettre à un nouveau visiteur de créer un compte client, de se connecter et de gérer son profil.

**Valeur produit** : Sans inscription, aucune Kadhia, aucune commande. C'est le prérequis de tout le parcours client.

**Sprint** : Sprint Auth (avant Sprint 2)

**User stories** :
- US-034 — S'inscrire en tant que client
- US-035 — Consulter et modifier son profil client
- US-046 — Réinitialiser son mot de passe oublié

**Critère de sortie** : Un visiteur peut créer un compte, se connecter avec un JWT valide, consulter/modifier ses informations personnelles et retrouver l'accès à son compte après un mot de passe oublié.

---

## EPIC-014 — Notifications MVP

**Objectif** : Informer le client et le marchand des événements importants (commande acceptée, prête, nouvelle commande soumise) sans push mobile dans le MVP.

**Valeur produit** : Le client sait quand venir chercher sa Kadhia. Le marchand sait quand traiter une commande. Sans notification, les deux doivent recharger l'application manuellement.

**Sprint** : Sprint 4 — Retrait sécurisé

**User stories** :
- US-038 — Recevoir des notifications sur l'évolution de sa commande (client)
- US-039 — Recevoir des notifications pour les nouvelles commandes (marchand)
- US-064 — Rappel de retrait avant expiration du créneau

**Critère de sortie** : Une notification est créée à chaque transition de statut clé. Le client et le marchand peuvent lister leurs notifications et les marquer comme lues. Le client reçoit un rappel automatique 1 heure avant son créneau de retrait si sa commande est `ready`.

---

# Epics post-MVP — Mise sur le marché (Sprints 10-16)

> Ces epics couvrent la phase go-to-market au-delà du cœur MVP (Sprints 0-9 livrés sur `main`).
> Mapping détaillé dans `docs/roadmap/mvp-roadmap.md`. Chaque US est suivie sur GitHub (tickets #352-#380 et #382-#389).
>
> État au 4 juin 2026 : **EPIC-015 — Fiabilité & observabilité production** est livré. Dans EPIC-016, le QR magasin imprimable et la checklist d'activation supérette (#356 via PR #412) sont livrés. Sprint 10 est clôturable ; #357 est à reporter et #358 à fermer comme non nécessaire.

## EPIC-015 — Fiabilité & observabilité production

**Objectif** : Garantir que les automatisations différées et l'application tiennent en production, et mesurer l'usage réel dès la bêta.

**Valeur produit** : Une bêta terrain fiable et pilotée par les données, pas au doigt mouillé.

**Sprint** : Sprint 10 — Durcissement bêta

**User stories** :
- US-067 — Livré : valider en production le worker async (runbook + checklist d'exploitation)
- US-068 — Livré : monitoring des jobs asynchrones (santé worker + file)
- US-069 — Livré : métriques bêta (KPI terrain)

**Critère de sortie** : atteint — worker async supervisé et monitoré, KPI terrain mesurés (commandes, taux d'acceptation, activation supérette).

**Livraison** : #352, #353 et #354 fermées.

---

## EPIC-016 — Activation terrain des supérettes

**Objectif** : Outiller l'activation d'une supérette pilote : QR physique, vérification de complétude, suivi opérationnel.

**Valeur produit** : On n'active en bêta que des supérettes réellement opérationnelles.

**Sprint** : Sprint 10 — Durcissement bêta

**User stories** :
- US-070 — Livré : QR magasin imprimable (PNG / PDF)
- US-071 — Livré : checklist d'activation supérette (gate de mise en bêta)
- US-072 — À reporter : journal opérationnel marchand (vue minimale), non bloquant Sprint 10 et plus cohérent avec le sprint support/exploitation terrain
- US-073 — À fermer comme non nécessaire : décision bêta FR-only vs FR+AR absorbée par les livraisons FR/AR client (#401) et préférence langue marchand (#395)

**Critère de sortie** : atteint pour Sprint 10 — le marchand affiche un QR imprimé en magasin et l'admin dispose d'une checklist d'activation avant bêta. Le journal opérationnel marchand complet relève du support terrain ultérieur.

---

## EPIC-017 — Abonnement & monétisation

**Objectif** : Transformer l'application en produit monétisable par abonnement marchand (gratuit → promo → standard).

**Valeur produit** : La plateforme génère du revenu récurrent. La règle « pas de paiement en ligne » du MVP concerne le paiement *client de la commande*, pas l'abonnement *plateforme marchand*.

**Sprint** : Sprint 11 — Activation commerciale

**User stories** :
- US-074 — Module abonnement marchand (`Subscription`)
- US-075 — Statuts : séparer lifecycle et phase tarifaire
- US-076 — Reçu / facture mensuelle *(à cadrer fiscalement)*
- US-077 — Paiement manuel (espèces / virement) + validation admin

**Critère de sortie** : Un marchand a un abonnement avec cycle de vie clair, des factures et un encaissement manuel tracé.

---

## EPIC-018 — Recouvrement & communication sortante

**Objectif** : Relancer les marchands en retard via un canal qu'ils consultent, suspendre en douceur, et offrir un contact WhatsApp contextualisé.

**Valeur produit** : Réduire le churn de paiement sans casser la relation ; un canal sortant fiable (l'email n'existe pas encore au-delà de `@mail()` natif).

**Sprint** : Sprint 11 (recouvrement) · Sprint 14 (WhatsApp)

**User stories** :
- US-078 — Infra email + relances de paiement (échéancier J-7 → J+21)
- US-079 — Suspension douce et réactivation
- US-094 — WhatsApp semi-manuel (client + marchand) *(Sprint 14)*

**Critère de sortie** : Un marchand est relancé par email, suspendu en douceur (catalogue conservé) puis réactivé après paiement ; le contact WhatsApp est contextualisé et tracé.

---

## EPIC-019 — Onboarding catalogue rapide

**Objectif** : Permettre au marchand de constituer son catalogue vite, sans saisie produit par produit.

**Valeur produit** : Levier de conversion essai → payant : un catalogue rapide à monter retient le marchand.

**Sprint** : Sprint 11 (CSV / scan) · Sprint 13 (photo IA)

**User stories** :
- US-080 — Import CSV + scan code-barres (onboarding minimum) *(Sprint 11)*
- US-081 — Import catalogue par photo assisté IA *(Sprint 13)*

**Critère de sortie** : Un marchand crée un catalogue exploitable par fichier, scan ou photo, en réutilisant le bulk multi-format et l'infra IA existants.

---

## EPIC-020 — Qualité & gouvernance du référentiel

**Objectif** : Garder un référentiel produit propre et gouverné à mesure que les imports massifs l'alimentent.

**Valeur produit** : Un référentiel fiable est la base partagée par tous les marchands.

**Sprint** : Sprint 13 — Catalogue intelligent

**User stories** :
- US-082 — Déduplication du référentiel (workflow admin)
- US-083 — Score de qualité des références produit
- US-084 — Gouvernance du référentiel (rôles, workflow, droits)

**Critère de sortie** : Les doublons sont fusionnés (priorité code-barres), chaque référence porte un score qualité, et la gouvernance est documentée.

---

## EPIC-021 — Support & exploitation terrain

**Objectif** : Donner à l'admin les outils pour gérer les problèmes réels sans toucher à la base.

**Valeur produit** : L'équipe traite incidents et cas limites de façon homogène et traçable.

**Sprint** : Sprint 12 — Support & exploitation

**User stories** :
- US-085 — Incidents commande (module structuré)
- US-086 — Backoffice support (consultation / traitement)
- US-087 — Journal opérationnel marchand complet + vue santé *(absorbe le report de #357)*
- US-088 — Process manuel d'exploitation terrain (runbook)

**Critère de sortie** : L'admin consulte, filtre, annote et clôture des incidents ; la santé de chaque marchand est visible ; les procédures sont écrites.

---

## EPIC-022 — Expérience mobile PWA

**Objectif** : Transformer le web responsive en PWA installable, mobile-first, avec notifications push et accessibilité de base.

**Valeur produit** : Une vraie expérience mobile terrain pour client et marchand (PWA/WCAG reportés post-Sprint 7).

**Sprint** : Sprint 14 — PWA & notifications

**User stories** :
- US-089 — PWA client (installable, mobile-first) *(#374 ouverte)*
- US-090 — PWA marchand (installable, terrain) *(#375 ouverte)*
- US-091 — Push notifications (client + marchand) *(#376 ouverte)*
- US-092 — Accessibilité minimum (WCAG de base) *(#379 ouverte)*

**Critère de sortie** : Client et marchand installent l'app sur mobile et reçoivent des notifications push. *Limite : Web Push iOS seulement sur Safari 16.4+ et PWA installée.* L'arabe/RTL client n'est plus le bloc principal de cet epic : US-093 a été livrée via #401.

---

## EPIC-023 — Croissance commerciale

**Objectif** : Augmenter usage, panier moyen, rétention et valeur perçue de l'abonnement.

**Valeur produit** : Aider les marchands à vendre plus et l'équipe à les retenir.

**Sprint** : Sprint 15 — Croissance

**User stories** :
- US-095 — Statistiques marchand avancées
- US-096 — Packs produits
- US-097 — Suggestions de Kadhia (souvent achetés / récents / favoris)
- US-098 — Promotions simples (prix barré, échéance)
- US-099 — Suivi commercial (CRM léger des marchands)

**Critère de sortie** : Le marchand pilote ses ventes, propose packs et promotions ; l'équipe suit la relation commerciale.

---

## EPIC-024 — Applications natives

**Objectif** : Industrialiser les parcours validés par la PWA en apps natives, après preuve terrain.

**Valeur produit** : Performance et notifications fiables une fois la traction prouvée, sans réinventer le produit.

**Sprint** : Sprint 16 — Apps natives

**User stories** :
- US-100 — App native Android marchand
- US-101 — App native Android client
- US-102 — App native iOS client
- US-103 — App native iOS marchand *(si besoin confirmé)*

**Critère de sortie** : Les apps natives reprennent les parcours validés par la PWA, dans l'ordre Android marchand → Android client → iOS client → iOS marchand.
