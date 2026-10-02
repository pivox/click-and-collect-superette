# Conception UI/UX — Import photo IA

Date : 2026-10-02  
Référence fonctionnelle : `docs/product/catalog-photo-import-multi-ai.md`

## 1. Intention UX

La fonctionnalité doit donner l’impression d’un **assistant de catalogue**, pas d’un outil technique d’IA.

Le vocabulaire visible privilégie :

- « Importer mes rayons » ;
- « Analyser mes photos » ;
- « Produits détectés » ;
- « Référence trouvée » ;
- « À confirmer » ;
- « Ajouter au catalogue » ;
- « Importer des images » côté admin ;
- « Proposition de référentiel » ;
- « Valeur actuelle / valeur proposée / preuve ».

Éviter dans l’interface courante : tokens, prompt, température, modèle brut, JSON, embeddings.

Ces détails restent accessibles à l’administration technique lorsque nécessaire.

## 2. Hiérarchie visuelle marchand

### Niveau 1 — action

Sur la page Catalogue, les deux actions principales sont complémentaires :

```text
Ajouter un produit
Importer mes rayons
```

`Ajouter un produit` reste adapté au cas unitaire. `Importer mes rayons` est le parcours accéléré.

### Niveau 2 — quota

Le quota est visible avant l’envoi, mais ne doit pas dominer l’écran :

```text
7 photos restantes sur 10
```

Avec une jauge discrète. Aucun compteur fournisseur n’est montré.

### Niveau 3 — photos

La zone d’upload fonctionne en drag/drop sur desktop et capture/sélection sur mobile.

Chaque photo est une carte autonome avec son état. Une erreur sur une image ne bloque pas les autres.

### Niveau 4 — analyse

Utiliser un stepper compact :

```text
1. Photos  →  2. Analyse  →  3. Vérification  →  4. Ajout
```

Le stepper reflète l’état serveur et survit à une navigation.

### Niveau 5 — revue

Les produits à corriger sont prioritaires en haut :

1. ambiguïtés ;
2. prix manquants ;
3. produits absents du référentiel ;
4. références trouvées ;
5. déjà présents.

Un filtre permet de revenir à `Tous`.

## 3. Carte produit marchand

Structure recommandée :

```text
┌────────────────────────────────────────────┐
│ [photo]  Coca-Cola Original                │
│          1 L · Bouteille                   │
│          ✓ Référence trouvée               │
│                                            │
│ Référence : Coca-Cola Original 1 L    >    │
│ Prix TND  [ 3.200 ]                        │
│ [✓ Disponible] [✓ Visible]                 │
│                                            │
│ [Inclure dans l’ajout ✓]                   │
└────────────────────────────────────────────┘
```

Cas ambigu :

```text
⚠ À confirmer
Coca-Cola — volume illisible

Possibilités :
( ) 1 L
( ) 1,5 L
[ Chercher dans le référentiel ]
[ Ne pas ajouter ]
```

Ne jamais précocher arbitrairement un format critique ambigu.

## 4. Upload admin

L’admin n’a pas de jauge de quota photo.

Le composant montre plutôt :

```text
Lot : 46 images
Prêtes : 41
À remplacer : 3
Erreur : 2
Taille totale : 182 Mo
```

Si un plafond budgétaire empêche de démarrer :

```text
Analyse en attente — plafond de dépense atteint
[Voir le suivi des coûts]
```

Ce message ne doit pas dire « quota photo dépassé ».

## 5. Revue admin

### Vue principale

La revue se fait avec preuves à gauche et diff à droite sur grand écran.

Les champs sont regroupés :

- Identité : nom, marque, variante ;
- Format : quantité, unité, pack ;
- Identification : GTIN/code-barres ;
- Classification : catégorie ;
- Enrichissement : fabricant, nom AR et champs autorisés.

### Risque

Les champs critiques portent un indicateur textuel :

```text
⚠ Identité produit
```

L’action bulk est désactivée par défaut pour ces champs.

### Preuves

Une valeur proposée peut surligner la zone source ou afficher un recadrage.

L’admin peut naviguer entre :

- image entière ;
- recadrage ;
- autre face du produit ;
- autre image de la même session.

## 6. Responsive

### Marchand mobile

- header 1 ligne ;
- quota sous le titre ;
- upload plein largeur ;
- vignettes 2 colonnes ;
- résultats en cartes ;
- barre sticky : `Enregistrer` / `Ajouter N produits`.

### Admin tablette

La revue passe d’une vue 2 colonnes à :

1. galerie ;
2. diff ;
3. actions sticky.

### Desktop

Exploiter la largeur pour garder image et données simultanément visibles.

## 7. Accessibilité

- tous les états ont texte + icône ;
- navigation clavier complète ;
- focus visible ;
- boutons nommés explicitement ;
- progression annoncée avec régions `aria-live` adaptées ;
- ne pas déplacer brutalement le focus lors d’un résultat asynchrone ;
- ordre RTL cohérent pour arabe ;
- zoom image utilisable au clavier.

## 8. États à concevoir explicitement

Chaque écran doit avoir :

- initial ;
- upload en cours ;
- upload partiel ;
- traitement en file ;
- analyse en cours ;
- résultat partiel ;
- prêt à vérifier ;
- aucun produit identifiable ;
- erreur fournisseur ;
- erreur fichier ;
- budget bloqué ;
- session annulée ;
- session terminée ;
- session reprise ;
- conflit de modification.

« Aucun produit identifiable » est différent de « erreur IA ».

## 9. Composants existants à réutiliser

Marchand :

- `MerchantCatalogWizard` comme point de départ ;
- composants UI communs ;
- services catalogue existants.

Admin :

- `AdminTable` ;
- `AdminDrawer` ;
- `AdminConfirmDialog` ;
- `BulkActionBar` ;
- `ProductReferenceDrawer` ;
- `ProductReferenceEditRow` ;
- styles de score qualité et statuts existants.

La conception ne doit pas introduire un deuxième design system.

## 10. Tests frontend attendus

- quota marchand affiché correctement ;
- aucune notion de quota photo sur admin ;
- upload partiel et retry ;
- navigation hors page puis reprise ;
- polling stoppé sur état terminal ;
- ambiguïté non validable sans choix ;
- prix requis avant ajout marchand ;
- référence déjà présente non écrasée ;
- diff admin champ par champ ;
- bulk admin limité aux champs autorisés ;
- conflit concurrent visible ;
- FR / AR / RTL ;
- mobile 390 × 844 ;
- tablette ;
- clavier et focus.
