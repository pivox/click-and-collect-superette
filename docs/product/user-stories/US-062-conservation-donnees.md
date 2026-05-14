# US-062 — Politique de conservation et suppression des données (RGPD)

**Epic** : EPIC-009 — Administration plateforme
**Sprint** : Sprint 7 — Production et localisation
**Priorité** : Should Have

---

## Récit

En tant que **client**,
je veux **pouvoir demander la suppression de mon compte et de mes données personnelles**,
afin de **exercer mon droit à l'oubli conformément à la législation sur la protection des données**.

En tant qu'**administrateur plateforme**,
je veux **définir une durée de conservation des données et déclencher la purge automatique**,
afin de **ne conserver les données que le temps nécessaire et respecter les obligations légales**.

---

## Préconditions

- Le client est connecté sur son compte.
- L'administrateur est connecté avec `ROLE_ADMIN`.

---

## Scénario nominal — Suppression de compte (client)

1. Le client accède aux paramètres de son compte.
2. Il clique sur « Supprimer mon compte ».
3. Un écran de confirmation lui indique ce qui sera supprimé et ce qui sera conservé (historique anonymisé des commandes).
4. Il confirme.
5. Le système :
   a. Anonymise les données personnelles du compte (`name → "Client supprimé"`, `email → hash@deleted.local`, `phone → null`).
   b. Conserve les commandes complétées en version anonymisée (pour la comptabilité du marchand).
   c. Supprime les données Kadhia non soumises.
   d. Désactive le compte (`active = false`, `deletedAt = now()`).
6. Le client reçoit un email de confirmation de suppression.
7. Les tokens JWT existants sont révoqués (token denylist si implémenté, sinon expiration naturelle).

---

## Scénario nominal — Purge automatique (admin)

1. Un job Symfony Scheduler s'exécute quotidiennement.
2. Il purge les données selon les règles de conservation définies :
   - Comptes inactifs depuis plus de 3 ans → anonymisation.
   - Logs d'erreur de plus de 90 jours → suppression.
   - `PasswordResetToken` expirés → suppression.
   - `ExceptionalClosure` de plus de 1 an → suppression.

---

## Règles de conservation

| Donnée | Durée de conservation | Action à l'expiration |
|---|---|---|
| Compte client actif | Durée de vie du compte | — |
| Compte client inactif (pas de connexion) | 3 ans | Anonymisation |
| Commandes complétées | 5 ans (obligation comptable) | Anonymisation client |
| Commandes annulées / refusées | 1 an | Suppression |
| `PasswordResetToken` expirés | 24h après expiration | Suppression |
| `OrderStatusLog` | 2 ans | Suppression |
| Logs techniques / erreurs | 90 jours | Suppression |
| `ExceptionalClosure` passées | 1 an | Suppression |

---

## Critères d'acceptation

- [ ] Le client peut supprimer son compte depuis ses paramètres.
- [ ] La suppression anonymise les données personnelles sans supprimer l'historique des commandes.
- [ ] Le client reçoit un email de confirmation de suppression.
- [ ] Un job automatique purge les données selon les règles de conservation.
- [ ] Les `PasswordResetToken` expirés sont purgés automatiquement.
- [ ] Un compte inactif depuis 3 ans est anonymisé automatiquement.

---

## Notes techniques

**Champs à ajouter sur `User` :**
```php
#[ORM\Column(nullable: true)]
private ?\DateTimeImmutable $deletedAt = null;

#[ORM\Column(nullable: true)]
private ?\DateTimeImmutable $lastLoginAt = null;
```

**Endpoint suppression de compte :**
```http
DELETE /api/me/account
```

**Corps de la réponse :** `204 No Content`

**Service `AccountAnonymizationService` :**
```php
public function anonymize(User $user): void
{
    $user->setName('Client supprimé');
    $user->setEmail('deleted-' . substr(md5($user->getId()), 0, 8) . '@deleted.local');
    $user->setPhone(null);
    $user->setDeletedAt(new \DateTimeImmutable());
    $user->setActive(false);
    // Les commandes complétées sont conservées avec customerName anonymisé dans OrderLine
}
```

**Job de purge (Symfony Scheduler) :**
```php
#[AsSchedule]
class DataRetentionSchedule
{
    #[AsCronTask('0 3 * * *')] // 3h du matin chaque nuit
    public function purgeExpiredData(): void { /* ... */ }
}
```

**Anonymisation commandes :** les snapshots `customerName` dans `OrderLine` ou `Order` sont remplacés par `"Client supprimé"`. L'`orderId` et les montants TND sont conservés pour la comptabilité.

**Conformité :** cette US couvre la conformité minimale. Un audit RGPD complet (registre des traitements, DPO) est hors scope MVP.
