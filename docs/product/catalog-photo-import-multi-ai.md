# Import photo IA — catalogue marchand et référentiel admin

Date de cadrage : 2026-10-02  
Statut : conception cible, à implémenter par les issues #637 à #649.

## 1. Objectif

La fonctionnalité transforme des images en propositions de produits sans donner à l’IA le droit de modifier seule les données métier.

Deux usages partagent le même socle technique mais gardent des règles métier différentes :

- **Marchand** : photographier ses rayons pour préremplir le catalogue de sa supérette, avec un forfait de 10 photos par `Shop`.
- **Admin référentiel** : uploader des images sans quota commercial de photos pour préparer, créer, compléter ou corriger le référentiel `ProductReference`.

Le référentiel reste la source commune d’identité produit. Le catalogue marchand reste la source des données commerciales propres à la supérette.

## 2. Schéma fonctionnel de référence

Ce schéma doit être conservé comme représentation synthétique du module :

```text
                           IMAGES
                           │
             ┌─────────────┴─────────────┐
             │                           │
          MARCHAND                     ADMIN
             │                           │
      quota 10 photos             pas de quota photo
             │                           │
      photos de rayons          produits / emballages /
             │                  rayons / codes-barres
             │                           │
             └──────────┬────────────────┘
                        ↓
                extraction IA
                        ↓
            matching ProductReference
                        │
          ┌─────────────┴──────────────┐
          │                            │
      MARCHAND                       ADMIN
          │                            │
 propositions catalogue       proposition référentiel
          │                            │
 correction + prix          ancien ↔ proposé + preuves
          │                            │
 validation                 validation champ par champ
          │                            │
 MerchantProduct              ProductReference
```

## 3. Principes non négociables

1. `ProductReference` décrit l’identité partagée du produit.
2. `MerchantProduct` décrit l’offre d’une supérette : prix, disponibilité, visibilité et données commerciales.
3. L’IA extrait et propose ; un utilisateur autorisé valide.
4. Une valeur non lisible reste inconnue. Ne jamais inventer volume, unité, pack, variante, GTIN ou prix.
5. Une photo de rayon ne permet pas de calculer un stock fiable.
6. Les images de travail sont privées et distinctes des images catalogue publiques.
7. Le marchand ne modifie jamais directement une `ProductReference`.
8. L’admin peut modifier le référentiel, mais uniquement par une action explicite et auditée.
9. Le quota marchand de 10 photos ne s’applique jamais à l’admin.
10. L’absence de quota admin n’annule pas les limites techniques et les plafonds de dépense IA.

## 4. Parcours marchand

### 4.1 Entrée

Depuis `Catalogue`, afficher une action visible :

```text
[ + Ajouter un produit ]   [ ✨ Importer mes rayons ]
```

Le parcours `Importer mes rayons` affiche immédiatement :

- le solde : `7 / 10 photos restantes` ;
- une explication courte : « Photographiez vos rayons, vérifiez les produits détectés puis ajoutez-les à votre catalogue » ;
- les règles de capture ;
- le bouton caméra / sélection de fichiers.

Le quota est partagé entre tous les comptes autorisés de la même supérette.

### 4.2 Capture

Une session accepte plusieurs photos dans la limite du solde.

Chaque vignette affiche :

- aperçu ;
- état `Prête`, `À remplacer`, `Upload en cours`, `Erreur` ;
- action supprimer avant lancement ;
- action remplacer ;
- rotation si nécessaire.

Le marchand ne doit pas voir les quatre fournisseurs du benchmark. Le détail technique appartient à l’admin.

### 4.3 Analyse

Après `Analyser mes photos` :

```text
Photos reçues
→ Analyse en cours
→ Références recherchées
→ Résultat prêt à vérifier
```

Le traitement est asynchrone. Le marchand peut quitter l’écran et reprendre plus tard.

Le résultat primaire configuré peut être montré dès qu’il est utilisable ; les autres fournisseurs du pilote ne bloquent pas le parcours marchand.

### 4.4 Vérification

Une seule liste de propositions est affichée.

Chaque ligne montre :

- miniature de la photo ou du recadrage source ;
- produit détecté ;
- marque ;
- variante ;
- quantité / unité ;
- pack ;
- état de matching ;
- référence proposée ;
- alternatives si ambiguïté ;
- statut `Déjà dans votre catalogue` ;
- prix marchand à confirmer ;
- disponibilité et visibilité.

États de matching visibles :

- `Référence trouvée` ;
- `À confirmer` ;
- `Absent du référentiel` ;
- `Non identifiable` ;
- `Déjà dans votre catalogue`.

Le marchand peut :

- accepter la référence proposée ;
- choisir une alternative ;
- rechercher une autre référence ;
- corriger un produit local ;
- supprimer une fausse détection ;
- ajouter un produit oublié ;
- saisir ou confirmer le prix ;
- enregistrer le brouillon.

### 4.5 Validation

Avant validation, afficher un récapitulatif :

```text
12 produits prêts à ajouter
3 déjà présents
2 à compléter
1 ambiguïté à résoudre
```

Le bouton final reste désactivé si une ligne sélectionnée ne respecte pas les règles métier obligatoires.

Le commit est idempotent et relit la base. Une offre ajoutée entre l’analyse et la validation est ignorée proprement, jamais écrasée.

## 5. Parcours admin référentiel

### 5.1 Entrée

Dans `Admin > Référentiel > Produits`, conserver la table, les filtres, le score qualité, les doublons et le drawer existants.

Ajouter une action distincte :

```text
[ + Nouveau produit ]   [ ✨ Importer des images ]
```

L’action ouvre une surface dédiée. Elle ne remplace pas l’enrichissement IA existant des produits incomplets.

### 5.2 Upload sans quota commercial

L’admin peut créer autant de sessions que nécessaire.

Une session peut avoir un objectif :

- `Découvrir des produits` ;
- `Enrichir une référence` ;
- `Vérifier une référence` ;
- `Actualiser un lot`.

Le système n’affiche pas de compteur « photos restantes ».

Il affiche à la place des informations opérationnelles :

- nombre d’images du lot ;
- taille totale ;
- progression ;
- traitements en attente ;
- coût IA estimé / observé si disponible ;
- éventuel arrêt budgétaire.

### 5.3 Types d’images

L’admin peut utiliser :

- face avant ;
- face arrière ;
- code-barres ;
- plusieurs faces du même produit ;
- rayon ;
- plusieurs produits ;
- document produit autorisé.

Une image peut documenter plusieurs références et plusieurs images peuvent constituer les preuves d’une seule référence.

### 5.4 Proposition de création

Pour un produit absent :

```text
Image(s)
→ Extraction
→ Candidats proches
→ Aucun match exact
→ Proposition de ProductReference
→ Revue admin
→ Création explicite
```

Avant création, afficher les références similaires pour limiter les doublons.

### 5.5 Proposition de mise à jour

Pour une référence existante, afficher un diff :

| Champ | Actuel | Proposé | Preuve | Décision |
| --- | --- | --- | --- | --- |
| Fabricant | — | Délice Holding | photo arrière | Accepter / Refuser |
| Code-barres | — | 619… | recadrage code | Accepter / Refuser |
| Volume | 1 L | 1 L | face avant | Inchangé |
| Variante | Original | Zero | face avant | Conflit |

Les champs d’identité sensibles — GTIN, quantité, unité, pack, variante — doivent être visuellement signalés et ne peuvent pas être modifiés en masse par défaut.

### 5.6 File de revue

Prévoir une vue de travail avec onglets ou filtres :

- À vérifier ;
- Créations ;
- Mises à jour ;
- Doublons ;
- Conflits ;
- Appliquées ;
- Rejetées ;
- Erreurs.

La file doit être paginée côté serveur.

## 6. Conception visuelle

### 6.1 Principes

Le module doit reprendre le design system existant :

- cartes `bg-card` ;
- surfaces secondaires `bg-soft` ;
- bordures `border-line` ;
- couleurs sémantiques de statut existantes ;
- `Button`, `AdminTable`, `AdminDrawer`, `AdminConfirmDialog`, `BulkActionBar` ;
- responsive mobile-first ;
- FR / AR / RTL.

Éviter une interface « laboratoire IA ». L’utilisateur travaille sur des produits, pas sur des prompts ou des tokens.

### 6.2 Écran marchand — desktop/tablette

Disposition recommandée :

```text
┌──────────────────────────────────────────────────────────────┐
│ Catalogue                                      [Ajouter]     │
│                                                [Importer]    │
├──────────────────────────────────────────────────────────────┤
│ Importer mes rayons · 7/10 photos restantes                 │
│ [ + Photo ] [ + Photo ] [ + Photo ]                         │
│ miniatures + états                                           │
│                                      [Analyser mes photos]   │
├──────────────────────────────────────────────────────────────┤
│ Résultats : 18 détectés · 12 trouvés · 4 à confirmer        │
│                                                              │
│ [photo] Coca-Cola Original 1 L       Référence trouvée      │
│         Prix [ 3.200 ]               [✓ sélectionner]        │
│                                                              │
│ [photo] Produit inconnu 500 ml       À confirmer            │
│         [Chercher une référence] [Créer localement]          │
├──────────────────────────────────────────────────────────────┤
│                       [Enregistrer] [Ajouter 12 produits]     │
└──────────────────────────────────────────────────────────────┘
```

### 6.3 Écran marchand — mobile

Sur téléphone :

1. header compact avec compteur ;
2. grille 2 colonnes de photos ;
3. progression en stepper ;
4. résultats sous forme de cartes ;
5. actions secondaires dans un menu ;
6. barre d’action finale sticky en bas.

Ne pas afficher un tableau horizontal complexe.

### 6.4 Écran admin — import

```text
Référentiel produits
[+ Nouveau produit] [✨ Importer des images]

┌ Import référentiel ──────────────────────────────────────────┐
│ Objectif : [Découvrir des produits ▼]                       │
│ [ Déposer les images ]                                      │
│ 46 images · 182 Mo · 41 prêtes · 5 rejetées                │
│ [Lancer l’analyse]                                          │
└──────────────────────────────────────────────────────────────┘
```

### 6.5 Écran admin — revue

Desktop recommandé : vue scindée.

```text
┌──────────────────────┬───────────────────────────────────────┐
│ PREUVES              │ PROPOSITION                           │
│                      │                                       │
│ [image principale]   │ Référence actuelle   → Proposition   │
│ [miniatures]         │ Nom                  → ...            │
│ zoom                 │ Marque               → ...            │
│ recadrages           │ GTIN                 → ... ⚠          │
│                      │ Pack                 → ... ⚠           │
│                      │                                       │
│                      │ [Refuser] [Accepter champs sélection.] │
└──────────────────────┴───────────────────────────────────────┘
```

Sur mobile/tablette étroite, empiler preuves puis diff. La barre de décision reste sticky.

### 6.6 États visuels

Utiliser des libellés métier explicites :

- vert : confirmé / appliqué ;
- jaune : à vérifier / ambigu ;
- rouge : conflit / erreur ;
- gris : inconnu / ignoré ;
- bleu ou couleur primaire : action en cours / sélection.

Ne jamais utiliser seulement une couleur : ajouter texte et icône.

## 7. Frontend cible

### 7.1 Marchand

Réutiliser :

- `MerchantCatalogWizard` ;
- `merchant-catalog.service.ts` ;
- les types de `merchant-catalog.types.ts` ;
- les composants et validations catalogue existants.

Évolution recommandée :

```text
components/merchant/catalogue/photo-import/
  MerchantPhotoImportLauncher
  MerchantPhotoImportUploader
  MerchantPhotoImportProgress
  MerchantPhotoImportReview
  MerchantPhotoImportItemCard
  MerchantPhotoImportSummary
```

Le wizard existant peut devenir l’orchestrateur d’entrée, mais la logique photo ne doit plus grossir dans un seul composant monolithique.

Services cibles :

- quota ;
- sessions ;
- upload d’images ;
- lancement ;
- polling / lecture session ;
- sauvegarde brouillon ;
- validation.

Le contrat synchrone historique `/catalog/photo-import/preview` reste compatible tant qu’une migration explicite n’est pas livrée.

### 7.2 Admin

Réutiliser le backoffice existant :

- `AdminTable` ;
- `ProductReferenceDrawer` ;
- `ProductReferenceEditRow` ;
- filtres marque/catégorie/statut/qualité ;
- comparaison de doublons ;
- bulk actions.

Composants cibles :

```text
components/admin/referentiel/photo-import/
  ReferenceImageImportLauncher
  ReferenceImageImportUploader
  ReferenceImageImportProgress
  AiReferenceProposalQueue
  AiReferenceProposalReview
  AiReferenceFieldDiff
  AiReferenceEvidenceViewer
  AiReferenceBulkActionBar
```

Routes frontend proposées :

```text
/admin/referentiel/produits
/admin/referentiel/import-images
/admin/referentiel/propositions-ia
/admin/referentiel/propositions-ia/{proposalId}
```

Ne pas multiplier les entrées sidebar si des onglets internes suffisent.

### 7.3 Gestion des requêtes

- annuler les requêtes devenues obsolètes ;
- polling borné avec arrêt sur états terminaux ;
- conserver le brouillon local uniquement comme confort, le serveur reste source de vérité ;
- afficher le `X-Request-Id` dans les erreurs de support lorsque pertinent ;
- ne jamais envoyer directement une clé fournisseur depuis le frontend.

## 8. Backend cible — résumé

Le backend cible est détaillé dans `docs/architecture/catalog-photo-import.md`.

Principes :

- deux contextes métier : import catalogue marchand et enrichissement référentiel admin ;
- composants techniques partagés : stockage privé, preprocessing, adaptateurs IA, matching, journal de coûts ;
- sessions persistées ;
- Symfony Messenger persistant ;
- fichiers référencés par identifiant, jamais transportés dans les messages ;
- résultat d’extraction immuable ;
- matching versionné ;
- proposition distincte de l’écriture métier finale ;
- idempotence ;
- audit ;
- budget.

## 9. Relation avec le benchmark quatre fournisseurs

Le benchmark Qwen / Mistral / OpenAI / Gemini concerne le pilote de 10 supérettes.

Les images admin :

- ne comptent pas dans les 10 supérettes ;
- ne changent pas le nombre de photos du pilote ;
- ne remplacent pas les échecs ;
- ne modifient pas rétroactivement les résultats.

Après la décision, le fournisseur retenu devient le fournisseur principal des nouveaux flux marchand et admin.

## 10. Liens backlog

- #637 — Epic.
- #638 — quota marchand.
- #639 — capture et stockage.
- #640 — adaptateurs IA.
- #641 — orchestration.
- #642 — matching.
- #643 — correction et commit marchand.
- #644 — coûts.
- #645 — benchmark.
- #646 — bascule fournisseur.
- #647 — upload admin sans quota.
- #648 — propositions référentiel.
- #649 — revue admin.
- #371/#373 — déduplication et gouvernance.
- #391/#580 — images catalogue publiques, finalité différente.
