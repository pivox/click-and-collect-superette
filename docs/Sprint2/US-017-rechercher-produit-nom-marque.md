# US-017 — Rechercher un produit par nom ou marque

## Sprint

Sprint 2 — Parcours client.

## Epic rattaché

EPIC-002 — Catalogue produits.

## Objectif produit

Permettre au client de trouver rapidement un produit dans le catalogue d'une supérette, sans parcourir toute la liste.

La recherche est centrale pour l'expérience Click & Collect : le client doit pouvoir taper une intention simple comme `lait`, `Vitalait`, `1L`, `Délice` ou `eau` et trouver les produits correspondants du magasin.

## Récit utilisateur

En tant que client,
je veux rechercher un produit par son nom, sa marque ou son format,
afin de trouver rapidement l'article à ajouter à ma Kadhia.

## Acteurs

- Client final.
- Supérette active.
- Catalogue marchand.
- Plateforme Click & Collect.

## Préconditions

- Le client consulte le catalogue d'une supérette active.
- La supérette possède des produits visibles et disponibles.
- Les produits ont des champs exploitables : nom, marque, catégorie, volume, unité et éventuellement nom arabe.

## Champs recherchables

La recherche doit couvrir au minimum :

- nom français du produit ;
- nom arabe si disponible ;
- marque ;
- format ou volume ;
- unité ;
- catégorie publique.

Exemples de recherches attendues :

- `lait` ;
- `vitalait` ;
- `Lait demi écrémé` ;
- `1L` ;
- `delice` ;
- `eau`.

## Parcours nominal

1. Le client ouvre le catalogue de la supérette.
2. Il saisit une recherche dans le champ prévu.
3. Après un court délai, le frontend appelle l'API avec le paramètre de recherche.
4. Le backend retourne uniquement les produits correspondant à la recherche.
5. Le client sélectionne un produit dans les résultats.
6. Le client peut l'ajouter à sa Kadhia.

## Règles métier

- La recherche se fait dans le catalogue de la supérette courante uniquement.
- La recherche ne doit pas retourner des produits d'une autre supérette.
- La recherche ne doit retourner que les produits visibles et disponibles.
- La recherche doit être insensible à la casse.
- La recherche doit être tolérante aux accents pour le français.
- La recherche doit permettre une correspondance partielle.
- Une recherche vide équivaut à consulter le catalogue sans filtre.
- Le frontend applique un debounce recommandé de 300 ms pour éviter trop d'appels API.

## API cible

Endpoint public :

```http
GET /api/stores/{storeId}/catalog?query=lait
```

Réponse attendue : même structure que le catalogue public, filtrée par recherche.

```json
{
  "store_id": "uuid",
  "items": [
    {
      "id": "merchant-product-uuid",
      "name_fr": "Lait demi-écrémé Vitalait 1L",
      "brand": "Vitalait",
      "category": "lait",
      "price_tnd": "1.750"
    }
  ]
}
```

## Critères d'acceptation

### Recherche par nom

Étant donné un produit nommé `Lait demi-écrémé Vitalait 1L`,
quand le client recherche `lait`,
alors ce produit apparaît dans les résultats.

### Recherche par marque

Étant donné un produit de marque `Vitalait`,
quand le client recherche `vitalait`,
alors les produits Vitalait visibles du magasin apparaissent.

### Recherche insensible à la casse

Étant donné un produit `Vitalait`,
quand le client recherche `VITALAIT`,
alors le résultat est identique à `vitalait`.

### Recherche sans résultat

Étant donné une recherche sans correspondance,
quand le client valide la recherche,
alors l'API retourne une liste vide,
et le frontend affiche un message clair du type `Aucun produit trouvé`.

### Recherche limitée au magasin

Étant donné deux supérettes avec des catalogues différents,
quand le client recherche dans la supérette A,
alors aucun produit propre uniquement à la supérette B ne doit apparaître.

## Tests attendus

- Test fonctionnel recherche par nom.
- Test fonctionnel recherche par marque.
- Test recherche insensible à la casse.
- Test recherche avec accent / sans accent si supporté.
- Test recherche sans résultat.
- Test isolation par `shopId`.
- Test exclusion des produits invisibles ou indisponibles.

## Hors périmètre

- Recherche full-text avancée avec ranking complexe.
- Suggestions automatiques.
- Correction orthographique.
- Historique de recherche.
- Recherche vocale.
- Recherche par code-barres côté client.

## Dépendances

- US-002 — Consulter le catalogue marchand.
- Catalogue marchand visible.
- Référentiel produit avec données nom/marque/format suffisantes.

## Définition de fini

La story est terminée lorsque le client peut rechercher un produit du catalogue public par nom, marque ou format, obtenir des résultats cohérents et sélectionner un produit à ajouter à la Kadhia.