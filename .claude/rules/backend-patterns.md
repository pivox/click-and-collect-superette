# Backend Patterns — Pièges et solutions connus

Patterns découverts pendant les sprints #20–#47. À appliquer systématiquement.

## 1. API Platform — IRI generation avec un DTO comme ApiResource

Quand la classe annotée `#[ApiResource]` est un **DTO de sortie** (ex. `KadhiaOutput`), la variable URI qui identifie **ce DTO lui-même** doit utiliser `fromClass: KadhiaOutput::class`. Les autres variables URI de la même opération (qui identifient d'autres ressources) continuent d'utiliser leur propre classe.

Utiliser l'entité pour l'identifiant du DTO provoque `InvalidArgumentException: Unable to generate an IRI` → HTTP 400.

```php
// Correct — kadhiaId identifie le DTO, storeId et merchantProductId identifient d'autres ressources
new Put(
    uriTemplate: '/me/kadhias/{kadhiaId}/lines/{merchantProductId}',
    uriVariables: [
        'kadhiaId'          => new Link(fromClass: KadhiaOutput::class, identifiers: ['id']),
        'merchantProductId' => new Link(fromClass: MerchantProduct::class, identifiers: ['id']),
    ],
)
new Post(
    uriTemplate: '/me/stores/{storeId}/kadhias',
    uriVariables: ['storeId' => new Link(fromClass: Shop::class, identifiers: ['id'])],
)

// Incorrect — kadhiaId doit pointer vers le DTO, pas l'entité
new Get(
    uriTemplate: '/me/kadhias/{kadhiaId}',
    uriVariables: ['kadhiaId' => new Link(fromClass: Kadhia::class, identifiers: ['id'])],
)
```

## 2. DQL + SQLite + UUID BLOB dans les tests

En environnement de test (SQLite), `createQueryBuilder` avec `setParameter('field', $entity)` échoue silencieusement pour les colonnes UUID (stockées en BLOB) : la requête retourne 0 résultat.

Solutions par ordre de préférence :

```php
// Option A — type explicite 'uuid' (recommandé pour DQL)
->setParameter('customer', $customer->getId(), 'uuid')

// Option B — API critères (toujours fiable)
$this->findOneBy(['customer' => $customer])
$this->findBy(['customer' => $customer])
```

Ne jamais passer l'entité directement en paramètre DQL dans ce projet.

## 3. POST sans body → HTTP 415 dans les tests fonctionnels

`requestJson('POST', $url, user: $customer)` sans argument body n'envoie pas `Content-Type: application/json` → le framework retourne 415 Unsupported Media Type.

Toujours passer `[]` comme corps pour les POST sans payload :

```php
// Correct
$this->requestJson('POST', $url, [], $customer);

// Incorrect — 415
$this->requestJson('POST', $url, user: $customer);
```

## 4. Doctrine orphanRemoval + contrainte unique dans un seul flush()

Vider une collection (`removeLine()`) puis insérer de nouveaux items avec la même contrainte unique `(order_id, merchant_product_id)` dans un seul `flush()` peut provoquer un INSERT avant le DELETE → violation de contrainte.

Solution : **mettre à jour en place** les lignes déjà présentes ; n'insérer que les vraiment nouvelles et ne supprimer que les vraiment retirées.

```php
// Indexer les lignes existantes par clé métier
$existingLines = [];
foreach ($order->getLines() as $line) {
    $existingLines[$line->getMerchantProduct()->getId()->toRfc4122()] = $line;
}

foreach ($kadhiaLines as $kadhiaLine) {
    $productId = $kadhiaLine->getMerchantProduct()->getId()->toRfc4122();
    if (isset($existingLines[$productId])) {
        $existingLines[$productId]->setQuantity(...); // UPDATE en place
    } else {
        $order->addLine(new OrderLine(...)); // INSERT uniquement si nouveau
    }
}

// DELETE uniquement les lignes vraiment retirées
foreach ($existingLines as $productId => $line) {
    if (!isset($kadhiaProductIds[$productId])) {
        $order->removeLine($line);
    }
}
```

## 5. Lecture des query params dans les providers custom

`$context['filters']` n'est **pas** fiable dans les providers custom API Platform. Injecter `RequestStack` et lire les paramètres directement depuis la requête.

```php
// Correct
public function __construct(private RequestStack $requestStack) {}

$request = $this->requestStack->getCurrentRequest();
$status = $request?->query->get('status') ?: null;
$page   = max(1, (int) ($request?->query->get('page') ?? 1));

// Incorrect — peut retourner null même si le paramètre est présent
$status = $context['filters']['status'] ?? null;
```

## 7. PHPStan — memory limit obligatoire

PHPStan crashe avec "Allowed memory size exhausted" si lancé sans limite explicite.

```bash
# Correct
vendor/bin/phpstan analyse --memory-limit=512M

# Incorrect — crashe à ~128 Mo (limite PHP par défaut)
vendor/bin/phpstan analyse
```

## 8. CS Fixer — pas de backslash sur les fonctions natives, espace avant `fn`

Deux règles CS Fixer silencieuses :

- Préfixe global interdit : `\array_map`, `\count`, `\sprintf` → écrire sans `\`.
- Espace obligatoire avant `(` dans les closures fléchées : `fn(` → `fn (`.

```php
// Correct
array_map(static fn (OrderStatus $s) => $s->value, $statuses)

// Incorrect — CS Fixer rejette les deux formes
\array_map(static fn(OrderStatus $s) => $s->value, $statuses)
```

## 9. `final readonly class` ne peut pas être mockée par PHPUnit

PHPUnit ne peut pas créer de mock d'une classe `final`. Pattern : extraire une interface, garder la classe `final readonly`, mocker l'interface dans les tests.

```php
// Interface (mockable dans les tests)
interface PickupReminderNotifierInterface
{
    public function notifyCustomerPickupReminder(Order $order): void;
}

// Classe finale qui implémente l'interface
final readonly class NotificationService implements PickupReminderNotifierInterface { ... }

// Test — mocker l'interface, jamais la classe concrète
$notifier = $this->createMock(PickupReminderNotifierInterface::class);
```

## 6. Extension bcmath et environnement de test

`ext-bcmath` est déclarée dans `apps/backend/composer.json` et utilisée en production (`bcadd`, `bcmul`). Ne pas la retirer.

Si des tests échouent avec `Call to undefined function bcadd()`, c'est que l'environnement CI n'a pas l'extension installée malgré la déclaration composer. Solution : ajouter un polyfill dans `tests/bootstrap.php` (pas en production) :

```php
if (!function_exists('bcadd')) {
    function bcadd(string $num1, string $num2, int $scale = 0): string
    {
        return number_format((float) $num1 + (float) $num2, $scale, '.', '');
    }
    function bcmul(string $num1, string $num2, int $scale = 0): string
    {
        return number_format((float) $num1 * (float) $num2, $scale, '.', '');
    }
}
```
