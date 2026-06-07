# Sprint 13 — Catalogue intelligent & réorganisation Launch Readiness

Date de cadrage : 2026-06-07  
Rôles de cadrage : PO + Tech Lead  
Point de départ : **dernier item livré = S13-003 — Score de qualité des références produit**.

## 1. Décision PO / Tech Lead

On ne crée pas un nouveau sprint transverse.

Le Sprint 13 devient le **point de bascule stratégique** : à partir de S13-003, les sprints restants ne préparent plus une bêta publique, mais une **V1 de lancement officiel**.

Décision :

```text
Pas de bêta publique.
Préparer une V1 complète, monétisable, exploitable, mobile-first et supportable.
```

## 2. État Sprint 13

### Déjà livré

```text
S13-001 — Import catalogue par photo assisté IA
S13-002 — Déduplication du référentiel produit
S13-003 — Score de qualité des références produit
```

### Restant Sprint 13

```text
S13-004 — Gouvernance du référentiel
S13-005 — Gestion optimisée des images produits web/mobile
S13-006 — UX de traitement rapide du référentiel admin
S13-007 — Création inline marques et catégories depuis le référentiel admin
S13-008 — Actions en masse et file de priorisation du référentiel admin
```

## 3. Objectif Sprint 13 après S13-003

Objectif PO : rendre le référentiel produit assez fiable pour soutenir le lancement officiel.

Objectif Tech Lead : finaliser les règles, les images et l'outillage admin avant d'autoriser les actions rapides et les actions en masse.

Critère de sortie :

```text
Le référentiel est gouverné, illustré, contrôlable et traitable rapidement par l'admin.
```

## 4. Ordre d'exécution Sprint 13 restant

```text
1. S13-004 — Gouvernance du référentiel
2. S13-005 — Images produits web/mobile
3. S13-006 — Traitement rapide admin
4. S13-007 — Création inline marques/catégories
5. S13-008 — Actions en masse
```

Règle Tech Lead : **S13-008 ne doit pas être lancé avant S13-004**, car les actions en masse doivent respecter une gouvernance claire.

## 5. S13-004 — Gouvernance du référentiel

Issue : #373

### Décision PO

Indispensable avant lancement. Le référentiel est un actif stratégique ; l'IA et les marchands peuvent proposer, mais seul l'admin valide officiellement.

### Décision Tech Lead

Formaliser les rôles, états, transitions, droits, règles d'archivage et règles de fusion avant d'ouvrir les traitements rapides.

### Sortie attendue

```text
docs/product/product-reference-governance.md
```

Le document doit cadrer :

- qui propose ;
- qui valide ;
- qui rejette ;
- qui fusionne ;
- qui archive ;
- ce que l'IA peut faire ;
- ce qu'une référence archivée interdit.

## 6. S13-005 — Images produits web/mobile

Issue : #391

### Décision PO

Les images produits sont nécessaires pour crédibiliser le catalogue avant lancement.

### Décision Tech Lead

L'image officielle appartient à `ProductReference`. Le marchand ne remplace pas librement l'image partagée.

### Sortie attendue

- Upload admin.
- Original conservé.
- Variantes WebP.
- Fallback JPEG.
- Placeholder catégorie.
- URLs exposées dans le catalogue public et l'admin.

## 7. S13-006 — Traitement rapide admin

Issue : #444

### Décision PO

L'admin doit corriger et valider plus vite les références simples avant lancement.

### Décision Tech Lead

Préserver pagination, filtres, recherche, erreurs ligne et drawer pour les cas complexes.

### Sortie attendue

- Grille ou mode de traitement rapide.
- Édition inline.
- Validation ligne.
- Rejet rapide avec raison.
- Erreurs visibles sur la ligne.

## 8. S13-007 — Création inline marques/catégories

Issue : #445

### Décision PO

Éviter les allers-retours admin pendant la validation produit.

### Décision Tech Lead

Réutiliser les services admin existants. Bloquer ou signaler les doublons exacts. Suggérer les noms proches sans fusion automatique.

### Sortie attendue

- Recherche marque/catégorie depuis le champ.
- Création si absente.
- Sélection automatique.
- Erreur API sans perte de saisie.

## 9. S13-008 — Actions en masse

Issue : #446

### Décision PO

Utile avant lancement si le volume référentiel augmente.

### Décision Tech Lead

Aucune action sensible sans résumé, confirmation et explication des lignes non éligibles.

### Sortie attendue

- Vue ou filtre `À traiter`.
- Sélection multiple.
- Résumé avant action.
- Résultat après action.
- Succès / erreurs / ignorés distingués.

## 10. Réorganisation des sprints suivants depuis Sprint 13

À partir de ce Sprint 13, les sprints restants gardent leur numéro historique, mais changent d'intention.

### Sprint 10 — Préproduction technique & activation

Ancienne intention : bêta terrain.

Nouvelle intention : prérequis de lancement officiel.

Issues : #352, #353, #354, #355, #356, #357, #358.

Critère de sortie :

```text
Worker async, monitoring, KPI, QR imprimable, checklist activation et journal minimal prêts.
```

### Sprint 11 — Monétisation & facturation avant lancement

Issues : #359, #360, #361, #362, #363, #364, #365.

Critère de sortie :

```text
Abonnement, phase tarifaire, paiement manuel, relance, suspension douce et réactivation opérationnels.
```

Décision Tech Lead : le cadrage fiscal doit précéder toute facture conforme.

### Sprint 12 — Support & exploitation avant lancement

Issues : #366, #367, #368, #369, #420, #421, #422.

Critère de sortie :

```text
Support capable de diagnostiquer incidents, santé marchand, jobs async et activation supérette depuis l'admin.
```

### Sprint 14 — Mobile Launch Readiness

Issues : #374, #375, #376, #377, #378, #379, #402, #403, #404.

Priorité :

```text
1. PWA client
2. PWA marchand
3. Accessibilité minimum
4. WhatsApp semi-manuel
5. Finitions i18n/accessibilité
6. Push notifications
```

Décision Tech Lead : le push ne doit pas bloquer tout le lancement si PWA + notifications in-app + WhatsApp sont stables.

### Sprint 15 — Valeur commerciale minimale avant lancement

Avant lancement :

```text
S15-001 — Statistiques marchand simples
S15-004 — Promotions simples
S15-005 — CRM léger marchand
```

Après lancement :

```text
S15-002 — Packs produits
S15-003 — Suggestions de Kadhia
```

### Sprint 16 — Post-lancement uniquement

Issues : #386, #387, #388, #389.

Décision PO / Tech Lead : pas d'app native avant usage réel, limites PWA constatées et besoin terrain confirmé.

## 11. Gates CTO avant lancement officiel

### Gate technique

- Worker async actif et supervisé.
- Monitoring jobs disponible.
- Healthcheck OK.
- Logs exploitables.
- Aucun message critique bloqué.

### Gate marchand

- Checklist activation complète.
- QR imprimable.
- Horaires et créneaux configurés.
- Catalogue minimum prêt.
- Commande test passée.
- Retrait test validé.

### Gate client

- PWA client installable.
- Catalogue mobile utilisable.
- Kadhia fluide.
- Suivi commande OK.
- Retrait QR/code OK.
- FR/AR propre sur les écrans visibles.

### Gate business

- Abonnement marchand créé.
- Phase tarifaire claire.
- Paiement manuel enregistrable.
- Relance possible.
- Suspension douce possible.
- Réactivation possible.

### Gate support

- Incident commande traçable.
- Journal marchand consultable.
- Runbook support disponible.
- Vue santé marchand disponible.

## 12. Règle finale

```text
Les anciens numéros de sprint restent.
Le Sprint 13 porte la réorganisation stratégique.
Les sprints suivants sont exécutés en Launch Readiness avant lancement officiel.
Aucun dossier SprintLaunchReadiness séparé ne doit être créé.
```
