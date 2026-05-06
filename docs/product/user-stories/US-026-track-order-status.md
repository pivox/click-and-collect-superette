# US-026 — Suivre le statut de sa commande

**Epic** : EPIC-007 — Retrait sécurisé
**Sprint** : Sprint 4 — Retrait sécurisé
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **voir en temps réel le statut de ma commande**,
afin de **savoir quand ma Kadhia est acceptée, préparée et prête à être retirée**.

---

## Préconditions

- Le client a soumis une commande.
- Le client est connecté à l'application.

---

## Scénario nominal

1. Après soumission de la commande, le client accède à l'écran de suivi.
2. L'écran affiche un fil d'avancement avec les étapes :
   - Commande soumise ✓
   - En attente de validation (en cours)
   - Acceptée
   - En préparation
   - Prête à retirer
   - Retirée
3. L'étape courante est mise en évidence.
4. Les étapes passées sont cochées.
5. Une notification push (ou email) est envoyée à chaque transition importante.

---

## Transitions notifiées

| Transition | Message au client |
|---|---|
| `submitted` → `accepted` | « Votre commande a été acceptée par [supérette]. » |
| `submitted` → `rejected` | « Votre commande a été refusée : [raison]. » |
| `accepted` → `preparing` | « Votre Kadhia est en cours de préparation. » |
| `preparing` → `ready` | « Votre Kadhia est prête ! Présentez votre QR code. » |
| `pickup_pending` → `completed` | « Retrait finalisé. Merci ! » |

---

## Scénarios alternatifs

**Commande refusée** :
- L'étape « Refusée » est affichée avec la raison du refus.
- Le client peut consulter les détails et retourner au catalogue.

**Commande annulée par le client** :
- L'étape « Annulée » est affichée.

---

## Règles métier

- Tous les statuts de commande sont visibles dans l'historique.
- Le client ne peut pas modifier une commande au-delà de `submitted`.
- L'annulation par le client n'est possible qu'en statut `submitted` (avant réponse marchand).

---

## Critères d'acceptation

- [ ] Le fil d'avancement reflète toujours le statut réel de la commande.
- [ ] Les notifications sont envoyées aux transitions clés.
- [ ] Une commande refusée affiche la raison du refus.
- [ ] Le client peut annuler une commande `submitted` depuis l'écran de suivi.
- [ ] L'historique complet des commandes passées est accessible.

---

## Notes techniques

- Endpoint : `GET /api/orders/{id}` retourne le statut courant et les transitions horodatées.
- Polling toutes les 30 secondes dans le MVP pour simuler le temps réel.
- Mercure push en post-MVP si le polling se révèle insuffisant.
- L'annulation : `PATCH /api/orders/{id}/cancel` (garde : statut == `submitted`).
