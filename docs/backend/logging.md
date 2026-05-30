# Logging backend — Click & Collect Supérette

## Bundle et configuration

**Bundle :** `symfony/monolog-bundle` (^3.10)

**Fichier de configuration :** `config/packages/monolog.yaml`

Par environnement :
- **dev** — rotating file `var/log/dev.log`, niveau `debug`, 7 fichiers max + handler console.
- **test** — NullHandler (logs supprimés, les tests mockent `LoggerInterface` directement).
- **prod** — rotating file `var/log/prod.log`, niveau `info`, 30 fichiers max. Fichier dédié `var/log/error.log` pour les erreurs.

---

## Canaux

| Canal | Service(s) | Rôle |
|---|---|---|
| `order` | `SubmitOrderProcessor`, `OrderStatusLogRecorder` | Cycle de vie des commandes, transitions de statut |
| `security` | `LastLoginAtSubscriber` | Connexions, événements d'authentification |
| `catalog` | `ImportProductsCommand` | Import Open*Facts, produits ignorés ou en erreur |
| `admin` | `AdminArchiveStoreProcessor`, `AdminActivateMerchantProcessor`, `AdminSuspendMerchantProcessor` | Actions admin critiques |
| `notification` | `NotificationService` | Envoi et persistance des notifications in-app |
| `app` (défaut) | MessageHandlers (4) | Traitements asynchrones Messenger |

---

## Niveaux de log

| Niveau | Quand l'utiliser |
|---|---|
| `debug` | Données détaillées utiles en dev uniquement — paramètres d'entrée, état intermédiaire |
| `info` | Événement métier important terminé normalement |
| `warning` | Anomalie récupérable — slot plein, UUID invalide ignoré, statut inattendu |
| `error` | Échec non récupérable — exception Messenger, flush échoué, API externe en erreur |

---

## Événements instrumentés

### Canal `order`

| Clé | Niveau | Contexte |
|---|---|---|
| `order.submit.start` | debug | `kadhia_id`, `slot_id` |
| `order.submitted` | info | `order_id`, `store_id`, `submission_type` |
| `order.slot_full` | warning | `slot_id`, `kadhia_id` |
| `order.submit.failed` | error | `kadhia_id`, `exception_class`, `exception_message` |
| `order.status_change.start` | debug | `order_id`, `from_status`, `to_status` |
| `order.status_changed` | info | `order_id`, `store_id`, `from_status`, `to_status` |

### Canal `security`

| Clé | Niveau | Contexte |
|---|---|---|
| `security.login.event` | debug | `user_id` |
| `security.login` | info | `user_id` |
| `security.login.skipped` | warning | `reason`, `user_id` ou `class` |
| `security.login.update_failed` | error | `user_id`, `exception_class`, `exception_message` |

### Canal `catalog`

| Clé | Niveau | Contexte |
|---|---|---|
| `catalog.import.fetch` | debug | `url` |
| `catalog.import.skipped` | debug | `reason`, `barcode`, `source` |
| `catalog.import.done` | info | `sources`, `fetched`, `inserted`, `updated`, `skipped`, `errors` |
| `catalog.import.fetch_failed` | error | `url`, `exception_class`, `exception_message` |

### Canal `admin`

| Clé | Niveau | Contexte |
|---|---|---|
| `admin.archive_store.start` | debug | `store_id` |
| `admin.archive_store.already_archived` | warning | `store_id` |
| `store.archived` | info | `store_id`, `reason` |
| `admin.merchant_activate.start` | debug | `merchant_id` |
| `admin.merchant_activate.already_active` | warning | `merchant_id` |
| `merchant.activated` | info | `merchant_id`, `email` |
| `admin.merchant_activate.failed` | error | `merchant_id`, `exception_class`, `exception_message` |
| `admin.merchant_suspend.start` | debug | `merchant_id` |
| `admin.merchant_suspend.already_inactive` | warning | `merchant_id` |
| `merchant.suspended` | info | `merchant_id`, `email` |
| `admin.merchant_suspend.failed` | error | `merchant_id`, `exception_class`, `exception_message` |

### Canal `notification`

| Clé | Niveau | Contexte |
|---|---|---|
| `notification.attempt` | debug | `type`, `order_id`, `recipient` |
| `notification.persisted` | info | `type`, `order_id`, `recipient` |
| `notification.no_owner` | warning | `order_id`, `store_id` |
| `notification.failed` | error | `type`, `order_id`, `exception_class`, `exception_message` |

### Canal `app` (MessageHandlers)

| Clé | Niveau | Contexte |
|---|---|---|
| `messenger.received` | debug | `message`, `order_id` |
| `messenger.handled` | info | `message`, `order_id` |
| `messenger.skipped` | warning | `message`, `order_id`, `reason` |
| `messenger.failure` | error | `message`, `order_id`, `exception_class`, `exception_message` |

---

## Règles de sécurité

- **Ne jamais loguer** : mots de passe, tokens JWT, secrets d'API, contenu brut d'une Kadhia.
- Les `user_id` et `order_id` sont des UUIDs opaques — pas d'information personnelle.
- En prod, le niveau `info` minimum évite de polluer les logs avec les `debug` de dev.

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
