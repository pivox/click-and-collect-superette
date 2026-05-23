# TODO — EPICs et User Stories

Ce document centralise les EPICs et User Stories à traiter après les sprints actuels.

Il consolide :

- le backlog global validé avec 15 EPICs ;
- le module abonnements, facturation, paiements et relances ;
- le référentiel produit tunisien neutre ;
- l'import IA depuis photo, ticket, export, fichier, code-barres et URL ;
- la gestion des images produits : stockage, formats, enrichissement, validation admin ;
- le suivi des coûts OpenAI, tokens, quotas et erreurs.

## Principes validés

- Le référentiel produit global reste neutre et ne contient pas de prix, stock, promotion ou disponibilité.
- `product_reference` représente l'identité stable d'un produit.
- `merchant_product` représente le produit activé chez un marchand, avec ses données locales.
- Toute donnée issue d'une source externe, d'un marchand ou d'OpenAI doit passer par observation, candidat, matching, validation ou score suffisant.
- Aucune image externe trouvée automatiquement ne doit être publiée directement sans contrôle.
- OpenAI aide à extraire, structurer, chercher et scorer ; l'application vérifie, stocke, traite et valide.
- Les coûts IA doivent être tracés dès le début.

---

## EPIC-01 — Abonnements & pricing progressif

Objectif : gérer le cycle tarifaire marchand : 3 mois gratuits, 3 mois à 10 DT, puis 50 DT / mois.

### US

- [ ] US-ABO-001 — Créer les plans d'abonnement marchand.
- [ ] US-ABO-002 — Appliquer automatiquement 3 mois gratuits à l'inscription.
- [ ] US-ABO-003 — Appliquer automatiquement 3 mois promotionnels à 10 DT.
- [ ] US-ABO-004 — Passer automatiquement au prix standard de 50 DT / mois.
- [ ] US-ABO-005 — Afficher au marchand son abonnement actuel.
- [ ] US-ABO-006 — Afficher la prochaine date de facturation.
- [ ] US-ABO-007 — Afficher le prochain prix applicable.
- [ ] US-ABO-008 — Prévenir avant passage à la phase promotionnelle.
- [ ] US-ABO-009 — Prévenir avant passage au prix standard.
- [ ] US-ABO-010 — Historiser les changements de phase d'abonnement.
- [ ] US-ABO-011 — Gérer les statuts `trial`, `promo`, `active`, `payment_due`, `grace_period`, `suspended`, `cancelled`.
- [ ] US-ABO-012 — Permettre à l'admin de consulter la phase d'abonnement d'un marchand.

---

## EPIC-02 — Facturation, paiements & relances

Objectif : facturer les marchands, suivre les paiements, relancer doucement et permettre une réactivation rapide.

### US

- [ ] US-BIL-001 — Générer une facture mensuelle marchand.
- [ ] US-BIL-002 — Numéroter les factures.
- [ ] US-BIL-003 — Gérer les statuts facture : `draft`, `issued`, `paid`, `partially_paid`, `overdue`, `cancelled`.
- [ ] US-BIL-004 — Afficher l'historique des factures côté marchand.
- [ ] US-BIL-005 — Afficher l'historique des factures côté admin.
- [ ] US-BIL-006 — Enregistrer un paiement manuel.
- [ ] US-BIL-007 — Valider un paiement côté admin.
- [ ] US-BIL-008 — Gérer les moyens de paiement : espèces, virement, paiement mobile plus tard, carte bancaire plus tard.
- [ ] US-BIL-009 — Gérer les statuts paiement : `pending`, `confirmed`, `failed`, `refunded`, `cancelled`.
- [ ] US-BIL-010 — Détecter les factures impayées.
- [ ] US-BIL-011 — Créer une relance J-7 avant échéance.
- [ ] US-BIL-012 — Créer une relance J-3 avant échéance.
- [ ] US-BIL-013 — Créer une notification J0 lorsque la facture est disponible.
- [ ] US-BIL-014 — Créer une relance J+3 après échéance.
- [ ] US-BIL-015 — Créer une relance J+7 après échéance.
- [ ] US-BIL-016 — Créer une dernière relance J+14 avant suspension.
- [ ] US-BIL-017 — Appliquer une suspension douce à J+21.
- [ ] US-BIL-018 — Garder la boutique visible mais bloquer les nouvelles commandes en cas de retard long.
- [ ] US-BIL-019 — Ne jamais supprimer automatiquement le catalogue, les images, le logo, les commandes ou les statistiques.
- [ ] US-BIL-020 — Réactiver rapidement un marchand après paiement confirmé.
- [ ] US-BIL-021 — Créer un dashboard admin facturation.
- [ ] US-BIL-022 — Afficher marchands gratuits, marchands à 10 DT, marchands à 50 DT.
- [ ] US-BIL-023 — Afficher les revenus mensuels estimés et encaissés.
- [ ] US-BIL-024 — Afficher les relances à faire aujourd'hui.
- [ ] US-BIL-025 — Afficher côté marchand un écran "Mon abonnement".
- [ ] US-BIL-026 — Tracer les actions facture, paiement, relance, suspension et réactivation.

---

## EPIC-03 — Branding marchand : logo, image, thème

Objectif : permettre à une supérette de personnaliser sa boutique publique.

### US

- [ ] US-BRD-001 / US-050 — Ajouter une photo ou un logo de supérette.
- [ ] US-BRD-002 — Modifier le logo depuis le dashboard marchand.
- [ ] US-BRD-003 — Supprimer ou remplacer un logo.
- [ ] US-BRD-004 — Ajouter une image de couverture boutique.
- [ ] US-BRD-005 — Supprimer ou remplacer l'image de couverture.
- [ ] US-BRD-006 — Choisir un thème couleur.
- [ ] US-BRD-007 — Prévisualiser la boutique publique.
- [ ] US-BRD-008 — Valider les contraintes image : type, taille, poids, dimensions.
- [ ] US-BRD-009 — Stocker les images de branding dans un dossier séparé des images produits.
- [ ] US-BRD-010 — Tracer la source de l'image : upload marchand ou admin.

---

## EPIC-04 — Import IA & catalogue marchand illimité

Objectif : accélérer la création de catalogue marchand et enrichir le référentiel produit grâce à l'IA, aux sources ouvertes, aux URL et aux contributions marchands.

### US — Import marchand assisté

- [ ] US-IA-001 — Importer des produits depuis une photo de ticket de caisse.
- [ ] US-IA-002 — Importer des produits depuis une photo de rayon.
- [ ] US-IA-003 — Importer des produits depuis une photo d'export caisse.
- [ ] US-IA-004 — Importer des produits depuis un fichier.
- [ ] US-IA-005 — Scanner un code-barres produit.
- [ ] US-IA-006 — Saisir manuellement un produit rapidement.
- [ ] US-IA-007 — Envoyer le contenu utile à OpenAI pour extraction structurée.
- [ ] US-IA-008 — Recevoir un JSON structuré contenant nom, marque, catégorie, quantité, unité, pack, code-barres, prix éventuel et score de confiance.
- [ ] US-IA-009 — Créer une `merchant_product_contribution` pour chaque produit détecté depuis un marchand.
- [ ] US-IA-010 — Afficher au marchand les produits détectés.
- [ ] US-IA-011 — Permettre au marchand de corriger les produits détectés.
- [ ] US-IA-012 — Permettre au marchand de supprimer une ligne détectée.
- [ ] US-IA-013 — Permettre au marchand de valider les produits détectés en masse.
- [ ] US-IA-014 — Activer les produits validés dans `merchant_product`.
- [ ] US-IA-015 — Enrichir le référentiel si un produit validé n'existe pas encore.

### US — Import depuis URLs données

- [ ] US-IA-016 — Permettre à l'admin de coller une URL source.
- [ ] US-IA-017 — Permettre à l'admin de coller plusieurs URLs en batch.
- [ ] US-IA-018 — Créer un `product_import_batch`.
- [ ] US-IA-019 — Créer un `product_import_url` par URL fournie.
- [ ] US-IA-020 — Récupérer la page côté backend.
- [ ] US-IA-021 — Nettoyer le HTML et extraire le texte utile.
- [ ] US-IA-022 — Extraire les URLs d'images candidates présentes dans la page.
- [ ] US-IA-023 — Envoyer le contenu nettoyé à OpenAI.
- [ ] US-IA-024 — Demander à OpenAI une réponse JSON conforme à un schéma.
- [ ] US-IA-025 — Créer un `product_extraction_result` avec le payload IA brut.
- [ ] US-IA-026 — Créer une `external_product_observation` par produit extrait d'une URL externe.
- [ ] US-IA-027 — Ne jamais intégrer automatiquement un produit externe dans `product_reference` sans validation ou score suffisant.
- [ ] US-IA-028 — Permettre à l'admin de relancer un import URL échoué.
- [ ] US-IA-029 — Afficher le statut de chaque URL : pending, fetched, extracted, failed, ignored.
- [ ] US-IA-030 — Afficher les erreurs HTTP, parsing et OpenAI.

### US — Matching, déduplication et référentiel

- [ ] US-IA-031 — Matcher d'abord par code-barres si disponible.
- [ ] US-IA-032 — Matcher ensuite par marque + nom normalisé + quantité + unité + pack + marché.
- [ ] US-IA-033 — Créer un `product_candidate` si aucun produit existant ne correspond.
- [ ] US-IA-034 — Détecter les doublons probables.
- [ ] US-IA-035 — Permettre à l'admin de fusionner deux candidats.
- [ ] US-IA-036 — Permettre à l'admin de rejeter un candidat.
- [ ] US-IA-037 — Permettre à l'admin de promouvoir un candidat en `product_reference`.
- [ ] US-IA-038 — Garder chaque format comme référence différente : 0.5L, 1L, 1.5L, pack 6x1.5L.
- [ ] US-IA-039 — Ne pas stocker dans `product_reference` les prix, promotions, stock, disponibilité ou emplacement rayon.
- [ ] US-IA-040 — Stocker les prix éventuels uniquement côté marchand.

### US — Enrichissement images produits

- [ ] US-IA-041 — Identifier les produits sans image.
- [ ] US-IA-042 — Permettre à l'admin de lancer la recherche image sur un produit.
- [ ] US-IA-043 — Permettre à l'admin de lancer la recherche image en batch.
- [ ] US-IA-044 — Créer un job planifié pour traiter un nombre limité de produits sans image.
- [ ] US-IA-045 — Chercher une image candidate via source ouverte ou source autorisée.
- [ ] US-IA-046 — Utiliser OpenAI pour aider à chercher, comparer ou scorer une image candidate.
- [ ] US-IA-047 — Créer un `product_image_candidate` pour chaque image possible.
- [ ] US-IA-048 — Afficher à l'admin l'aperçu image, la source, l'URL, le score et le produit lié.
- [ ] US-IA-049 — Permettre à l'admin de valider une image candidate.
- [ ] US-IA-050 — Permettre à l'admin de rejeter une image candidate avec motif.
- [ ] US-IA-051 — Télécharger uniquement l'image validée.
- [ ] US-IA-052 — Vérifier le type MIME réel de l'image.
- [ ] US-IA-053 — Refuser les formats non autorisés.
- [ ] US-IA-054 — Refuser les images trop lourdes.
- [ ] US-IA-055 — Calculer un checksum SHA-256 pour éviter les doublons.
- [ ] US-IA-056 — Redimensionner l'image en plusieurs formats.
- [ ] US-IA-057 — Convertir ou optimiser l'image pour l'affichage web.
- [ ] US-IA-058 — Stocker l'image dans le filesystem en développement.
- [ ] US-IA-059 — Prévoir une abstraction pour migrer vers S3 ou compatible plus tard.
- [ ] US-IA-060 — Marquer le produit comme enrichi en image après validation.
- [ ] US-IA-061 — Ne jamais hotlinker directement une image externe.
- [ ] US-IA-062 — Ne jamais générer une fausse image officielle d'un produit réel.
- [ ] US-IA-063 — Autoriser un placeholder générique seulement si aucune vraie image fiable n'existe.

### US — Stockage et formats images produits

- [ ] US-IMG-001 — Créer une entité `product_image`.
- [ ] US-IMG-002 — Associer une image à `product_reference` ou `merchant_product`.
- [ ] US-IMG-003 — Conserver l'URL originale et le nom de source si image externe.
- [ ] US-IMG-004 — Conserver largeur, hauteur, MIME type, poids et checksum.
- [ ] US-IMG-005 — Gérer les statuts image : `missing`, `search_pending`, `candidate_found`, `downloaded`, `processed`, `needs_review`, `validated`, `rejected`, `failed`.
- [ ] US-IMG-006 — Gérer les sources image : `merchant_upload`, `open_food_facts`, `authorized_external_source`, `admin_url`, `ai_generated_placeholder`.
- [ ] US-IMG-007 — Stocker les fichiers produits sous un chemin dédié, par exemple `var/storage/products/{productId}/original.{ext}` en privé puis exposition contrôlée.
- [ ] US-IMG-008 — Générer un format miniature pour listing.
- [ ] US-IMG-009 — Générer un format carte produit.
- [ ] US-IMG-010 — Générer un format détail produit.
- [ ] US-IMG-011 — Prévoir un format original conservé ou archivé.
- [ ] US-IMG-012 — Nettoyer les anciennes variantes lorsqu'une image est remplacée.
- [ ] US-IMG-013 — Ajouter une commande Symfony de retraitement des images.
- [ ] US-IMG-014 — Ajouter un job Messenger de traitement asynchrone image.
- [ ] US-IMG-015 — Ajouter des limites de batch pour éviter de saturer disque, CPU ou IA.

---

## EPIC-05 — Onboarding marchand assisté

Objectif : guider une supérette jusqu'à une boutique publiable.

### US

- [ ] US-ONB-001 / US-054 — Créer l'onboarding marchand.
- [ ] US-ONB-002 — Créer un assistant de première configuration boutique.
- [ ] US-ONB-003 — Renseigner les informations de base de la supérette.
- [ ] US-ONB-004 — Configurer les horaires d'ouverture.
- [ ] US-ONB-005 — Configurer les créneaux de retrait.
- [ ] US-ONB-006 — Ajouter les premiers produits.
- [ ] US-ONB-007 — Importer le catalogue via IA pendant l'onboarding.
- [ ] US-ONB-008 — Ajouter logo et image de couverture pendant l'onboarding.
- [ ] US-ONB-009 — Afficher une checklist de progression.
- [ ] US-ONB-010 — Marquer la boutique comme prête à publier.
- [ ] US-ONB-011 — Permettre à l'admin ou commercial d'aider un marchand pendant l'onboarding.

---

## EPIC-06 — Dashboard statistiques marchand

Objectif : donner au marchand une vision simple de son activité.

### US

- [ ] US-STAT-001 — Afficher le nombre de commandes.
- [ ] US-STAT-002 — Afficher le chiffre d'affaires estimé.
- [ ] US-STAT-003 — Afficher les produits les plus commandés.
- [ ] US-STAT-004 — Afficher le taux d'acceptation des commandes.
- [ ] US-STAT-005 — Afficher le taux de refus des commandes.
- [ ] US-STAT-006 — Afficher les commandes par période.
- [ ] US-STAT-007 — Afficher les clients récurrents.
- [ ] US-STAT-008 — Exporter des statistiques simples.
- [ ] US-STAT-009 — Afficher les produits souvent indisponibles.
- [ ] US-STAT-010 — Afficher les alertes catalogue incomplet ou produits sans image.

---

## EPIC-07 — Packs produits

Objectif : permettre aux marchands de vendre des ensembles de produits.

### US

- [ ] US-PACK-001 — Créer un pack de produits.
- [ ] US-PACK-002 — Ajouter plusieurs produits dans un pack.
- [ ] US-PACK-003 — Définir un prix pack côté marchand.
- [ ] US-PACK-004 — Activer ou désactiver un pack.
- [ ] US-PACK-005 — Afficher les packs côté client.
- [ ] US-PACK-006 — Ajouter un pack au panier.
- [ ] US-PACK-007 — Gérer la disponibilité d'un pack selon les produits qui le composent.
- [ ] US-PACK-008 — Afficher les packs dans le dashboard marchand.

---

## EPIC-08 — Suggestions intelligentes panier

Objectif : augmenter le panier moyen et aider le client à compléter ses achats.

### US

- [ ] US-SUG-001 — Suggérer des produits complémentaires.
- [ ] US-SUG-002 — Suggérer les produits souvent achetés ensemble.
- [ ] US-SUG-003 — Suggérer les produits populaires de la supérette.
- [ ] US-SUG-004 — Suggérer un remplacement en cas de rupture.
- [ ] US-SUG-005 — Afficher les suggestions dans le panier.
- [ ] US-SUG-006 — Mesurer les clics sur suggestions.
- [ ] US-SUG-007 — Mesurer les ajouts panier depuis suggestions.
- [ ] US-SUG-008 — Permettre à l'admin de désactiver les suggestions intelligentes si nécessaire.

---

## EPIC-09 — Sous-domaine boutique

Objectif : donner une URL publique simple et identifiable à chaque supérette.

### US

- [ ] US-DOM-001 — Générer une URL publique boutique.
- [ ] US-DOM-002 — Gérer un slug boutique unique.
- [ ] US-DOM-003 — Associer une boutique à un sous-domaine.
- [ ] US-DOM-004 — Vérifier la disponibilité du sous-domaine.
- [ ] US-DOM-005 — Rediriger le client vers la bonne boutique.
- [ ] US-DOM-006 — Gérer la désactivation du sous-domaine si boutique suspendue.
- [ ] US-DOM-007 — Permettre à l'admin de modifier le slug si nécessaire.
- [ ] US-DOM-008 — Prévoir une future option domaine personnalisé.

---

## EPIC-10 — Publicité & boosts sponsorisés

Objectif : préparer un futur levier de monétisation via visibilité sponsorisée.

### US

- [ ] US-ADS-001 — Créer un boost produit.
- [ ] US-ADS-002 — Créer un boost boutique.
- [ ] US-ADS-003 — Définir la période de boost.
- [ ] US-ADS-004 — Afficher des produits sponsorisés.
- [ ] US-ADS-005 — Afficher des boutiques sponsorisées.
- [ ] US-ADS-006 — Créer un dashboard admin des boosts.
- [ ] US-ADS-007 — Mesurer impressions et clics.
- [ ] US-ADS-008 — Désactiver automatiquement un boost expiré.
- [ ] US-ADS-009 — Tracer qui a créé ou désactivé un boost.

---

## EPIC-11 — PWA / application dédiée

Objectif : préparer le canal mobile client et marchand.

### US

- [ ] US-APP-001 / US-059 — Rendre la PWA installable et prévoir un mode offline minimal.
- [ ] US-APP-002 — Préparer l'application iOS client.
- [ ] US-APP-003 — Préparer l'application Android client.
- [ ] US-APP-004 — Ajouter les notifications push client.
- [ ] US-APP-005 — Ajouter les notifications push marchand.
- [ ] US-APP-006 — Prévoir un mode offline minimal catalogue / panier.
- [ ] US-APP-007 — Créer un écran d'installation application.
- [ ] US-APP-008 — Documenter ce qui reste web, PWA, iOS ou Android.
- [ ] US-APP-009 — Prévoir le suivi des versions mobiles.

---

## EPIC-12 — Exports & reporting

Objectif : permettre aux marchands et admins d'extraire les données utiles.

### US

- [ ] US-EXP-001 / US-061 — Exporter les commandes en CSV.
- [ ] US-EXP-002 — Exporter les produits marchand en CSV.
- [ ] US-EXP-003 — Exporter les factures en CSV.
- [ ] US-EXP-004 — Exporter un rapport ventes par période.
- [ ] US-EXP-005 — Exporter un rapport produits indisponibles.
- [ ] US-EXP-006 — Exporter les données pour admin ou commercial.
- [ ] US-EXP-007 — Filtrer les exports par date.
- [ ] US-EXP-008 — Tracer les exports sensibles.

---

## EPIC-13 — Quotas IA & suivi coûts

Objectif : maîtriser les coûts OpenAI et éviter les traitements illimités.

### US

- [ ] US-AICOST-001 — Enregistrer chaque appel OpenAI.
- [ ] US-AICOST-002 — Stocker le modèle utilisé.
- [ ] US-AICOST-003 — Stocker les tokens d'entrée.
- [ ] US-AICOST-004 — Stocker les tokens de sortie.
- [ ] US-AICOST-005 — Estimer le coût de chaque appel.
- [ ] US-AICOST-006 — Associer un appel IA à un marchand si applicable.
- [ ] US-AICOST-007 — Associer un appel IA à un batch d'import si applicable.
- [ ] US-AICOST-008 — Définir un quota IA par marchand.
- [ ] US-AICOST-009 — Définir un quota IA global par jour.
- [ ] US-AICOST-010 — Bloquer ou ralentir un import IA si quota dépassé.
- [ ] US-AICOST-011 — Récupérer les informations disponibles sur les limites ou tokens restants dans les réponses API.
- [ ] US-AICOST-012 — Afficher un dashboard admin des coûts IA.
- [ ] US-AICOST-013 — Afficher le coût par batch d'import.
- [ ] US-AICOST-014 — Journaliser les erreurs OpenAI.
- [ ] US-AICOST-015 — Relancer un batch IA échoué.
- [ ] US-AICOST-016 — Prévoir un mode dry-run ou estimation avant gros batch.
- [ ] US-AICOST-017 — Arrêter proprement les jobs IA si le budget quotidien est atteint.

---

## EPIC-14 — Administration commerciale

Objectif : donner à l'admin et au commercial les outils de pilotage marchand.

### US

- [ ] US-ADM-001 / US-028 — Gérer les comptes marchands.
- [ ] US-ADM-002 / US-029 — Superviser le référentiel produit.
- [ ] US-ADM-003 / US-030 — Valider les propositions produits.
- [ ] US-ADM-004 / US-055 — Télécharger le QR code marchand.
- [ ] US-ADM-005 — Affecter un commercial à un marchand.
- [ ] US-ADM-006 — Suivre les marchands à relancer.
- [ ] US-ADM-007 — Voir les boutiques actives et inactives.
- [ ] US-ADM-008 — Filtrer les marchands par ville.
- [ ] US-ADM-009 — Filtrer les marchands par statut abonnement.
- [ ] US-ADM-010 — Filtrer les marchands par statut boutique.
- [ ] US-ADM-011 — Historiser les actions admin sur marchand.
- [ ] US-ADM-012 — Gérer les demandes de support marchand.
- [ ] US-ADM-013 — Afficher les produits candidats à valider.
- [ ] US-ADM-014 — Afficher les images candidates à valider.
- [ ] US-ADM-015 — Afficher les imports URL en cours ou échoués.
- [ ] US-ADM-016 — Afficher les coûts IA par marchand ou par batch.

---

## EPIC-15 — Conformité, consentement & audit

Objectif : préparer la production, la conformité et la traçabilité.

### US

- [ ] US-COMP-001 / US-060 — Améliorer l'accessibilité WCAG.
- [ ] US-COMP-002 / US-062 — Gérer la conservation et la suppression des données.
- [ ] US-COMP-003 / US-063 — Créer un audit trail admin.
- [ ] US-COMP-004 — Gérer le consentement client.
- [ ] US-COMP-005 — Gérer le consentement marchand.
- [ ] US-COMP-006 — Journaliser les actions sensibles.
- [ ] US-COMP-007 — Supprimer ou anonymiser un compte client.
- [ ] US-COMP-008 — Exporter les données personnelles.
- [ ] US-COMP-009 / US-058 — Gérer la fermeture définitive d'une supérette.
- [ ] US-COMP-010 — Ne pas supprimer automatiquement les données marchand lors d'une simple suspension.
- [ ] US-COMP-011 — Tracer les validations de produits, images et imports IA.
- [ ] US-COMP-012 — Conserver les sources des observations externes.
- [ ] US-COMP-013 — Éviter la copie massive de catalogues concurrents.
- [ ] US-COMP-014 — Éviter la copie des descriptions longues, prix, promotions et images non autorisées.
- [ ] US-COMP-015 — Respecter les conditions d'utilisation et les règles d'accès des sources externes.

---

## Entités techniques à prévoir

### Référentiel et import produit

- [ ] `external_product_observation`
- [ ] `merchant_product_contribution`
- [ ] `product_candidate`
- [ ] `product_reference`
- [ ] `merchant_product`
- [ ] `product_import_batch`
- [ ] `product_import_url`
- [ ] `product_extraction_result`

### Images produits

- [ ] `product_image`
- [ ] `product_image_candidate`
- [ ] `product_image_variant`

### Facturation

- [ ] `subscription_plan`
- [ ] `merchant_subscription`
- [ ] `billing_phase`
- [ ] `invoice`
- [ ] `invoice_line`
- [ ] `payment`
- [ ] `payment_method`
- [ ] `payment_reminder`
- [ ] `billing_event`
- [ ] `merchant_billing_account`

### OpenAI et jobs

- [ ] `ai_call_log`
- [ ] `ai_usage_quota`
- [ ] `ai_import_batch_cost`
- [ ] Message Symfony `EnrichMissingProductImagesMessage`
- [ ] Message Symfony `FindProductImageMessage`
- [ ] Message Symfony `ProcessProductImageCandidateMessage`
- [ ] Message Symfony `ImportProductsFromUrlsMessage`
- [ ] Message Symfony `ExtractProductsFromUrlMessage`
- [ ] Commande `app:product-images:enrich-missing`
- [ ] Commande `app:products:import-urls`

---

## Priorité recommandée

1. [ ] EPIC-04 — Import IA & catalogue marchand illimité.
2. [ ] EPIC-13 — Quotas IA & suivi coûts.
3. [ ] EPIC-14 — Administration commerciale.
4. [ ] EPIC-03 — Branding marchand : logo, image, thème.
5. [ ] EPIC-02 — Facturation, paiements & relances.
6. [ ] EPIC-01 — Abonnements & pricing progressif.

---

## Hors MVP immédiat

- [ ] Paiement carte bancaire complet.
- [ ] PDF fiscal complet.
- [ ] Intégration comptable.
- [ ] Relances SMS payantes automatiques.
- [ ] Génération d'images officielles produit par IA.
- [ ] Copie massive de catalogues concurrents.
- [ ] Publication automatique d'images externes sans validation.
