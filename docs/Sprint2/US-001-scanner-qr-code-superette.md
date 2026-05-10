# US-001 — Scanner le QR code d'une supérette

## Sprint

Sprint 2 — Parcours client.

## Epic rattaché

EPIC-001 — Onboarding par QR code.

## Objectif produit

Permettre à un client d'accéder directement à l'espace digital d'une supérette en scannant son QR code physique, sans avoir à rechercher manuellement le magasin dans une liste.

Cette user story est le point d'entrée principal du parcours client MVP : le QR code affiché dans la supérette ou partagé par le marchand doit amener le client vers la bonne supérette, avec son catalogue, son identité visuelle et ses informations publiques.

## Récit utilisateur

En tant que client d'une supérette,
je veux scanner le QR code du magasin,
afin d'ouvrir directement la page de cette supérette et commencer ma Kadhia sans friction.

## Acteurs

- Client final.
- Supérette active.
- Plateforme Click & Collect.

## Préconditions

- La supérette existe dans le système.
- La supérette est active.
- La supérette possède un `qr_code_token` unique.
- Le QR code encode une URL publique contenant ce token.
- Le token ne doit pas exposer de donnée sensible.

## Parcours nominal

1. Le client scanne le QR code affiché par la supérette.
2. Le navigateur ouvre l'URL publique associée au QR code.
3. Le frontend appelle l'API publique de résolution du QR code.
4. Le backend retrouve la supérette active correspondant au token.
5. Le client est redirigé ou positionné sur la page publique de cette supérette.
6. Le client peut ensuite consulter les informations de la supérette et son catalogue.

## Règles métier

- Le QR code doit identifier une seule supérette.
- Une supérette inactive ne doit pas être accessible via QR code.
- Un token inconnu doit retourner une erreur claire, sans fuite d'information.
- Le QR code doit rester stable tant qu'il n'est pas explicitement régénéré.
- L'accès par QR code est public et ne nécessite pas d'authentification.
- Le QR code sert uniquement à l'entrée dans le storefront ; il ne donne aucun droit marchand ou admin.

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
  "is_active": true
}
```

## Critères d'acceptation

### Cas nominal

Étant donné une supérette active avec un QR token valide,
quand le client scanne le QR code,
alors le système retrouve la supérette,
et retourne les informations publiques nécessaires pour ouvrir son espace client.

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

## Hors périmètre

- Génération graphique du QR code.
- Impression du QR code.
- Statistiques de scan.
- Géolocalisation client.
- Sélection de supérette par carte ou liste.

## Dépendances

- Entité `Shop` existante.
- Seed de supérette de démonstration.
- Endpoint public de catalogue pour la suite du parcours.

## Définition de fini

La story est terminée lorsque le client peut scanner un QR code valide, résoudre la supérette côté backend, arriver sur l'espace public du magasin et poursuivre vers la consultation du catalogue.