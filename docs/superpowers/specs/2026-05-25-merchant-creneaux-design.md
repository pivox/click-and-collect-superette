# Spec — Créneaux marchand (frontend)

Date : 2026-05-25  
Priorité : P1 (premier chantier post-catalogue)  
Branche cible : `feat/merchant-creneaux`  
PR de référence : à créer

---

## Contexte

Le backend expose un ensemble complet d'endpoints pour la gestion des créneaux de retrait marchand (Sprint 3b, livré). Cette spec couvre l'interface frontend permettant au marchand de gérer ses règles récurrentes, ses créneaux ponctuels et ses fermetures exceptionnelles depuis l'espace `/merchant/creneaux`.

---

## Périmètre

### Inclus

- **Règles récurrentes** (`PickupSlotRule`) : CRUD + génération de créneaux sur 4 semaines
- **Créneaux ponctuels** (`PickupSlot`) : liste filtrée par jour + CRUD
- **Fermetures exceptionnelles** (`ExceptionalClosure`) : CRUD avec plage date/heure et raison

### Exclus de cette PR

- Horaires d'ouverture (`opening_hours`) : format JSON non typé, appartient à l'écran Paramètres (P2)
- Gestion de la capacité uniquement côté frontend (pas de logique métier dupliquée)
- Notifications push / polling temps réel

---

## Route et navigation

**Route principale :** `/merchant/creneaux`

`MerchantShell` : déplacer "Créneaux" de `DISABLED_NAV` vers `ACTIVE_NAV` avec `href: '/merchant/creneaux'`.

---

## Architecture des fichiers

```
app/merchant/creneaux/
  page.tsx

components/merchant/creneaux/
  DayStrip.tsx
  SlotCard.tsx
  SlotCreateModal.tsx
  RuleAccordion.tsx
  RuleForm.tsx
  ClosureAccordion.tsx
  ClosureForm.tsx
  GenerateBanner.tsx

lib/services/
  merchant-slot-rules.service.ts
  merchant-slots.service.ts
  merchant-closures.service.ts

lib/types/
  merchant-slots.types.ts
```

---

## Types frontend

Fichier : `lib/types/merchant-slots.types.ts`

```typescript
export interface MerchantPickupSlotRule {
  id: string;
  weekday: number;       // 1 = lundi, 7 = dimanche
  start_time: string;    // "HH:MM"
  end_time: string;      // "HH:MM"
  capacity: number;
  is_active: boolean;
}

export interface MerchantPickupSlot {
  id: string;
  starts_at: string;     // ISO 8601
  ends_at: string;
  capacity: number;
  booked_count: number;
  is_active: boolean;
}

export interface MerchantExceptionalClosure {
  id: string;
  starts_at: string;
  ends_at: string;
  reason: string | null;
  is_active: boolean;
}

export interface CreateSlotRulePayload {
  weekday: number;
  start_time: string;
  end_time: string;
  capacity: number;
}

export interface PatchSlotRulePayload {
  weekday?: number;
  start_time?: string;
  end_time?: string;
  capacity?: number;
  is_active?: boolean;
}

export interface CreateSlotPayload {
  starts_at: string;
  ends_at: string;
  capacity: number;
}

export interface PatchSlotPayload {
  capacity?: number;
  is_active?: boolean;
}

export interface CreateClosurePayload {
  starts_at: string;
  ends_at: string;
  reason?: string;
}

export interface PatchClosurePayload {
  starts_at?: string;
  ends_at?: string;
  reason?: string | null;
  is_active?: boolean;
}

export interface GenerateSlotsResult {
  generated_count: number;
}
```

---

## Contrats API consommés

| Action | Méthode | Endpoint |
|---|---|---|
| Lister règles | GET | `/api/merchant/stores/{id}/pickup-slot-rules` |
| Créer règle | POST | `/api/merchant/stores/{id}/pickup-slot-rules` |
| Modifier règle | PATCH | `/api/merchant/stores/{id}/pickup-slot-rules/{ruleId}` |
| Supprimer règle | DELETE | `/api/merchant/stores/{id}/pickup-slot-rules/{ruleId}` |
| Générer créneaux | POST | `/api/merchant/stores/{id}/pickup-slot-rules/generate` |
| Lister créneaux | GET | `/api/merchant/stores/{id}/pickup-slots` |
| Créer créneau | POST | `/api/merchant/stores/{id}/pickup-slots` |
| Modifier créneau | PATCH | `/api/merchant/stores/{id}/pickup-slots/{slotId}` |
| Supprimer créneau | DELETE | `/api/merchant/stores/{id}/pickup-slots/{slotId}` |
| Lister fermetures | GET | `/api/merchant/stores/{id}/exceptional-closures` |
| Créer fermeture | POST | `/api/merchant/stores/{id}/exceptional-closures` |
| Modifier fermeture | PATCH | `/api/merchant/stores/{id}/exceptional-closures/{closureId}` |
| Supprimer fermeture | DELETE | `/api/merchant/stores/{id}/exceptional-closures/{closureId}` |

Le `{id}` est le `store.id` issu du contexte `MerchantAuthContext`.

Le filtrage des créneaux par jour se fait **côté frontend** : `GET /pickup-slots` retourne tous les créneaux, le filtre s'applique sur `starts_at.startsWith(selectedDateISO)`.

---

## Flux UX détaillé

### Chargement initial

Trois appels parallèles au montage : règles, créneaux, fermetures. Spinners indépendants par section pour ne pas bloquer l'affichage global si un appel est lent.

### `DayStrip`

- 14 prochains jours en défilement horizontal (aujourd'hui inclus)
- Chaque pastille : date courte (ex. "Mer 28") + badge avec nombre de créneaux `is_active: true` ce jour
- Jour courant sélectionné par défaut
- Clic sur un jour → met à jour l'état local `selectedDate`, re-filtre les slots sans appel API
- Les jours avec une fermeture exceptionnelle active affichent un indicateur visuel (ex. pastille rouge ou icône)

### `SlotCard`

- Affiche : heure début–fin, capacité totale, `booked_count` réservé, places restantes
- `booked_count = capacity` → badge "Complet", carte grisée
- `is_active: false` → badge "Inactif"
- Bouton édition inline (capacité et statut actif/inactif via PATCH)
- Bouton suppression : si `booked_count > 0`, affiche un message "Ce créneau a des réservations, impossible de le supprimer" sans envoyer de DELETE
- Bouton "＋ Créneau ponctuel" en haut à droite de la section → ouvre `SlotCreateModal`

### `SlotCreateModal`

- Champs : date (input date, pré-remplie avec `selectedDate`), heure début, heure fin, capacité
- Validation côté client : heure début < heure fin, capacité > 0
- Soumission → `POST /pickup-slots` → ferme la modale, rafraîchit la liste des slots

### `RuleAccordion`

- Titre "Règles récurrentes" avec chevron pour replier/déplier
- Ouverte par défaut si aucune règle n'existe (guide l'onboarding)
- Liste des règles : label humain "Lundi 17:00–19:00 · capacité 6 · Actif"
- Bouton "＋ Nouvelle règle" → affiche `RuleForm` inline dans l'accordéon
- Icône poubelle par règle → confirmation inline ("Supprimer cette règle ?") avant DELETE
- Après DELETE : rechargement de la liste des règles

### `RuleForm`

- Champs : jour de la semaine (select : Lundi à Dimanche), heure début (HH:MM), heure fin (HH:MM), capacité
- Validation : heure début < heure fin, capacité > 0, jour entre 1 et 7
- Soumission → `POST /pickup-slot-rules` (201) → accordéon se referme, `GenerateBanner` apparaît

### `GenerateBanner`

**Étape 1 :**
> "Règle créée. Générer les créneaux pour les 4 prochaines semaines ?"  
> Période : Du [date J] au [date J+28]  
> [Générer] [Plus tard]

**Étape 2 (après clic "Générer") :**
- Spinner pendant l'appel `POST /pickup-slot-rules/generate`
- Succès → "X créneaux générés." → bannière disparaît, `DayStrip` et slots se rechargent
- Erreur → message d'erreur inline dans la bannière, bouton réessayer

**"Plus tard" :** bannière disparaît jusqu'au prochain rechargement de page. Pas de persistance locale.

**Réapparition :** si des règles existent et que le total de créneaux futurs (`starts_at > now`) est 0, la bannière réapparaît au chargement avec le message "Aucun créneau futur. Générer depuis vos règles ?".

### `ClosureAccordion`

- Titre "Fermetures exceptionnelles" avec chevron
- Liste des fermetures : plage date/heure + raison (ou "Sans raison")
- `ClosureForm` inline pour création : datetime début, datetime fin, raison (optionnelle, max 255 chars)
- Validation : `starts_at < ends_at`, date dans le futur pour la création
- Icône poubelle par fermeture → confirmation inline avant DELETE

---

## Gestion des erreurs

- Erreur réseau sur chargement initial → message d'erreur par section avec bouton "Réessayer"
- Erreur 403 → redirect vers `/merchant/login` (géré par `MerchantAuthContext`)
- Erreur 422 (validation backend) → afficher le message d'erreur retourné par l'API dans le formulaire concerné
- Erreur 409 (conflit) → message inline spécifique

---

## Tests attendus

### Services

- `merchant-slot-rules.service.test.ts` : listRules, createRule, patchRule, deleteRule, generateSlots
- `merchant-slots.service.test.ts` : listSlots, createSlot, patchSlot, deleteSlot
- `merchant-closures.service.test.ts` : listClosures, createClosure, patchClosure, deleteClosure

### Composants

- `DayStrip` : rendu 14 jours, pastille active, badge count correct, indicateur fermeture
- `SlotCard` : état normal / complet / inactif, blocage suppression si `booked_count > 0`
- `SlotCreateModal` : validation heure début < fin, soumission, fermeture après succès
- `RuleAccordion` : liste règles, label jour (lundi→dimanche), ouverte si vide
- `RuleForm` : validation, soumission, apparition `GenerateBanner`
- `GenerateBanner` : apparaît post-création, appel generate au clic, disparaît après succès, bouton "Plus tard"
- `ClosureAccordion` : liste, formulaire, validation `starts_at < ends_at`

---

## Hypothèses et risques

| Hypothèse | Risque |
|---|---|
| `GET /pickup-slots` retourne tous les créneaux sans pagination | Si le marchand a des centaines de créneaux, le filtrage client peut être lent — à surveiller, pagination à ajouter si nécessaire |
| `GET /pickup-slot-rules/generate` retourne `{ generated_count: N }` | À vérifier sur le backend : le DTO de sortie `PickupSlotRuleGenerationOutput` doit exposer ce champ |
| `MerchantAuthContext` expose bien `merchant.store.id` | Vérifié — `MerchantMe.store.id` existe dans `merchant.types.ts` |
| Filtrage par jour via `starts_at.startsWith(dateISO)` | Fonctionne si le backend retourne des dates ISO 8601 avec timezone UTC — à valider avec un appel réel |

---

## Ordre de livraison conseillé (checkpoints)

1. **Types + services** : `merchant-slots.types.ts`, les 3 services avec mocks
2. **DayStrip + SlotCard** : composants purs, testables sans API
3. **Page principale** : assemblage, chargement parallèle, états vide/erreur
4. **RuleAccordion + RuleForm + GenerateBanner** : flux création règle + génération
5. **SlotCreateModal** : création créneau ponctuel
6. **ClosureAccordion + ClosureForm** : fermetures
7. **MerchantShell** : activer l'entrée "Créneaux"
8. **Tests** : services + composants
9. **tsc + lint + build**
