# US-001 — Reconnaître une supérette par QR code

## Sprint

Sprint 2 — Parcours client.

## Epic rattaché

EPIC-001 — Onboarding client par reconnaissance de supérette.

## Objectif produit

Permettre à un client d'identifier automatiquement une supérette en scannant son QR code physique.

Le QR code ne doit plus être considéré uniquement comme un raccourci vers le catalogue. Il sert d'abord à reconnaître le store, à afficher son identité publique, puis à créer ou mettre à jour la relation entre le client et cette supérette lorsque le client est connecté.

Cette relation permettra ensuite au client de retrouver ses supérettes connues, de revenir plus vite vers un catalogue déjà visité et de construire progressivement son parcours Click & Collect autour de magasins identifiés.

## Récit utilisateur

En tant que client d'une supérette,
je veux scanner le QR code du magasin,
afin que l'application reconnaisse cette supérette et me permette d'accéder à son espace client.

## Acteurs

- Client final connecté ou non connecté.
- Supérette active.
- Plateforme Click & Collect.

## Préconditions

- La supérette existe dans le système.
- La supérette est active.
- La supérette possède un `qr_code_token` unique.
- Le QR code encode une URL publique contenant ce token.
- Le token ne doit pas exposer d'identifiant interne sensible.
- Si le client est connecté, son identité applicative est connue du backend.

## Parcours nominal

1. Le client scanne le QR code affiché par la supérette.
2. Le navigateur ouvre l'URL publique associée au QR code.
3. Le frontend appelle l'API publique de résolution du QR code.
4. Le backend retrouve la supérette active correspondant au token.
5. Le frontend affiche la fiche publique de la supérette.
6. Si le client est connecté, le backend crée ou met à jour la relation client/supérette avec la source `qr_code`.
7. Si le client n'est pas connecté, le frontend peut conserver temporairement le store courant côté session locale.
8. Le client peut ensuite consulter le catalogue de cette même supérette.

## Règles métier

- Le QR code doit identifier une seule supérette.
- Une supérette inactive ne doit pas être accessible via QR code.
- Un token inconnu doit retourner une erreur claire, sans fuite d'information.
- Le QR code doit rester stable tant qu'il n'est pas explicitement régénéré.
- L'accès par QR code est public et ne nécessite pas de JWT.
- Le QR code sert uniquement à reconnaître un store ; il ne donne aucun droit marchand ou admin.
- Le QR code ne doit pas exposer directement l'identifiant technique interne du store.
- Si le client est connecté, scanner le même store plusieurs fois ne doit pas créer de doublon dans la relation client/supérette.
- Si la relation existe déjà, `last_seen_at` est mis à jour.
- La source de découverte doit être enregistrée à `qr_code` lors du premier scan.

## Données minimales retournées

La résolution du QR code doit permettre d'afficher :

- l'identifiant public du store ;
- le nom de la supérette ;
- le slug public ;
- la ville ;
- le pays ;
- le statut actif ;
- les informations nécessaires à l'application du thème actif ;
- le lien logique vers le catalogue du store.

## API cible

Endpoint public cible :

```http
GET /api/stores/by-qr/{qrCodeToken}
```

Réponse attendue minimale :

```json
{
  "store_id": "uuid",
  "name": "Supérette El Amen",
  "slug": "superette-el-amen",
  "city": "Tunis",
  "country": "TN",
  "is_active": true,
  "theme_url": "/api/stores/{storeId}/theme",
  "catalog_url": "/api/stores/{storeId}/catalog"
}
```

Si le client est connecté, la création ou mise à jour de la relation peut être faite :

- soit implicitement pendant la résolution du QR code ;
- soit explicitement via un endpoint de visite.

Option recommandée pour garder une séparation claire :

```http
POST /api/me/stores/{storeId}/visit
```

Payload :

```json
{
  "source": "qr_code"
}
```

## Critères d'acceptation

### Cas nominal

Étant donné une supérette active avec un QR token valide,
quand le client scanne le QR code,
alors le système retrouve la supérette,
et retourne les informations publiques nécessaires pour ouvrir son espace client.

### Création de relation client/supérette

Étant donné un client connecté,
quand il scanne le QR code d'une supérette active,
alors une relation client/supérette est créée si elle n'existe pas déjà,
et la source de découverte est `qr_code`.

### Relation déjà existante

Étant donné un client connecté qui connaît déjà cette supérette,
quand il scanne à nouveau son QR code,
alors aucune relation dupliquée n'est créée,
et `last_seen_at` est mis à jour.

### Client non connecté

Étant donné un client non connecté,
quand il scanne le QR code,
alors il peut consulter la fiche publique de la supérette,
et aucune relation persistée en base n'est créée.

### Token inconnu

Étant donné un QR token inexistant,
quand le client ouvre l'URL,
alors l'API retourne `404 Not Found`,
et le frontend affiche un message indiquant que la supérette est introuvable.

### Supérette inactive

Étant donné une supérette existante mais inactive,
quand le client ouvre son QR code,
alors l'API ne doit pas exposer la supérette comme disponible,
et le frontend affiche un message de supérette indisponible.

### Accès public

Étant donné un client non connecté,
quand il scanne le QR code,
alors il peut accéder à la page publique de la supérette sans JWT.

## Tests attendus

- Test fonctionnel `GET /api/stores/by-qr/{token}` avec token valide.
- Test fonctionnel avec token inconnu.
- Test fonctionnel avec supérette inactive.
- Test de sécurité confirmant l'accès public.
- Test d'unicité du `qr_code_token` côté base de données.
- Test de création de relation client/supérette après scan connecté.
- Test d'idempotence : deux scans du même store ne créent pas deux relations.
- Test de mise à jour de `last_seen_at`.

## Hors périmètre

- Génération graphique du QR code.
- Impression du QR code.
- Statistiques avancées de scan.
- Géolocalisation client.
- Recherche de supérette par carte.
- Droits marchand ou admin.

## Dépendances

- Entité `Shop` ou `Store` existante.
- Relation client/supérette décrite dans `US-032`.
- Seed de supérette de démonstration.
- Endpoint public de catalogue pour la suite du parcours.
- Endpoint public de thème actif du store.

## Définition de fini

La story est terminée lorsque le client peut scanner un QR code valide, reconnaître la bonne supérette, voir sa fiche publique, créer ou mettre à jour sa relation client/supérette s'il est connecté, puis poursuivre vers la consultation du catalogue.