# US-047 — Configurer des créneaux de retrait récurrents

**Epic** : EPIC-012 — Gestion des créneaux de retrait
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Must Have

---

## Récit

En tant que **marchand**,
je veux **définir un créneau récurrent** (ex : « tous les jours de 10h à 11h, capacité 5 »),
afin de **ne pas avoir à créer manuellement chaque créneau pour toute la semaine**.

---

## Préconditions

- Le marchand est connecté et propriétaire de la supérette.

---

## Scénario nominal — Création d'un créneau récurrent

1. Le marchand accède à « Mes créneaux ».
2. Il clique sur « Ajouter un créneau récurrent ».
3. Il configure :
   - Jours de la semaine (cases à cocher : Lu, Ma, Me, Je, Ve, Sa, Di).
   - Heure de début et heure de fin.
   - Capacité maximale.
   - Date de début de la récurrence.
   - Date de fin (optionnelle — sinon, récurrence indéfinie).
4. Il valide.
5. Le système génère les `PickupSlot` correspondants sur 4 semaines glissantes.
6. Les créneaux générés sont visibles dans le planning semaine.

---

## Scénario nominal — Modification d'un créneau récurrent

1. Le marchand modifie la capacité ou les jours.
2. Les créneaux futurs non encore réservés sont mis à jour.
3. Les créneaux passés ou déjà réservés ne sont pas modifiés.

---

## Scénario nominal — Suppression d'un créneau récurrent

1. Le marchand supprime la règle de récurrence.
2. Les créneaux futurs sans réservation sont supprimés.
3. Les créneaux avec des commandes restent actifs.

---

## Règles métier

- La génération est glissante sur **4 semaines** à partir d'aujourd'hui.
- Un job planifié (`Scheduler`) régénère les créneaux chaque semaine pour maintenir 4 semaines d'avance.
- Les créneaux déjà générés et non modifiés par le marchand ne sont pas recréés (idempotence).
- Un créneau récurrent et un créneau ponctuel peuvent coexister.
- La modification d'une règle de récurrence ne s'applique qu'aux créneaux **futurs non réservés**.

---

## Critères d'acceptation

- [ ] Le marchand peut définir une règle avec jours, horaire et capacité.
- [ ] Les créneaux sont générés automatiquement sur 4 semaines.
- [ ] Les clients voient immédiatement les nouveaux créneaux.
- [ ] La modification met à jour les créneaux futurs non réservés uniquement.
- [ ] La suppression ne touche pas les créneaux avec commandes existantes.
- [ ] Un job planifié maintient 4 semaines de créneaux en avance.

---

## Notes techniques

**Nouvelle entité `PickupSlotRule` :**
```text
pickup_slot_rules
- id (uuid)
- shop_id
- days_of_week (json — [1,2,3,4,5] pour lun–ven)
- start_time (time — ex: "10:00")
- end_time   (time — ex: "11:00")
- capacity
- valid_from (date)
- valid_until (date, nullable)
- is_active (bool)
- created_at, updated_at
```

**Job de génération — Symfony Scheduler :**
```php
// Tourne chaque lundi à 00h00
#[AsSchedule]
class GeneratePickupSlotsSchedule implements ScheduleProviderInterface { ... }
```

Le handler crée les `PickupSlot` manquants pour les 4 semaines suivantes en respectant les règles actives de chaque supérette.

**Endpoints :**
```http
GET    /api/merchant/stores/{storeId}/pickup-slot-rules
POST   /api/merchant/stores/{storeId}/pickup-slot-rules
PATCH  /api/merchant/stores/{storeId}/pickup-slot-rules/{ruleId}
DELETE /api/merchant/stores/{storeId}/pickup-slot-rules/{ruleId}
```
