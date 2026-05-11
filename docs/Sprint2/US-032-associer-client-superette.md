# US-032 — Associer un client à une supérette

## Sprint

Sprint 2 — Parcours client.

## Epic rattaché

EPIC-001 — Onboarding client par reconnaissance de supérette.

## Objectif produit

Créer une relation métier entre un client et une supérette dès que le client reconnaît ou consulte ce store.

Cette relation doit permettre au client de retrouver ses supérettes connues, d'identifier ses magasins favoris et de revenir plus rapidement vers les catalogues déjà consultés.

## Récit utilisateur

En tant que client connecté,
je veux que l'application mémorise les supérettes que j'ai reconnues ou consultées,
afin de retrouver facilement mes magasins habituels.

## Acteurs

- Client connecté.
- Supérette active.
- Plateforme Click & Collect.

## Préconditions

- Le client possède un compte applicatif.
- La supérette existe dans le système.
- La supérette est active au moment de la création de relation.
- Le client a découvert la supérette via un événement connu : QR code, recherche, consultation ou future commande.

## Modèle métier cible

Créer une entité pivot entre le client et la supérette.

Nom proposé : `ClientStore` ou `CustomerStore` selon le vocabulaire final du code.

Champs minimaux :

```text
client_store
- id
- customer_id
- store_id
- source: qr_code | search | manual | order
- first_seen_at
- last_seen_at
- is_favorite
- status: active | hidden
- created_at
- updated_at
```

Contrainte obligatoire :

```text
UNIQUE(customer_id, store_id)
```

## Sources de création possibles

La relation peut être créée ou mise à jour depuis :

- scan QR code ;
- recherche de supérette ;
- ouverture d'une fiche publique store ;
- ajout manuel aux favoris ;
- future commande dans une supérette.

## Parcours nominal

1. Le client connecté reconnaît une supérette via QR code ou recherche.
2. Le backend vérifie si une relation existe déjà entre ce client et cette supérette.
3. Si aucune relation n'existe, le backend la crée avec la source de découverte.
4. Si la relation existe déjà, le backend met à jour `last_seen_at`.
5. Le store devient disponible dans la liste des supérettes connues du client.

## Règles métier

- Une relation client/supérette ne doit pas être dupliquée.
- `first_seen_at` ne doit pas changer après la première création.
- `last_seen_at` doit être mis à jour à chaque nouvelle consultation reconnue.
- `source` représente la première source connue de découverte.
- `is_favorite` vaut `false` par défaut.
- Le client peut masquer ou retirer une supérette de sa liste.
- Une supérette inactive ne doit pas être ajoutée comme relation active.
- La relation ne donne aucun droit marchand ou admin.

## API cible

Liste des supérettes connues du client :

```http
GET /api/me/stores
```

Créer ou mettre à jour une visite :

```http
POST /api/me/stores/{storeId}/visit
```

Payload :

```json
{
  "source": "qr_code"
}
```

Marquer en favori :

```http
PATCH /api/me/stores/{storeId}/favorite
```

Payload :

```json
{
  "is_favorite": true
}
```

Retirer de la liste visible :

```http
DELETE /api/me/stores/{storeId}
```

## Critères d'acceptation

### Création après QR code

Étant donné un client connecté,
quand il scanne le QR code d'une supérette active,
alors une relation client/supérette est créée avec `source = qr_code`.

### Création après recherche

Étant donné un client connecté,
quand il ouvre une supérette depuis les résultats de recherche,
alors une relation client/supérette est créée avec `source = search`.

### Idempotence

Étant donné une relation déjà existante,
quand le client consulte à nouveau la même supérette,
alors aucune nouvelle ligne n'est créée,
et `last_seen_at` est mis à jour.

### Liste des stores connus

Étant donné un client connecté avec plusieurs supérettes connues,
quand il consulte `GET /api/me/stores`,
alors il reçoit la liste de ses stores actifs, triés par favori puis par dernière consultation.

### Favori

Étant donné une supérette connue,
quand le client la marque comme favorite,
alors elle remonte dans sa liste de magasins.

### Client non connecté

Étant donné un client non connecté,
quand il consulte une supérette,
alors aucune relation persistée n'est créée côté backend.

## Tests attendus

- Test de création d'une relation client/supérette.
- Test d'unicité `customer_id + store_id`.
- Test d'idempotence de la visite.
- Test de mise à jour de `last_seen_at`.
- Test de conservation de `first_seen_at`.
- Test de liste `GET /api/me/stores`.
- Test de favori.
- Test de refus ou non-création pour une supérette inactive.
- Test de sécurité : un client ne peut gérer que ses propres relations.

## Hors périmètre

- Historique détaillé de navigation.
- Recommandation automatique de magasins.
- Géolocalisation.
- Avis client.
- Statistiques marchand sur les clients.

## Dépendances

- Entité client ou utilisateur final.
- Entité `Shop` ou `Store`.
- Résolution QR code `US-001`.
- Recherche de supérettes `US-033`.

## Définition de fini

La story est terminée lorsqu'un client connecté peut créer, retrouver, mettre à jour et marquer en favori une relation avec une supérette active, sans doublon et sans exposition de données privées.