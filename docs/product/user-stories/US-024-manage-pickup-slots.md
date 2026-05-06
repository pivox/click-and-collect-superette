# US-024 — Configurer les créneaux de retrait de la supérette

**Epic** : EPIC-012 — Gestion des créneaux de retrait
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Must Have

---

## Récit

En tant que **marchand**,
je veux **configurer les créneaux horaires de retrait de ma supérette et leur capacité**,
afin de **contrôler le flux des retraits et ne pas être débordé**.

---

## Préconditions

- Le marchand est connecté à son backoffice.
- La supérette est active.

---

## Scénario nominal

1. Le marchand accède à la section « Créneaux de retrait » dans ses paramètres.
2. Il voit le planning de la semaine avec les créneaux existants.
3. Il crée un nouveau créneau en sélectionnant : jour de la semaine (ou date précise), heure de début, heure de fin, capacité maximale.
4. Il sauvegarde.
5. Le créneau est immédiatement disponible à la sélection pour les clients (si dans le futur).

---

## Scénario nominal — Modification d'un créneau

1. Le marchand sélectionne un créneau existant.
2. Il modifie la capacité ou les horaires.
3. Il sauvegarde.

---

## Scénario nominal — Désactivation d'un créneau

1. Le marchand désactive un créneau (ex : jour férié).
2. Le créneau n'est plus visible pour les nouveaux clients.
3. Les commandes déjà associées à ce créneau ne sont pas affectées.

---

## Scénarios alternatifs

**Conflit horaire** :
- Le système refuse la création d'un créneau qui chevauche un créneau existant le même jour.

**Créneau passé** :
- Le marchand ne peut pas modifier un créneau passé ayant des commandes associées.

---

## Règles métier

- La capacité d'un créneau est le nombre maximum de commandes simultanées acceptées.
- Un créneau désactivé n'est plus visible pour les clients mais les commandes existantes restent valides.
- Les créneaux récurrents (ex : tous les mardis 14h–15h) peuvent être définis par le marchand.
- Dans le MVP : les créneaux sont gérés manuellement, sans génération automatique.

---

## Critères d'acceptation

- [ ] Le marchand peut créer un créneau avec jour, plage horaire et capacité.
- [ ] Le créneau créé est visible pour les clients dans les délais corrects.
- [ ] La modification de capacité est effective immédiatement.
- [ ] La désactivation d'un créneau le masque des nouveaux choix client sans affecter les commandes existantes.
- [ ] Un conflit horaire est détecté et signalé.

---

## Notes techniques

- Endpoint : `POST /api/merchant/pickup-slots` avec `{ dayOfWeek?, date?, startTime, endTime, capacity }`
- Endpoint : `PATCH /api/merchant/pickup-slots/{id}` avec `{ capacity?, active? }`
- Entité : `PickupSlot` avec champs `startAt`, `endAt`, `capacity`, `reservedCount`, `active`.
- La disponibilité côté client est calculée : `active = true AND reservedCount < capacity AND startAt > now()`.
