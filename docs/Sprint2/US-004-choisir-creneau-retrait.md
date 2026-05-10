# US-004 — Choisir un créneau de retrait

## Sprint

Sprint 2 — Parcours client.

## Epic rattaché

EPIC-004 — Rendez-vous et soumission de commande.

## Objectif produit

Permettre au client de choisir un créneau de retrait disponible avant de soumettre sa commande.

Le créneau donne de la visibilité au marchand sur la charge de préparation et permet au client de savoir quand venir récupérer sa Kadhia.

## Récit utilisateur

En tant que client connecté,
je veux choisir un créneau de retrait disponible,
afin d'indiquer au marchand quand je viendrai récupérer ma commande.

## Acteurs

- Client connecté.
- Supérette active.
- Créneaux de retrait configurés.
- Plateforme Click & Collect.

## Préconditions

- Le client est authentifié.
- Le client possède une Kadhia `draft` non vide.
- La supérette est active.
- La supérette possède des créneaux de retrait configurés.
- Au moins un créneau futur a une capacité restante.

## Parcours nominal

1. Le client consulte le récapitulatif de sa Kadhia.
2. Il clique sur continuer ou choisir un créneau.
3. Le frontend demande les créneaux disponibles pour la supérette.
4. Le backend retourne uniquement les créneaux futurs avec capacité restante.
5. Le client sélectionne un créneau.
6. Le créneau sélectionné est conservé jusqu'à la soumission de commande.

## Règles métier

- Un créneau appartient à une seule supérette.
- Le client ne voit que les créneaux de la supérette de sa Kadhia.
- Un créneau passé ne doit jamais être proposé.
- Un créneau complet ne doit pas être proposé.
- La timezone de référence MVP est `Africa/Tunis`.
- La capacité restante doit être vérifiée de nouveau au moment de la soumission de commande.
- La sélection d'un créneau ne crée pas encore une commande.
- La sélection d'un créneau ne garantit pas définitivement la capacité tant que la commande n'est pas soumise.

## Données créneau attendues

Chaque créneau doit contenir au minimum :

- identifiant du créneau ;
- `store_id` ;
- début ;
- fin ;
- timezone ;
- capacité maximale ;
- capacité restante ;
- état disponible.

## API cible

Endpoint protégé client ou public selon décision produit. Recommandation MVP : public en lecture, mais filtré par supérette.

```http
GET /api/stores/{storeId}/pickup-slots?from=today&available=true
```

Réponse attendue :

```json
{
  "store_id": "store-uuid",
  "items": [
    {
      "id": "pickup-slot-uuid",
      "starts_at": "2026-05-11T10:00:00+01:00",
      "ends_at": "2026-05-11T10:30:00+01:00",
      "timezone": "Africa/Tunis",
      "capacity": 5,
      "remaining_capacity": 3,
      "is_available": true
    }
  ]
}
```

## Critères d'acceptation

### Créneaux disponibles

Étant donné une supérette avec des créneaux futurs disponibles,
quand le client demande les créneaux,
alors l'API retourne uniquement les créneaux avec capacité restante.

### Créneau passé

Étant donné un créneau dans le passé,
quand le client consulte les créneaux,
alors ce créneau n'est pas retourné.

### Créneau complet

Étant donné un créneau dont la capacité restante est `0`,
quand le client consulte les créneaux,
alors ce créneau n'est pas proposé comme disponible.

### Isolation supérette

Étant donné deux supérettes,
quand le client consulte les créneaux de la supérette A,
alors aucun créneau de la supérette B ne doit être retourné.

### Revalidation à la soumission

Étant donné un créneau sélectionné,
quand le client soumet la commande,
alors le backend vérifie à nouveau que le créneau est encore disponible.

## Tests attendus

- Test liste des créneaux futurs disponibles.
- Test exclusion des créneaux passés.
- Test exclusion des créneaux complets.
- Test isolation par `storeId`.
- Test format timezone `Africa/Tunis`.
- Test revalidation au moment de la soumission, à couvrir avec US-021.

## Hors périmètre

- Configuration des créneaux par le marchand.
- Génération automatique de planning.
- Liste d'attente.
- Réservation temporaire de créneau avant soumission.
- Modification de créneau après commande soumise.

## Dépendances

- US-020 — Récapitulatif Kadhia.
- Modèle `PickupSlot`.
- Capacité de créneau.
- US-021 — Soumettre la commande.

## Définition de fini

La story est terminée lorsque le client peut voir les créneaux futurs disponibles de la supérette, en sélectionner un, et poursuivre vers la soumission de commande.