# US-063 — Audit trail des actions administrateur

**Epic** : EPIC-009 — Administration plateforme
**Sprint** : Sprint 5 — Administration minimale
**Priorité** : Could Have

---

## Récit

En tant qu'**administrateur plateforme**,
je veux **consulter un journal des actions importantes réalisées par les administrateurs**,
afin de **tracer les changements critiques et répondre aux demandes de support ou d'audit**.

---

## Préconditions

- L'administrateur est connecté avec `ROLE_ADMIN`.

---

## Scénario nominal

1. L'administrateur accède à la section « Journal d'audit ».
2. Il voit une liste paginée des actions récentes, avec pour chaque entrée :
   - Date et heure de l'action.
   - Administrateur ayant effectué l'action.
   - Type d'action (ex. `shop.archived`, `merchant.suspended`).
   - Identifiant de la ressource concernée.
   - Résumé lisible (ex. « Supérette "Ezzahra Market" archivée par admin@superette.tn »).
3. Il peut filtrer par type d'action ou par administrateur.

---

## Actions auditées

| Action | Description |
|---|---|
| `shop.created` | Création d'une nouvelle supérette |
| `shop.updated` | Modification des informations d'une supérette |
| `shop.archived` | Fermeture définitive d'une supérette |
| `shop.qr_regenerated` | Régénération du QR code d'une supérette |
| `merchant.created` | Création d'un compte marchand |
| `merchant.suspended` | Suspension d'un compte marchand |
| `merchant.activated` | Réactivation d'un compte marchand |
| `product_ref.created` | Création d'un produit dans le référentiel |
| `product_ref.updated` | Modification d'un produit référentiel |
| `product_ref.archived` | Archivage d'un produit référentiel |
| `proposal.approved` | Validation d'une proposition de produit marchand |
| `proposal.rejected` | Refus d'une proposition de produit marchand |
| `account.deleted` | Suppression / anonymisation d'un compte client |

---

## Règles métier

- Chaque action administrative critique crée automatiquement une entrée dans le journal.
- Le journal est en lecture seule — aucune entrée ne peut être modifiée ou supprimée depuis l'interface.
- La rétention des logs d'audit est de 2 ans (voir US-062).
- Seuls les administrateurs peuvent consulter le journal.
- Les actions de marchands et clients ne sont pas dans ce journal (elles sont dans `OrderStatusLog`).

---

## Critères d'acceptation

- [ ] Chaque action administrative critique génère une entrée dans le journal.
- [ ] Le journal est accessible uniquement aux administrateurs.
- [ ] Les entrées contiennent : date, admin, type d'action, ressource concernée, résumé.
- [ ] La liste est paginée (20 entrées/page).
- [ ] Le journal est filtrable par type d'action et par administrateur.
- [ ] Aucune entrée ne peut être modifiée ou supprimée depuis l'interface.

---

## Notes techniques

**Nouvelle entité `AdminAuditLog` :**
```php
#[ORM\Entity]
#[ORM\Table(name: 'admin_audit_logs')]
#[ORM\Index(fields: ['action', 'createdAt'])]
class AdminAuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $admin;

    #[ORM\Column(length: 64)]
    private string $action; // ex. 'shop.archived'

    #[ORM\Column(length: 64)]
    private string $resourceType; // ex. 'shop'

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $resourceId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null; // résumé lisible

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null; // données contextuelles libres

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
}
```

**Service `AdminAuditLogger` :**
```php
class AdminAuditLogger
{
    public function log(
        User $admin,
        string $action,
        string $resourceType,
        ?Uuid $resourceId = null,
        ?string $summary = null,
        array $metadata = []
    ): void {
        $log = new AdminAuditLog($admin, $action, $resourceType, $resourceId, $summary, $metadata);
        $this->entityManager->persist($log);
        // flush délégué à la transaction parente
    }
}
```

**Endpoint de consultation :**
```http
GET /api/admin/audit-logs?action=shop.archived&admin=<uuid>&page=1
```

**Réponse :**
```json
{
  "items": [
    {
      "id": "<uuid>",
      "created_at": "2026-05-14T10:30:00+01:00",
      "admin": { "id": "<uuid>", "email": "admin@superette.tn" },
      "action": "shop.archived",
      "resource_type": "shop",
      "resource_id": "<uuid>",
      "summary": "Supérette \"Ezzahra Market\" archivée — raison : fermeture définitive"
    }
  ],
  "total": 42,
  "page": 1
}
```

**Intégration dans les processors admin :**
Appeler `AdminAuditLogger::log()` à la fin de chaque action critique, dans la même transaction Doctrine.

**Migration :** créer la table `admin_audit_logs` avec index sur `(action, created_at)` et `(admin_id, created_at)`.
