# Design — Amélioration UX créneaux marchand

**Date :** 2026-05-27  
**Périmètre :** Frontend (Next.js) + Backend mineur (Symfony/API Platform)  
**Pages concernées :** `/merchant/creneaux` + toutes les pages marchands (warning global)

---

## Contexte

La page `/merchant/creneaux` permet au marchand de gérer ses règles récurrentes de créneaux de retrait (`PickupSlotRule`) et ses créneaux concrets (`PickupSlot`). Deux problèmes UX identifiés :

1. Le formulaire de création de règle ne permet de choisir qu'**un seul jour** à la fois via un `<select>` — créer des règles du lundi au vendredi nécessite 5 soumissions.
2. Le bouton de génération de créneaux n'apparaît qu'**après la création d'une règle** (bannière éphémère) — un marchand qui revient après plusieurs jours ne sait pas que ses créneaux sont épuisés.

---

## Décisions d'architecture

- On **conserve le modèle existant** : `PickupSlotRule` (1 règle = 1 jour) + `PickupSlot` (instances concrètes générées).
- Pas de matérialisation paresseuse, pas de refonte du modèle `Order`. Blast radius minimal.
- Le backend reste la source de vérité ; les créneaux concrets doivent exister pour qu'une commande puisse référencer un `pickup_slot_id`.

---

## Changement 1 — Sélecteur multi-jours dans RuleForm

### Comportement

Remplacer le `<select>` (un seul jour) par 7 chips cliquables (Lundi → Dimanche), tous **pré-cochés par défaut**. Le marchand décoche les jours non souhaités avant de soumettre.

```
[ Lun ] [ Mar ] [ Mer ] [ Jeu ] [ Ven ] [·Sam·] [·Dim·]
Heure début : [17:00]   Heure fin : [19:00]   Capacité : [6]
[Ajouter la règle]
```

### Règles

- Au moins 1 jour doit être sélectionné (validation côté client).
- Au submit, `RuleForm` itère sur les jours sélectionnés et appelle `onSubmit(payload)` une fois par jour — la signature `onSubmit: (payload: CreateSlotRulePayload) => Promise<void>` du parent est inchangée.
- Le backend reçoit toujours des requêtes unitaires `POST /api/merchant/stores/{storeId}/pickup-slot-rules` avec `weekday: number` — pas de changement backend.
- Si une règle échoue (ex. doublon 409), les autres jours continuent d'être soumis. Les erreurs sont agrégées et affichées à la fin.

### Fichiers modifiés

| Fichier | Nature |
|---|---|
| `apps/frontend/src/components/merchant/creneaux/RuleForm.tsx` | Réécriture du champ jour |

---

## Changement 2 — Bouton Générer permanent dans RuleAccordion

### Comportement

- Supprimer la `GenerateBanner` (pop-up post-création) du flux de `page.tsx`.
- Afficher en bas de `RuleAccordion`, quand au moins une règle existe, une section "Générer les créneaux" avec deux boutons : **Générer 1 mois** et **Générer 3 mois**.
- Après génération : affichage inline du résultat (`N créneaux générés`).

```
╔─ Règles récurrentes ─────────────────────────────────╗
│  ● Lundi 17:00–19:00 · capacité 6           [🗑]    │
│  ● Mardi 17:00–19:00 · capacité 6           [🗑]    │
│  ── Générer les créneaux ──────────────────────────  │
│  [ Générer 1 mois ]  [ Générer 3 mois ]             │
│  ✓ 14 créneaux générés.                             │
╚─────────────────────────────────────────────────────╝
```

### Backend — paramètre horizon

L'endpoint `POST /api/merchant/stores/{storeId}/pickup-slot-rules/generate` accepte désormais un corps JSON :

```json
{ "horizon_months": 1 }   // ou 3
```

**Nouveau DTO :** `GenerateSlotsInput`
```php
class GenerateSlotsInput {
    #[Assert\NotNull]
    #[Assert\Choice([1, 3])]
    public ?int $horizonMonths = 1;
}
```

**`PickupSlotRuleGenerator::generateForShop()`** : remplace `'+4 weeks'` par `"+{$horizonMonths} months"` (paramètre `int $horizonMonths = 1`).

**`GenerateMerchantPickupSlotRulesProcessor`** : lit `$data->horizonMonths` depuis l'input et le passe au service.

**`PickupSlotRuleGenerationOutput`** : `input: false` → `input: GenerateSlotsInput::class`.

### Fichiers modifiés

| Fichier | Nature |
|---|---|
| `apps/frontend/src/components/merchant/creneaux/RuleAccordion.tsx` | Section génération permanente |
| `apps/frontend/src/components/merchant/creneaux/GenerateBanner.tsx` | Plus importée dans page.tsx |
| `apps/frontend/src/app/merchant/creneaux/page.tsx` | Suppression GenerateBanner, `showBanner` state |
| `apps/frontend/src/lib/services/merchant-slot-rules.service.ts` | `generateMerchantSlots(storeId, horizonMonths)` |
| `apps/frontend/src/lib/types/merchant-slots.types.ts` | Type `GenerateSlotsPayload` |
| `apps/backend/src/Dto/GenerateSlotsInput.php` | **Nouveau** |
| `apps/backend/src/Service/PickupSlotRuleGenerator.php` | Paramètre `horizonMonths` |
| `apps/backend/src/Processor/GenerateMerchantPickupSlotRulesProcessor.php` | Lecture input |
| `apps/backend/src/ApiResource/PickupSlotRuleGenerationOutput.php` | `input:` mis à jour |

---

## Changement 3 — Warning global j+6 dans MerchantShell

### Comportement

`MerchantShell` charge `listMerchantSlots(storeId)` au montage. Si aucun slot n'a son `starts_at` dans les 6 prochains jours → afficher un bandeau d'alerte **au-dessus du contenu** de toutes les pages marchands.

```
⚠ Aucun créneau disponible dans les 6 prochains jours.
  Vos clients ne pourront pas passer de commande.
  → Aller dans Créneaux pour générer 1 ou 3 mois de créneaux.   [×]
```

### Règles

- Le bandeau est **dismissible** (état local `dismissed`, non persisté en localStorage — il réapparaît à la prochaine navigation).
- Il n'apparaît **pas** sur `/merchant/login`.
- Le check est re-effectué à chaque montage de `MerchantShell` (navigation entre pages).
- Pas de nouvel endpoint backend : on réutilise `GET /api/merchant/stores/{id}/pickup-slots` existant.
- La logique de calcul j+6 est dans un nouveau composant `SlotCoverageWarning` (reçoit les slots en props, calcule et affiche).

### Fichiers modifiés

| Fichier | Nature |
|---|---|
| `apps/frontend/src/components/merchant/MerchantShell.tsx` | Appel `listMerchantSlots` + rendu warning |
| `apps/frontend/src/components/merchant/SlotCoverageWarning.tsx` | **Nouveau** composant warning |

---

## Récapitulatif des fichiers

### Backend (5 fichiers)
- `src/Dto/GenerateSlotsInput.php` — nouveau
- `src/ApiResource/PickupSlotRuleGenerationOutput.php` — modifier `input:`
- `src/Service/PickupSlotRuleGenerator.php` — ajouter param `horizonMonths`
- `src/Processor/GenerateMerchantPickupSlotRulesProcessor.php` — lire input
- Tests : `tests/Functional/Api/MerchantPickupSlotRuleApiTest.php` — couvrir `horizon_months: 1` et `3`

### Frontend (8 fichiers)
- `src/components/merchant/creneaux/RuleForm.tsx` — chips multi-jours
- `src/components/merchant/creneaux/RuleAccordion.tsx` — section génération
- `src/components/merchant/creneaux/GenerateBanner.tsx` — retirer du flow (fichier conservé)
- `src/app/merchant/creneaux/page.tsx` — nettoyer `showBanner`
- `src/lib/services/merchant-slot-rules.service.ts` — paramètre `horizonMonths`
- `src/lib/types/merchant-slots.types.ts` — type payload génération
- `src/components/merchant/MerchantShell.tsx` — appel slots + rendu warning
- `src/components/merchant/SlotCoverageWarning.tsx` — nouveau

---

## Risques et limites

| Risque | Niveau | Mitigation |
|---|---|---|
| Règle doublon sur un des jours (409) lors de création multi-jours | Faible | Agrégation des erreurs, les autres jours continuent |
| `listMerchantSlots` dans MerchantShell ajoute 1 requête par page | Faible | Volume de slots faible en MVP ; acceptable |
| `GenerateSlotsInput` avec `horizon_months: 3` génère beaucoup de slots (ex. 200+) | Faible | Idempotent, `hasActiveOverlapForShop` protège les doublons |
| `GenerateBanner` toujours dans le code mais inutilisée | Cosmétique | À supprimer dans une prochaine PR de nettoyage |
