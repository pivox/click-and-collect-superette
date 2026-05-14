# US-048 — Informer le client quand aucun créneau n'est disponible

**Epic** : EPIC-004 — Rendez-vous et soumission de commande
**Sprint** : Sprint 2 — Parcours client
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **voir un message clair quand aucun créneau de retrait n'est disponible**,
afin de **comprendre pourquoi je ne peux pas soumettre ma commande et savoir quoi faire**.

---

## Préconditions

- Le client a composé une Kadhia.
- Il accède à l'étape de sélection du créneau.

---

## Cas couverts

| Situation | Message affiché |
|---|---|
| Aucun créneau configuré par le marchand | « Cette supérette n'a pas encore configuré ses créneaux de retrait. Revenez bientôt. » |
| Tous les créneaux sont complets | « Tous les créneaux sont complets pour le moment. Revenez plus tard ou essayez un autre jour. » |
| Aucun créneau dans les prochaines 24 heures | « Aucun créneau disponible aujourd'hui. Les prochains créneaux sont disponibles à partir du [date]. » |
| Créneau dans moins de 2 heures (règle US-043) | « Ce créneau est trop proche. Choisissez un créneau dans au moins 2 heures. » |

---

## Scénario nominal — Aucun créneau

1. Le client arrive à l'étape « Choisir un créneau ».
2. La liste est vide.
3. Un message contextuel explique la situation avec une icône illustrative.
4. Un bouton « Voir le catalogue à nouveau » permet de retourner sans perdre la Kadhia.
5. La Kadhia reste en `draft` (elle n'est pas supprimée).

---

## Règles métier

- La Kadhia en `draft` est conservée même si le client quitte l'étape de sélection du créneau.
- Le client peut revenir plus tard, retrouver sa Kadhia et soumettre quand un créneau est disponible.
- Le message est différencié selon la cause réelle (aucun créneau vs. tous complets).
- L'API retourne une collection vide avec un champ `reason` expliquant l'absence.

---

## Critères d'acceptation

- [ ] Quand aucun créneau n'existe, un message explicite s'affiche (pas une page vide ou une erreur 404).
- [ ] Quand tous les créneaux sont complets, le message le dit clairement.
- [ ] Le prochain créneau disponible est affiché si connu (même complet).
- [ ] La Kadhia est conservée pendant cette attente.
- [ ] Le client peut continuer à modifier sa Kadhia pendant qu'il attend un créneau.

---

## Notes techniques

**Réponse API quand aucun créneau :**
```json
{
  "store_id": "<uuid>",
  "items": [],
  "reason": "NO_SLOTS_CONFIGURED",
  "next_available_at": null
}
```

**Valeurs possibles de `reason` :**
- `NO_SLOTS_CONFIGURED` — aucun `PickupSlot` pour cette supérette
- `ALL_SLOTS_FULL` — tous les créneaux futurs ont `booked_count >= capacity`
- `NO_UPCOMING_SLOTS` — pas de créneau dans les 7 prochains jours

**`next_available_at` :** date du prochain créneau non complet, même s'il est dans plusieurs jours.

**Modification dans `PickupSlotCollectionProvider` :** calculer le `reason` et `next_available_at` si la collection est vide.
