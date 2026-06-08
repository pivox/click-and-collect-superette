# Sprint 13 — Catalogue intelligent & qualité référentiel

Date de cadrage : 2026-06-07  
Rôles de cadrage : PO + Tech Lead  
Point de départ de la redéfinition : **S13-003 — Score de qualité des références produit**.  
Correction suite review PR : **S13-005 / #391 est déjà livré dans l'état courant du dépôt**, donc il ne doit pas être replanifié.

## 1. Décision PO / Tech Lead

Sprint 13 ne porte pas la redéfinition globale des sprints suivants.

Sprint 13 reste centré sur le **référentiel produit** et le **traitement catalogue admin**.

La redéfinition stratégique des sprints futurs commence à partir de **Sprint 14** et doit être documentée dans :

```text
docs/Sprint14/README.md
```

## 2. État Sprint 13

### Déjà livré / acquis

```text
S13-001 — Import catalogue par photo assisté IA
S13-002 — Déduplication du référentiel produit
S13-003 — Score de qualité des références produit
S13-005 — Gestion optimisée des images produits web/mobile (#391)
```

### Restant Sprint 13

```text
S13-004 — Gouvernance du référentiel
S13-006 — UX de traitement rapide du référentiel admin
S13-007 — Création inline marques et catégories depuis le référentiel admin
S13-008 — Actions en masse et file de priorisation du référentiel admin
```

## 3. Objectif Sprint 13 restant

Objectif PO : rendre le référentiel produit assez fiable pour soutenir le lancement officiel.

Objectif Tech Lead : finaliser les règles et l'outillage admin avant d'autoriser les actions rapides et les actions en masse.

Critère de sortie :

```text
Le référentiel est gouverné, contrôlable et traitable rapidement par l'admin.
Les images produits sont considérées comme un acquis déjà livré, pas comme un reste à planifier.
```

## 4. Ordre d'exécution Sprint 13 restant

```text
1. S13-004 — Gouvernance du référentiel
2. S13-006 — Traitement rapide admin
3. S13-007 — Création inline marques/catégories
4. S13-008 — Actions en masse
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

### Statut

```text
Livré / acquis dans l'état courant du dépôt.
```

### Décision PO

Ne pas replanifier cette issue comme travail restant. Elle reste mentionnée comme dépendance qualité du catalogue.

### Décision Tech Lead

Vérifier uniquement que les écrans et contrats qui dépendent des images utilisent bien les URLs/variants existants. Pas de nouveau lot image dans Sprint 13 restant.

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

## 10. Règle finale Sprint 13

```text
Sprint 13 reste catalogue / référentiel.
S13-005 est un acquis livré, pas une tâche restante.
La redéfinition des sprints futurs commence à Sprint 14.
Aucun dossier SprintLaunchReadiness séparé ne doit être créé.
```
