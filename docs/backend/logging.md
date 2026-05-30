# Logging backend — Click & Collect Supérette

## Bundle et configuration

**Bundle :** `symfony/monolog-bundle` (^3.10)

**Fichier de configuration :** `config/packages/monolog.yaml`

Par environnement :
- **dev** — rotating file `var/log/dev.log`, niveau `debug`, 7 fichiers max + handler console.
- **test** — NullHandler (logs supprimés, les tests mockent `LoggerInterface` directement).
- **prod** — rotating file `var/log/prod.log`, niveau `info`, 30 fichiers max. Fichier dédié `var/log/error.log` pour les erreurs.

---

## Règles de sécurité (non négociables)

- **Ne jamais loguer** : mots de passe, tokens JWT, refresh tokens, token reset password, secrets API.
- **Ne jamais loguer** : email en clair, téléphone, adresse, contenu brut d'une Kadhia, note libre complète utilisateur, token QR.
- **Identifiants opaques uniquement** : `order_id`, `store_id`, `merchant_id`, `user_id`, `pickup_session_id`, `kadhia_id`, `slot_id`.
- **Email** : utiliser `email_hash = hash('sha256', strtolower($email))` pour corrélation si nécessaire. Jamais l'email en clair dans Monolog (l'`AdminAuditLogger` gère ses propres métadonnées en base).
- **Texte libre utilisateur** : éviter ou tronquer. Utiliser `has_note = true/false` plutôt que le contenu.

---

## Canaux

| Canal | Service(s) | Rôle |
|---|---|---|
| `order` | `SubmitOrderProcessor`, `OrderStatusLogRecorder`, `MerchantRejectOrderProcessor`, `MerchantStartPreparationProcessor`, `MerchantMarkReadyProcessor`, `CancelOrderProcessor`, processeurs pickup/retrait | Cycle de vie des commandes, transitions statut, QR, retrait |
| `security` | `LastLoginAtSubscriber`, `PasswordResetRequestProcessor`, `PasswordResetConfirmProcessor` | Connexions, authentification, reset password |
| `catalog` | `ImportProductsCommand` | Import Open*Facts, produits ignorés ou en erreur |
| `admin` | `AdminArchiveStoreProcessor`, `AdminActivateStoreProcessor`, `AdminDeactivateStoreProcessor`, `AdminActivateMerchantProcessor`, `AdminSuspendMerchantProcessor`, `AdminCreateMerchantProcessor`, `AdminUpdateMerchantProcessor` | Actions admin critiques |
| `notification` | `NotificationService` | Envoi et persistance des notifications in-app |
| `app` (défaut) | MessageHandlers (4) | Traitements asynchrones Messenger |

---

## Niveaux de log

| Niveau | Quand l'utiliser |
|---|---|
| `debug` | Données détaillées utiles en dev uniquement — paramètres d'entrée, état intermédiaire. Jamais de données sensibles même en debug. |
| `info` | Événement métier important terminé normalement |
| `warning` | Anomalie récupérable — rejet métier, conflit d'état, slot plein, UUID invalide ignoré |
| `error` | Échec non récupérable — exception Messenger, flush échoué, API externe en erreur |

---

## Événements instrumentés — Canal `order`

### Soumission commande (`SubmitOrderProcessor`)

| Clé | Niveau | Contexte |
|---|---|---|
| `order.submit.start` | debug | `kadhia_id`, `slot_id` |
| `order.submit.rejected` | warning | `reason`, `kadhia_id`, `slot_id`, `user_id`, `store_id` |
| `order.submitted` | info | `order_id`, `store_id`, `submission_type` |
| `order.slot_full` | warning | `slot_id`, `kadhia_id` (lock concurrent) |
| `order.submit.failed` | error | `kadhia_id`, `exception_class`, `exception_message` |
| `order.submit.timeout_scheduled` | info | `order_id` |
| `order.submit.timeout_schedule_failed` | error | `order_id`, `exception_class`, `exception_message` |

**Valeurs de `reason` pour `order.submit.rejected`** :
`CUSTOMER_ACCESS_REQUIRED`, `KADHIA_NOT_FOUND`, `KADHIA_NOT_DRAFT`, `STORE_NOT_FOUND`,
`PICKUP_SLOT_NOT_FOUND`, `PICKUP_SLOT_FULL`, `PICKUP_SLOT_EXPIRED`, `PICKUP_SLOT_CLOSED`,
`KADHIA_EMPTY`, `PRODUCT_UNAVAILABLE`, `PARTIAL_ACCEPTANCE_EXPIRED`

### Transitions statut commande (`OrderStatusLogRecorder`)

| Clé | Niveau | Contexte |
|---|---|---|
| `order.status_change.start` | debug | `order_id`, `from_status`, `to_status` |
| `order.status_changed` | info | `order_id`, `store_id`, `from_status`, `to_status` |

### Actions marchand commandes

| Clé | Niveau | Contexte |
|---|---|---|
| `merchant.order_reject.start` | debug | `order_id`, `store_id` |
| `merchant.order_reject.rejected` | warning | `order_id`, `store_id`, `reason` |
| `merchant.order_rejected` | info | `order_id`, `store_id` |
| `merchant.order_reject.failed` | error | `order_id`, `store_id`, `exception_class`, `exception_message` |
| `merchant.order_preparation.start` | debug | `order_id`, `store_id` |
| `merchant.order_preparation.rejected` | warning | `order_id`, `store_id`, `reason` |
| `merchant.order_preparation_started` | info | `order_id`, `store_id` |
| `merchant.order_preparation.failed` | error | `order_id`, `store_id`, `exception_class`, `exception_message` |
| `merchant.order_ready.start` | debug | `order_id`, `store_id` |
| `merchant.order_ready.rejected` | warning | `order_id`, `store_id`, `reason` |
| `merchant.order_ready` | info | `order_id`, `store_id` |
| `merchant.order_ready.failed` | error | `order_id`, `store_id`, `exception_class`, `exception_message` |
| `merchant.order_ready.pickup_reminder_scheduled` | info | `order_id` |
| `merchant.order_ready.pickup_reminder_schedule_failed` | error | `order_id`, `exception_class`, `exception_message` |

### Annulation commande client (`CancelOrderProcessor`)

| Clé | Niveau | Contexte |
|---|---|---|
| `customer.order_cancel.start` | debug | `order_id`, `user_id`, `store_id` |
| `customer.order_cancel.rejected` | warning | `order_id`, `user_id`, `store_id`, `reason` |
| `customer.order_cancelled` | info | `order_id`, `user_id`, `store_id` |
| `customer.order_cancel.failed` | error | `order_id`, `user_id`, `store_id`, `exception_class`, `exception_message` |

### Parcours QR / retrait

| Clé | Niveau | Contexte |
|---|---|---|
| `pickup.scan.start` | debug | `pickup_session_id`, `order_id`, `store_id` |
| `pickup.scan.rejected` | warning | `reason`, `pickup_session_id`?, `order_id`?, `store_id`? |
| `pickup.scanned` | info | `pickup_session_id`, `order_id`, `store_id`, `idempotent`? |
| `pickup.scan.failed` | error | `pickup_session_id`, `order_id`, `store_id`, `exception_class`, `exception_message` |
| `pickup.confirm_merchant.start` | debug | `pickup_session_id`, `order_id`, `store_id` |
| `pickup.confirm_merchant.rejected` | warning | `reason`, `pickup_session_id`, `order_id`?, `store_id`? |
| `pickup.confirm_merchant.done` | info | `pickup_session_id`, `order_id`, `store_id`, `completed` |
| `pickup.confirm_merchant.failed` | error | `pickup_session_id`, `order_id`, `store_id`, `exception_class`, `exception_message` |
| `pickup.confirm_customer.start` | debug | `pickup_session_id`, `order_id`, `store_id` |
| `pickup.confirm_customer.rejected` | warning | `reason`, `pickup_session_id`, `order_id`?, `store_id`? |
| `pickup.confirm_customer.done` | info | `pickup_session_id`, `order_id`, `store_id`, `completed` |
| `pickup.confirm_customer.failed` | error | `pickup_session_id`, `order_id`, `store_id`, `exception_class`, `exception_message` |
| `pickup.force_complete.start` | debug | `pickup_session_id`, `order_id`, `store_id` |
| `pickup.force_complete.rejected` | warning | `reason`, `pickup_session_id`, `order_id`, `store_id` |
| `pickup.force_completed` | info | `pickup_session_id`, `order_id`, `store_id`, `has_note` |
| `pickup.force_complete.failed` | error | `pickup_session_id`, `order_id`, `store_id`, `exception_class`, `exception_message` |

> `?` = présent seulement si disponible à ce stade. `ownership_mismatch` est loggué sans `order_id` ni `store_id` pour ne pas révéler d'information.

---

## Événements instrumentés — Canal `security`

| Clé | Niveau | Contexte |
|---|---|---|
| `security.login.event` | debug | `user_id` |
| `security.login` | info | `user_id` |
| `security.login.skipped` | warning | `reason`, `user_id` ou `class` |
| `security.login.update_failed` | error | `user_id`, `exception_class`, `exception_message` |
| `security.password_reset.requested` | debug | `email_hash` |
| `security.password_reset.user_not_eligible` | debug | `email_hash` (debug, pas warning — ne révèle pas l'inexistence du compte) |
| `security.password_reset.sent` | info | `email_hash` |
| `security.password_reset.failed` | warning ou error | `email_hash`?, `reason`?, `exception_class`?, `exception_message`? |
| `security.password_reset.confirm.start` | debug | — |
| `security.password_reset.confirmed` | info | — |

---

## Événements instrumentés — Canal `catalog`

| Clé | Niveau | Contexte |
|---|---|---|
| `catalog.import.fetch` | debug | `url` |
| `catalog.import.skipped` | debug | `reason`, `barcode`, `source` |
| `catalog.import.done` | info | `sources`, `fetched`, `inserted`, `updated`, `skipped`, `errors` |
| `catalog.import.fetch_failed` | error | `url`, `exception_class`, `exception_message` |

---

## Événements instrumentés — Canal `admin`

| Clé | Niveau | Contexte |
|---|---|---|
| `admin.merchant_create.start` | debug | `email_hash` |
| `admin.merchant_create.rejected` | warning | `reason`, `email_hash` |
| `merchant.created` | info | `merchant_id` |
| `admin.merchant_create.failed` | error | `email_hash`, `exception_class`, `exception_message` |
| `admin.merchant_update.start` | debug | `merchant_id`, `updated_fields` (noms uniquement) |
| `merchant.updated` | info | `merchant_id`, `updated_fields` |
| `admin.merchant_update.failed` | error | `merchant_id`, `exception_class`, `exception_message` |
| `admin.merchant_activate.start` | debug | `merchant_id` |
| `admin.merchant_activate.already_active` | warning | `merchant_id` |
| `merchant.activated` | info | `merchant_id` |
| `admin.merchant_activate.failed` | error | `merchant_id`, `exception_class`, `exception_message` |
| `admin.merchant_suspend.start` | debug | `merchant_id` |
| `admin.merchant_suspend.already_inactive` | warning | `merchant_id` |
| `merchant.suspended` | info | `merchant_id` |
| `admin.merchant_suspend.failed` | error | `merchant_id`, `exception_class`, `exception_message` |
| `admin.archive_store.start` | debug | `store_id` |
| `admin.archive_store.already_archived` | warning | `store_id` |
| `store.archived` | info | `store_id`, `reason` |
| `admin.store_activate.start` | debug | `store_id` |
| `admin.store_activate.rejected` | warning | `store_id`, `reason` |
| `store.activated` | info | `store_id` |
| `admin.store_activate.failed` | error | `store_id`, `exception_class`, `exception_message` |
| `admin.store_deactivate.start` | debug | `store_id` |
| `store.deactivated` | info | `store_id` |
| `admin.store_deactivate.failed` | error | `store_id`, `exception_class`, `exception_message` |

---

## Événements instrumentés — Canal `notification`

| Clé | Niveau | Contexte |
|---|---|---|
| `notification.attempt` | debug | `type`, `order_id`, `recipient` |
| `notification.persisted` | info | `type`, `order_id`, `recipient` |
| `notification.no_owner` | warning | `order_id`, `store_id` |
| `notification.failed` | error | `type`, `order_id`, `exception_class`, `exception_message` |

---

## Événements instrumentés — Canal `app` (MessageHandlers)

| Clé | Niveau | Contexte |
|---|---|---|
| `messenger.received` | debug | `message`, `order_id` |
| `messenger.handled` | info | `message`, `order_id` |
| `messenger.skipped` | warning | `message`, `order_id`, `reason` |
| `messenger.failure` | error | `message`, `order_id`, `exception_class`, `exception_message` |

---

## Utilisation d'un canal nommé

L'injection du canal se fait via l'attribut `#[Autowire]` dans le constructeur :

```php
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

public function __construct(
    #[Autowire(service: 'monolog.logger.order')]
    private LoggerInterface $logger,
) {}
```
