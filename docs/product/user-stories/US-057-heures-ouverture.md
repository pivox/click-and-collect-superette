# US-057 — Afficher et configurer les heures d'ouverture de la supérette

**Epic** : EPIC-002 — Catalogue et disponibilité
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Should Have

---

## Récit

En tant que **client**,
je veux **voir les heures d'ouverture de la supérette avant de composer ma Kadhia**,
afin de **savoir si je peux passer récupérer ma commande dans les horaires prévus**.

En tant que **marchand**,
je veux **configurer les heures d'ouverture habituelles de ma supérette**,
afin de **les afficher automatiquement sur ma vitrine publique**.

---

## Préconditions

- La supérette existe et est active.
- Le marchand est connecté et propriétaire de la supérette.

---

## Scénario nominal — Client

1. Le client accède à la page de la supérette (via QR code ou recherche).
2. Il voit les heures d'ouverture hebdomadaires sous le nom et l'adresse de la supérette.
3. Si la supérette est actuellement ouverte, un badge vert « Ouvert » est affiché.
4. Si elle est fermée, un badge gris « Fermé — ouvre lundi à 8h00 » est affiché.

---

## Scénario nominal — Marchand

1. Le marchand accède aux paramètres de sa supérette.
2. Il clique sur « Heures d'ouverture ».
3. Pour chaque jour de la semaine, il définit une ou deux plages horaires (ex. 8h–13h et 15h–20h).
4. Il peut marquer un jour comme fermé.
5. Il sauvegarde.
6. Les heures sont immédiatement visibles sur la vitrine publique.

---

## Règles métier

- Les heures d'ouverture sont indicatives : elles n'empêchent pas la composition d'une Kadhia hors horaires.
- Seuls les créneaux de retrait (`PickupSlot`) déterminent effectivement quand une commande peut être retirée.
- Un jour sans plage configurée est affiché « Fermé ».
- Les fuseaux horaires sont `Africa/Tunis` par défaut.
- Maximum 2 plages par jour (pause méridienne possible).

---

## Critères d'acceptation

- [ ] Le marchand peut configurer des plages horaires pour chaque jour de la semaine.
- [ ] Les heures d'ouverture sont visibles sur la vitrine publique de la supérette.
- [ ] Un indicateur « Ouvert » / « Fermé » est affiché en temps réel côté client.
- [ ] Un jour sans horaire configuré affiche « Fermé ».
- [ ] L'API publique retourne les heures d'ouverture dans la réponse de la supérette.

---

## Notes techniques

**Champ à ajouter sur `Shop` :**
```php
#[ORM\Column(type: Types::JSON, nullable: true)]
private ?array $openingHours = null;
```

**Format JSON `openingHours` :**
```json
{
  "monday":    [{"open": "08:00", "close": "13:00"}, {"open": "15:00", "close": "20:00"}],
  "tuesday":   [{"open": "08:00", "close": "20:00"}],
  "wednesday": [],
  "thursday":  [{"open": "08:00", "close": "20:00"}],
  "friday":    [{"open": "08:00", "close": "13:00"}],
  "saturday":  [{"open": "09:00", "close": "13:00"}],
  "sunday":    []
}
```

**Endpoints :**
```http
GET   /api/stores/{storeId}           → inclut opening_hours dans le DTO public
PATCH /api/merchant/stores/{storeId}/opening-hours
```

**Payload PATCH :**
```json
{
  "opening_hours": {
    "monday": [{"open": "08:00", "close": "20:00"}],
    "sunday": []
  }
}
```

**Calcul « Ouvert maintenant » :**
Effectué côté client (JavaScript) à partir du JSON `opening_hours` et de l'heure locale du device (Africa/Tunis). Pas de calcul serveur nécessaire dans le MVP.

**Migration :** ajouter la colonne `opening_hours JSONB` sur la table `shops`.
