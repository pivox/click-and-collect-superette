# US-033 — Rechercher une supérette

## Sprint

Sprint 2 — Parcours client.

## Epic rattaché

EPIC-001 — Onboarding client par reconnaissance de supérette.

## Objectif produit

Permettre au client de trouver une supérette sans scanner de QR code.

Le QR code reste l'entrée la plus directe en magasin, mais le client doit aussi pouvoir rechercher un store par nom, ville ou zone simple. La recherche est donc la deuxième porte d'entrée vers le store.

## Récit utilisateur

En tant que client,
je veux rechercher une supérette par nom ou par ville,
afin de choisir le magasin dans lequel je veux faire ma Kadhia.

## Acteurs

- Client final connecté ou non connecté.
- Supérette active.
- Plateforme Click & Collect.

## Préconditions

- Des supérettes existent dans le système.
- Les supérettes proposées au client sont actives.
- Les informations publiques de base sont disponibles : nom, slug, ville, pays et zone éventuelle.

## Critères MVP

- nom de la supérette ;
- ville ;
- pays ;
- quartier ou zone si disponible ;
- statut actif.

## Parcours nominal

1. Le client ouvre l'écran de recherche de supérette.
2. Il saisit un nom, une ville ou une zone.
3. Le frontend appelle l'API publique de recherche.
4. Le backend retourne les supérettes actives correspondant aux critères.
5. Le client sélectionne une supérette.
6. Le frontend affiche la fiche publique du store.
7. Si le client est connecté, une relation client/supérette est créée ou mise à jour avec la source `search`.
8. Le client peut ensuite accéder au catalogue de cette supérette.

## Règles métier

- La recherche de supérette est publique.
- Les supérettes inactives ne doivent pas apparaître dans les résultats standards.
- Les données retournées doivent rester publiques.
- Un résultat de recherche doit toujours mener vers une fiche publique de store.
- Une recherche sans résultat doit être gérée proprement côté frontend.
- Si le client est connecté et ouvre un résultat, la relation client/supérette peut être créée ou mise à jour.

## API cible

Endpoint public recommandé :

```http
GET /api/stores/search?query=amen&city=tunis
```

Réponse attendue minimale :

```json
{
  "items": [
    {
      "store_id": "uuid",
      "name": "Supérette El Amen",
      "slug": "superette-el-amen",
      "city": "Tunis",
      "country": "TN",
      "is_active": true
    }
  ],
  "total": 1
}
```

Après sélection d'un résultat par un client connecté, la visite peut être enregistrée via :

```http
POST /api/me/stores/{storeId}/visit
```

Payload :

```json
{
  "source": "search"
}
```

## Critères d'acceptation

### Recherche par nom

Étant donné plusieurs supérettes actives,
quand le client recherche une partie du nom,
alors les supérettes correspondantes sont retournées.

### Recherche par ville

Étant donné plusieurs supérettes dans plusieurs villes,
quand le client filtre par ville,
alors seuls les stores actifs de cette ville sont proposés.

### Supérettes inactives exclues

Étant donné une supérette inactive correspondant aux critères,
quand le client effectue une recherche publique,
alors cette supérette n'apparaît pas dans les résultats standards.

### Aucun résultat

Étant donné une recherche sans correspondance,
quand le client valide sa recherche,
alors l'API retourne une liste vide.

### Relation après recherche

Étant donné un client connecté,
quand il ouvre une supérette depuis la recherche,
alors une relation client/supérette est créée ou mise à jour avec `source = search`.

## Tests attendus

- Test fonctionnel de recherche par nom.
- Test fonctionnel de recherche par ville.
- Test d'exclusion des supérettes inactives.
- Test de résultat vide.
- Test de cohérence entre résultat de recherche et fiche publique store.
- Test de création ou mise à jour de la relation client/supérette après ouverture d'un résultat.

## Hors périmètre

- Recherche géolocalisée.
- Carte interactive.
- Recherche par disponibilité produit.
- Suggestions avancées.
- Avis et notation client.

## Dépendances

- Entité `Shop` ou `Store`.
- Données publiques de localisation simple.
- Fiche publique store `US-031`.
- Relation client/supérette `US-032`.

## Définition de fini

La story est terminée lorsque le client peut rechercher une supérette active par nom ou ville, ouvrir sa fiche publique, puis créer ou mettre à jour sa relation avec ce store lorsqu'il est connecté.