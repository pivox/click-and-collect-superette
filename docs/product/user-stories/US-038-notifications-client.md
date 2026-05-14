# US-038 — Recevoir des notifications sur l'évolution de sa commande (client)

**Epic** : EPIC-014 — Notifications MVP
**Sprint** : Sprint 4 — Retrait sécurisé
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **être informé des changements de statut de ma commande**,
afin de **savoir quand récupérer ma Kadhia sans avoir à consulter l'application en permanence**.

---

## Préconditions

- Le client est connecté.
- Une commande est soumise.

---

## Scénario nominal

1. Le client soumet sa commande.
2. Le marchand l'accepte → une notification est créée : « Votre commande a été acceptée. »
3. Le marchand passe en préparation → notification : « Votre Kadhia est en cours de préparation. »
4. Le marchand marque la commande prête → notification : « Votre Kadhia est prête ! Présentez votre QR code. »
5. La commande est finalisée → notification : « Retrait confirmé. Merci ! »
6. Le client peut consulter ses notifications depuis le menu (badge de comptage).
7. Il peut marquer une notification comme lue.

---

## Notifications déclenchées

| Transition | Titre FR | Corps FR |
|---|---|---|
| `submitted` → `accepted` | « Commande acceptée » | « [Supérette] a accepté votre commande #[ref]. » |
| `submitted` → `partially_accepted` | « Commande partiellement acceptée » | « [Supérette] a modifié votre commande. Vérifiez votre Kadhia. » |
| `submitted` → `rejected` | « Commande refusée » | « [Supérette] a refusé votre commande : [raison]. » |
| `accepted` → `preparing` | « En préparation » | « Votre Kadhia est en cours de préparation. » |
| `preparing` → `ready` | « Kadhia prête ! » | « Votre Kadhia est prête. Présentez votre QR code en supérette. » |
| `pickup_pending` → `completed` | « Retrait confirmé » | « Retrait finalisé. À bientôt chez [Supérette] ! » |

---

## Règles métier

- Les notifications sont persistées en base et retournées via API (MVP : pas de push mobile).
- Une notification non lue est comptabilisée dans le badge du menu.
- Les notifications sont liées à une commande et cliquables (deeplink vers la commande).
- Le client ne peut lire que ses propres notifications.
- Une notification lue reste visible dans l'historique (pas de suppression dans le MVP).

---

## Critères d'acceptation

- [ ] Une notification est créée pour chaque transition de statut listée ci-dessus.
- [ ] Le client peut lister ses notifications (paginées, les plus récentes en premier).
- [ ] Le badge indique le nombre de notifications non lues.
- [ ] Le client peut marquer une notification comme lue.
- [ ] Une notification contient : titre, corps, date, lien vers la commande, statut lu/non-lu.
- [ ] Les notifications du client ne sont pas visibles par d'autres utilisateurs.

---

## Notes techniques

**Nouvelle entité `Notification` :**
```text
notification
- id (uuid)
- user_id
- order_id (nullable)
- title_fr
- title_ar
- body_fr
- body_ar
- is_read (bool, default false)
- created_at
INDEX(user_id, is_read, created_at)
```

**Endpoints :**
```http
GET   /api/me/notifications?page=1&unread=true
PATCH /api/me/notifications/{id}/read
PATCH /api/me/notifications/read-all
```

**GET réponse 200 :**
```json
{
  "items": [
    {
      "id": "<uuid>",
      "title": "Kadhia prête !",
      "body": "Votre Kadhia est prête. Présentez votre QR code en supérette.",
      "order_id": "<uuid>",
      "is_read": false,
      "created_at": "2026-05-15T14:30:00+01:00"
    }
  ],
  "total": 5,
  "unread_count": 2,
  "page": 1,
  "per_page": 20
}
```

- Créer un `NotificationService::createForOrderTransition()` appelé depuis chaque Processor de transition.
- Le titre et le corps sont stockés en FR et AR. La réponse API retourne la bonne langue selon le header `Accept-Language`.
- Pas de push/SMS dans le MVP. Le polling côté frontend (toutes les 30s) suffit.
