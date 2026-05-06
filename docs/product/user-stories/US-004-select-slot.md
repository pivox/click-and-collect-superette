# US-004 — Choisir un créneau de retrait

**Epic** : EPIC-004 — Rendez-vous et soumission de commande
**Sprint** : Sprint 2 — Parcours client
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **choisir un créneau de retrait disponible**,
afin de **savoir quand venir récupérer ma Kadhia**.

---

## Préconditions

- Le client a une Kadhia non vide.
- Le marchand a configuré des créneaux de retrait avec une capacité disponible.

---

## Scénario nominal

1. Le client accède à l'étape de choix du créneau depuis sa Kadhia.
2. Le système affiche les créneaux disponibles pour les prochains jours ouvrés.
3. Chaque créneau affiche le jour, la plage horaire et la disponibilité (ex : « Aujourd'hui 14h–15h — Disponible »).
4. Le client sélectionne un créneau.
5. Le créneau sélectionné est mis en surbrillance.
6. Le client peut passer à la confirmation de commande.

---

## Scénarios alternatifs

**Aucun créneau disponible** :
- Le système affiche : « Aucun créneau disponible pour le moment. Revenez plus tard. »
- Le client ne peut pas soumettre la commande.

**Créneau expiré entre sélection et soumission** :
- Le système détecte la collision lors de la soumission.
- Il propose automatiquement le prochain créneau disponible.

---

## Règles métier

- Un créneau est disponible si sa capacité restante est supérieure à 0.
- Les créneaux passés ne sont pas affichés.
- La capacité est décrémentée à la soumission, pas à la sélection.
- Le créneau sélectionné est réservé de façon optimiste pendant 10 minutes maximum.

---

## Critères d'acceptation

- [ ] Les créneaux affichés ont tous une capacité disponible > 0.
- [ ] Les créneaux passés ne sont pas affichés.
- [ ] La sélection d'un créneau est visuellement claire.
- [ ] Si aucun créneau n'est disponible, un message explicite est affiché.
- [ ] Le créneau retenu est correctement associé à la commande lors de la soumission.
- [ ] L'affichage des horaires utilise le format 24h et la timezone Tunisie (UTC+1).

---

## Notes techniques

- Endpoint : `GET /api/shops/{shopId}/pickup-slots?from=today&available=true`
- Réservation optimiste via `reserved_until` timestamp sur le slot.
- Timezone : `Africa/Tunis` (UTC+1, pas de changement d'heure).
- Les créneaux sont des entités `PickupSlot` avec `capacity` et `reservedCount`.
