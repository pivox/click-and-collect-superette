# US-019 — Modifier la quantité ou retirer un produit de la Kadhia

## Sprint

Sprint 2 — Parcours client.

## Epic rattaché

EPIC-003 — Gestion Kadhia.

## Objectif produit

Permettre au client de corriger sa Kadhia avant soumission : augmenter une quantité, diminuer une quantité ou retirer complètement un produit.

Cette story donne au client le contrôle de sa liste de courses avant le choix du créneau et la création de commande.

## Récit utilisateur

En tant que client connecté,
je veux modifier les quantités ou retirer des produits de ma Kadhia,
afin d'ajuster ma commande avant de la soumettre.

## Acteurs

- Client connecté.
- Kadhia `draft`.
- Plateforme Click & Collect.

## Préconditions

- Le client est authentifié avec `ROLE_CUSTOMER`.
- Le client possède une Kadhia au statut `draft` (identifiée par `kadhiaId`).
- La Kadhia contient au moins une ligne.
- La ligne appartient bien à la Kadhia du client courant.

## Parcours nominal — modification quantité

1. Le client ouvre sa Kadhia.
2. Il augmente ou diminue la quantité d'un produit.
3. Le frontend appelle l'API de modification de ligne.
4. Le backend valide la quantité.
5. Le backend met à jour la ligne.
6. Le backend recalcule les totaux.
7. Le client voit la Kadhia mise à jour.

## Parcours nominal — suppression produit

1. Le client ouvre sa Kadhia.
2. Il clique sur supprimer ou met la quantité à `0`.
3. Le frontend appelle l'API de suppression ou de mise à jour quantité.
4. Le backend retire la ligne de la Kadhia.
5. Le backend recalcule les totaux.
6. Le client voit la Kadhia sans ce produit.

## Règles métier

- Seule une Kadhia au statut `draft` est modifiable.
- Une Kadhia `submitted` ou dans tout autre statut non-`draft` ne peut pas être modifiée ; l'API retourne `KADHIA_NOT_EDITABLE`.
- La quantité minimale d'une ligne active est `1`.
- Une quantité négative est toujours refusée.
- Une ligne ne peut être modifiée que par le propriétaire de la Kadhia.
- L'endpoint `PUT .../lines/{merchantProductId}` est un upsert : il crée ou remplace la ligne ciblée par son `merchantProductId`.
- Après chaque modification, le total de la Kadhia doit être recalculé côté serveur.
- Le prix unitaire snapshoté ne doit pas être recalculé depuis le catalogue lors d'une simple modification de quantité.

## API cible

Consulter la Kadhia :

```http
GET /api/me/kadhias/{kadhiaId}
Authorization: Bearer <client_jwt>
```

Modifier la quantité d'un produit (upsert) :

```http
PUT /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}
Authorization: Bearer <client_jwt>
Content-Type: application/json
```

Payload :

```json
{
  "quantity": 2
}
```

Supprimer une ligne :

```http
DELETE /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}
Authorization: Bearer <client_jwt>
```

## Critères d'acceptation

### Augmenter une quantité

Étant donné une Kadhia `draft` avec une ligne quantité `1`,
quand le client met la quantité à `2`,
alors la ligne passe à quantité `2`,
et le total de la Kadhia est recalculé.

### Diminuer une quantité

Étant donné une ligne quantité `3`,
quand le client met la quantité à `1`,
alors la ligne passe à quantité `1`,
et le total est recalculé.

### Supprimer une ligne

Étant donné une Kadhia contenant un produit,
quand le client supprime la ligne,
alors le produit disparaît de la Kadhia,
et le total est recalculé.

### Quantité zéro

Étant donné une ligne existante,
quand le client envoie `quantity = 0`,
alors la ligne est supprimée ou l'API retourne une réponse cohérente documentée.

### Quantité invalide

Étant donné une quantité négative,
quand le client tente la modification,
alors l'API retourne une erreur de validation.

### Kadhia soumise

Étant donné une Kadhia déjà soumise,
quand le client tente de modifier une ligne,
alors l'API refuse la modification avec l'erreur `KADHIA_NOT_EDITABLE`.

### Sécurité propriétaire

Étant donné une ligne appartenant à un autre client,
quand le client courant tente de la modifier,
alors l'API retourne une erreur d'accès.

## Tests attendus

- Test modification quantité vers une valeur supérieure.
- Test modification quantité vers une valeur inférieure.
- Test suppression de ligne.
- Test quantité `0`.
- Test quantité négative refusée.
- Test modification interdite après soumission.
- Test accès interdit sur une ligne d'un autre client.
- Test maintien du prix snapshoté.
- Test recalcul des totaux.

## Hors périmètre

- Réservation de stock.
- Substitution produit.
- Commentaire par ligne.
- Modification après acceptation marchand.
- Synchronisation temps réel multi-device.

## Dépendances

- US-003 — Ajouter un produit à la Kadhia.
- Modèle `Kadhia` et `KadhiaLine`.
- Authentification client.

## Définition de fini

La story est terminée lorsque le client peut modifier les quantités ou retirer des produits d'une Kadhia `draft` identifiée par `kadhiaId`, avec recalcul des totaux côté serveur et refus clair si la Kadhia n'est plus en `draft`.