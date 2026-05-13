# US-021 — Soumettre la commande

## Sprint

Sprint 2 — Parcours client.

## Epic rattaché

EPIC-004 — Rendez-vous et soumission de commande.

## Objectif produit

Permettre au client de transformer sa Kadhia `draft` en commande soumise au marchand, avec un créneau de retrait valide.

Cette user story clôture le parcours client du Sprint 2 : le client a trouvé ses produits, composé sa Kadhia, vérifié le total, choisi un créneau et confirme sa demande de préparation.

## Récit utilisateur

En tant que client connecté,
je veux soumettre ma Kadhia avec un créneau de retrait,
afin que le marchand reçoive ma commande à préparer.

## Acteurs

- Client connecté.
- Supérette active.
- Kadhia `draft` non vide.
- Créneau de retrait disponible.
- Marchand destinataire de la commande.
- Plateforme Click & Collect.

## Préconditions

- Le client est authentifié avec `ROLE_CUSTOMER`.
- Le client possède une Kadhia `draft` identifiée par `kadhiaId`.
- La Kadhia contient au moins une ligne.
- La Kadhia appartient à une seule supérette.
- Le créneau sélectionné appartient à cette même supérette.
- Le créneau est futur et possède une capacité restante.

## Parcours nominal

1. Le client consulte le récapitulatif de sa Kadhia.
2. Il sélectionne un créneau de retrait disponible.
3. Il clique sur `Soumettre la commande`.
4. Le frontend appelle l'API de soumission.
5. Le backend ouvre une transaction.
6. Le backend verrouille ou revalide la Kadhia et le créneau.
7. Le backend vérifie que la Kadhia est non vide et toujours `draft`.
8. Le backend vérifie que le créneau est encore disponible.
9. Le backend crée une commande au statut `submitted`.
10. Le backend associe les lignes snapshotées de la Kadhia à la commande.
11. Le backend décrémente la capacité restante du créneau.
12. Le backend passe la Kadhia au statut `submitted` ou la lie définitivement à la commande.
13. Le client reçoit une confirmation de soumission.

## Règles métier

- Une commande client doit être créée uniquement depuis une Kadhia `draft` non vide.
- La soumission doit être transactionnelle.
- La capacité du créneau doit être vérifiée au moment exact de la soumission.
- Deux soumissions concurrentes ne doivent pas dépasser la capacité du créneau.
- Les prix de la commande doivent venir des snapshots de Kadhia.
- Les prix ne doivent pas être recalculés depuis le catalogue au moment de la soumission.
- Une Kadhia déjà soumise ne peut pas être soumise une deuxième fois.
- Le statut initial de la commande est `submitted`.
- La validation ou le refus marchand appartient au Sprint 3.
- La commande doit rester rattachée au client, à la supérette et au créneau.

## API cible

Endpoint protégé client :

```http
POST /api/me/kadhias/{kadhiaId}/submit
Authorization: Bearer <client_jwt>
Content-Type: application/json
```

Payload :

```json
{
  "pickup_slot_id": "pickup-slot-uuid",
  "notes": "Instructions optionnelles pour le marchand"
}
```

Le champ `notes` est optionnel. Il s'agit de notes au niveau de la commande (visibles par le marchand), distinctes des notes de la Kadhia.

Réponse attendue :

```json
{
  "id": "order-uuid",
  "kadhia_id": "kadhia-uuid",
  "store_id": "store-uuid",
  "status": "submitted",
  "total_tnd": "3.500",
  "pickup_slot_id": "pickup-slot-uuid"
}
```

## Critères d'acceptation

### Soumission nominale

Étant donné une Kadhia `draft` non vide et un créneau disponible,
quand le client soumet la commande,
alors une commande `submitted` est créée,
et le client reçoit une confirmation.

### Kadhia vide

Étant donné une Kadhia vide,
quand le client tente de soumettre,
alors l'API refuse la soumission avec une erreur métier claire.

### Kadhia déjà soumise

Étant donné une Kadhia déjà soumise,
quand le client tente de la soumettre à nouveau,
alors l'API refuse l'opération.

### Créneau complet

Étant donné un créneau devenu complet entre l'affichage et la soumission,
quand le client soumet,
alors l'API refuse la soumission et demande de choisir un autre créneau.

### Créneau d'une autre supérette

Étant donné une Kadhia de la supérette A et un créneau de la supérette B,
quand le client tente de soumettre,
alors l'API refuse l'opération.

### Transaction capacité

Étant donné deux clients qui soumettent simultanément le dernier créneau disponible,
quand les deux requêtes arrivent,
alors une seule commande est acceptée si la capacité restante est `1`.

## Tests attendus

- Test soumission nominale.
- Test refus Kadhia vide.
- Test refus Kadhia déjà soumise.
- Test refus créneau passé.
- Test refus créneau complet.
- Test refus créneau d'une autre supérette.
- Test décrément capacité.
- Test transaction ou verrouillage contre la surcapacité.
- Test conservation des prix snapshotés.
- Test statut initial `submitted`.

## Après soumission — suites possibles

Le traitement de la commande par le marchand est couvert au Sprint 3. Les cas à prévoir :

| Statut commande | Impact sur la Kadhia | Action client |
| --- | --- | --- |
| `accepted` | Kadhia reste `submitted` | Attendre la préparation |
| `partially_accepted` | Kadhia revient à `draft` avec lignes acceptées | Modifier et re-soumettre (US-022-A) |
| `rejected` | Kadhia reste `submitted` | Créer une nouvelle Kadhia |
| `cancelled` | Kadhia reste `submitted` | Créer une nouvelle Kadhia |

## Hors périmètre

- Acceptation ou refus marchand (Sprint 3).
- Acceptation partielle côté marchand (Sprint 3).
- Préparation de commande.
- QR code de retrait.
- Paiement en ligne.
- Notification push avancée.
- Annulation client après soumission.

## Dépendances

- US-003 — Ajouter un produit à la Kadhia.
- US-019 — Modifier la Kadhia.
- US-020 — Récapitulatif Kadhia.
- US-004 — Choisir un créneau de retrait.
- Modèle `Order`.

## Définition de fini

La story est terminée lorsque le client peut soumettre une Kadhia `draft` non vide via `POST /api/me/kadhias/{kadhiaId}/submit`, créer une commande au statut `submitted`, et empêcher les doubles soumissions ou les dépassements de capacité.