# US-058 — Fermer définitivement une supérette (admin)

**Epic** : EPIC-009 — Administration plateforme
**Sprint** : Sprint 5 — Administration minimale
**Priorité** : Should Have

---

## Récit

En tant qu'**administrateur plateforme**,
je veux **archiver définitivement une supérette qui cesse son activité**,
afin de **préserver l'historique des commandes sans laisser la supérette accessible aux clients**.

---

## Préconditions

- L'administrateur est connecté avec `ROLE_ADMIN`.
- La supérette existe et est active (ou suspendue).

---

## Scénario nominal

1. L'administrateur accède à la gestion des supérettes.
2. Il sélectionne la supérette à fermer définitivement.
3. Il clique sur « Fermer définitivement ».
4. Un écran de confirmation affiche :
   - Nombre de commandes actives sur la supérette.
   - Avertissement : « Cette action est irréversible depuis l'interface. »
5. L'administrateur saisit le motif de fermeture et confirme.
6. Le système :
   a. Annule toutes les commandes dont le statut est `submitted` ou `accepted` (avec notification client).
   b. Désactive tous les créneaux de retrait (`PickupSlot.isActive = false`).
   c. Supprime le `qrCodeToken` (ou le révoque) pour invalider les QR codes existants.
   d. Passe `Shop.active = false` et `Shop.archivedAt = now()`.
7. Le catalogue public de la supérette n'est plus accessible.
8. L'historique des commandes reste consultable par l'admin.

---

## Scénario alternatif — Commandes en cours de préparation ou prêtes

1. Si des commandes sont au statut `preparing` ou `ready`, le système bloque la fermeture.
2. L'administrateur voit un message : « 2 commandes sont en cours de préparation. Finalisez-les ou annulez-les avant de fermer la supérette. »
3. L'administrateur doit traiter ces commandes manuellement avant de relancer la fermeture.

---

## Règles métier

- La fermeture définitive ne supprime pas les données : commandes, lignes, logs restent en base (archivage).
- Les commandes `submitted` et `accepted` sont annulées automatiquement avec le statut `cancelled` et le motif `SHOP_CLOSED`.
- Les commandes `preparing` et `ready` bloquent la fermeture (traitement manuel requis).
- Les commandes `completed`, `rejected`, `cancelled` ne sont pas affectées.
- Le QR code de la supérette devient invalide dès la fermeture (token révoqué).
- La réouverture est possible uniquement via une intervention directe en base (hors interface MVP).

---

## Critères d'acceptation

- [ ] L'admin voit le nombre de commandes actives avant de confirmer.
- [ ] Les commandes `submitted` et `accepted` sont annulées automatiquement.
- [ ] Les commandes `preparing` et `ready` bloquent la fermeture avec un message explicite.
- [ ] Le QR code devient invalide après la fermeture.
- [ ] La supérette n'apparaît plus dans les résultats publics.
- [ ] L'historique des commandes reste accessible pour l'admin.
- [ ] Un log d'audit est créé avec le motif et l'auteur de la fermeture.

---

## Notes techniques

**Champs à ajouter sur `Shop` :**
```php
#[ORM\Column(nullable: true)]
private ?\DateTimeImmutable $archivedAt = null;

#[ORM\Column(type: Types::TEXT, nullable: true)]
private ?string $archiveReason = null;
```

**Endpoint :**
```http
POST /api/admin/stores/{storeId}/archive
```

**Payload :**
```json
{
  "reason": "Fermeture définitive du commerce — propriétaire retraité"
}
```

**Réponse 409 si commandes bloquantes :**
```json
{
  "error": "ORDERS_IN_PROGRESS",
  "message": "2 commandes sont en cours de préparation ou prêtes.",
  "blocking_orders": ["<uuid1>", "<uuid2>"]
}
```

**Logique de fermeture (service `ShopArchiveService`) :**
1. Vérifier l'absence de commandes `preparing`/`ready` → 409 si trouvées.
2. `UPDATE orders SET status = 'cancelled', cancellation_reason = 'SHOP_CLOSED' WHERE shop_id = :id AND status IN ('submitted', 'accepted')`.
3. `UPDATE pickup_slots SET is_active = false WHERE shop_id = :id`.
4. `UPDATE shops SET active = false, qr_code_token = NULL, archived_at = NOW(), archive_reason = :reason WHERE id = :id`.
5. Créer un `AdminAuditLog` (voir US-063).
6. Déclencher les notifications clients annulés (voir US-038).

**Migration :** ajouter `archived_at` et `archive_reason` sur `shops`.
