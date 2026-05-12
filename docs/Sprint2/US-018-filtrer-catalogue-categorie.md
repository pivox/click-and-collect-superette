# US-018 — Filtrer le catalogue par catégorie

## Sprint

Sprint 2 — Parcours client.

## Epic rattaché

EPIC-002 — Catalogue produits.

## Objectif produit

Permettre au client de réduire la liste des produits affichés en sélectionnant une catégorie, par exemple `lait`, `boissons`, `pâtes`, `huile` ou `biscuits`.

Le filtre par catégorie complète la recherche texte : il permet une navigation rapide, proche de l'organisation par rayons d'une supérette.

## Récit utilisateur

En tant que client,
je veux filtrer le catalogue par catégorie,
afin de parcourir plus facilement les produits d'un rayon précis.

## Acteurs

- Client final.
- Supérette active.
- Catalogue marchand.
- Plateforme Click & Collect.

## Préconditions

- Le client consulte le catalogue public d'une supérette.
- Les produits du catalogue sont associés à une catégorie publique.
- Les catégories ont un slug stable utilisable dans l'URL.

## Parcours nominal

1. Le client ouvre le catalogue de la supérette.
2. Le frontend affiche une liste de catégories disponibles.
3. Le client sélectionne une catégorie.
4. Le frontend appelle le catalogue public avec le paramètre `category`.
5. Le backend retourne uniquement les produits visibles et disponibles de cette catégorie.
6. Le client peut ajouter un produit filtré à sa Kadhia.

## Règles métier

- Le filtre catégorie s'applique uniquement au catalogue de la supérette courante.
- Une catégorie sans produit visible doit retourner une liste vide.
- Le filtre ne doit pas exposer les produits invisibles ou indisponibles.
- Le slug de catégorie est utilisé côté API pour éviter les variations de libellé.
- Le filtre catégorie doit pouvoir être combiné avec la recherche texte.
- Le choix `Tout` côté frontend doit supprimer le filtre catégorie.

## API cible

Endpoint public :

```http
GET /api/shops/{shopId}/catalog?category=lait
```

Combinaison recherche + catégorie :

```http
GET /api/shops/{shopId}/catalog?query=vitalait&category=lait
```

Réponse attendue : même structure que le catalogue public, filtrée par catégorie.

## Critères d'acceptation

### Filtre par catégorie

Étant donné un catalogue avec plusieurs catégories,
quand le client sélectionne `lait`,
alors seuls les produits de la catégorie `lait` sont retournés.

### Catégorie vide

Étant donné une catégorie sans produit visible,
quand le client sélectionne cette catégorie,
alors le frontend affiche un état vide clair.

### Combinaison recherche et catégorie

Étant donné une recherche `vitalait` et une catégorie `lait`,
quand le client applique les deux filtres,
alors seuls les produits correspondant aux deux critères sont affichés.

### Reset catégorie

Étant donné un filtre catégorie actif,
quand le client choisit `Tout`,
alors le catalogue revient à la liste sans filtre catégorie.

### Isolation magasin

Étant donné plusieurs supérettes,
quand le client filtre le catalogue de la supérette A,
alors aucun produit d'une autre supérette ne doit apparaître.

## Tests attendus

- Test fonctionnel filtre par catégorie.
- Test catégorie sans résultat.
- Test combinaison `query` + `category`.
- Test reset côté contrat API via absence du paramètre `category`.
- Test exclusion des produits invisibles et indisponibles.
- Test isolation par `shopId`.

## Hors périmètre

- Arborescence multi-niveaux de catégories.
- Facettes avancées.
- Filtres prix, marque ou disponibilité.
- Tri par pertinence.
- Compteurs dynamiques par catégorie.

## Dépendances

- US-002 — Consulter le catalogue marchand.
- US-017 — Rechercher un produit par nom ou marque.
- Référentiel produit avec catégories normalisées.

## Définition de fini

La story est terminée lorsque le client peut filtrer le catalogue public par catégorie, combiner ce filtre avec une recherche simple et continuer vers l'ajout à la Kadhia.