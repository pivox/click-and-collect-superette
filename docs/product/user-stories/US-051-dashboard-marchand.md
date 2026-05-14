# US-051 — Tableau de bord journalier du marchand

**Epic** : EPIC-005 — Validation marchand
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Must Have

---

## Récit

En tant que **marchand**,
je veux **voir en un coup d'œil l'état de mes commandes du jour**,
afin de **prioriser mes actions sans fouiller dans des listes filtrées**.

---

## Préconditions

- Le marchand est connecté et propriétaire de la supérette.

---

## Contenu du dashboard

```
┌─────────────────────────────────────────┐
│ Supérette Ezzahra — Mercredi 14 mai 2026│
├──────────┬──────────┬──────────┬────────┤
│ En attente│ Acceptées│En prép.  │ Prêtes │
│    3      │    2     │    1     │   1    │
├──────────┴──────────┴──────────┴────────┤
│ Prochain créneau : 10h–11h (2 commandes)│
│ Créneau suivant  : 11h–12h (1 commande) │
└─────────────────────────────────────────┘
```

Sections détaillées :
- **À traiter maintenant** : commandes `submitted` avec créneau dans < 3 heures.
- **En cours** : commandes `accepted` ou `preparing`.
- **Prêtes** : commandes `ready` en attente de retrait.
- **Créneaux du jour** : liste des créneaux avec comptage de commandes associées.

---

## Règles métier

- Le dashboard porte sur les commandes du jour (créneau `starts_at` entre minuit et 23h59 aujourd'hui, fuseau Africa/Tunis).
- Les commandes urgentes (`submitted` avec créneau < 3h) sont mises en évidence (badge rouge).
- Le dashboard est en lecture seule — les actions (accepter, préparer) se font depuis le détail de commande.
- Actualisé toutes les 30 secondes par le frontend (polling simple dans le MVP).

---

## Critères d'acceptation

- [ ] Le dashboard affiche les compteurs par statut pour les commandes du jour.
- [ ] Les commandes urgentes (créneau < 3h) sont signalées visuellement.
- [ ] Les créneaux du jour et leur remplissage sont visibles.
- [ ] Le clic sur un compteur ouvre la liste filtrée correspondante.
- [ ] Les données se rafraîchissent sans rechargement complet de la page.

---

## Notes techniques

**Endpoint :**
```http
GET /api/merchant/stores/{storeId}/dashboard
```

**Réponse :**
```json
{
  "date": "2026-05-14",
  "counts": {
    "submitted": 3,
    "accepted": 2,
    "preparing": 1,
    "ready": 1
  },
  "urgent_submitted": 1,
  "slots_today": [
    { "slot_id": "<uuid>", "starts_at": "10:00", "ends_at": "11:00", "order_count": 2, "capacity": 5 },
    { "slot_id": "<uuid>", "starts_at": "11:00", "ends_at": "12:00", "order_count": 1, "capacity": 5 }
  ]
}
```

**Requête SQL optimisée :** une seule requête avec `GROUP BY status` sur les commandes du jour, filtrée par `shop_id`.

**Sécurité :** `MerchantShopAccessChecker`.
