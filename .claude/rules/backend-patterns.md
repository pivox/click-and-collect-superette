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

S'applique aussi aux PATCH avec `read: false, output: false` : même sans crash à l'exécution (AP ne génère pas d'IRI), un futur upgrade pourrait le déclencher. Toujours pointer vers le DTO.

```php
// Correct — même sans IRI générée en pratique
new Patch(
    uriTemplate: '/admin/resource/{resourceId}/action',
    uriVariables: [
        'resourceId' => new Link(fromClass: ResourceOutput::class, identifiers: ['id']),
    ],
    read: false, output: false,
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

## 3. POST/PATCH sans body → HTTP 415 dans les tests fonctionnels

`requestJson('POST', $url, user: $customer)` ou `requestJson('PATCH', ...)` sans argument body n'envoie pas `Content-Type: application/json` → le framework retourne 415 Unsupported Media Type.

Toujours passer `[]` comme corps pour les POST/PATCH sans payload :

```php
// Correct
$this->requestJson('POST', $url, [], $customer);
$this->requestJson('PATCH', $url, [], $admin);

// Incorrect — 415
$this->requestJson('POST', $url, user: $customer);
$this->requestJson('PATCH', $url, user: $admin);
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

## 7. PHPStan — memory limit obligatoire

PHPStan crashe avec "Allowed memory size exhausted" si lancé sans limite explicite.

```bash
# Correct
vendor/bin/phpstan analyse --memory-limit=512M

# Incorrect — crashe à ~128 Mo (limite PHP par défaut)
vendor/bin/phpstan analyse
```

## 8. Backslash obligatoire sur les fonctions natives (`@Symfony:risky`)

La config `.php-cs-fixer.dist.php` active `@Symfony:risky` qui inclut `native_function_invocation` :
**CS Fixer impose `\sprintf`, `\array_map`, `\count`** (préfixe namespace global). Sans `\`, le `--dry-run` CI échoue.

Deux règles :

- Préfixe `\` obligatoire sur les fonctions natives : `\sprintf`, `\array_map`, `\count`, etc.
- Espace obligatoire avant `(` dans les closures fléchées : `fn(` → `fn (`.

```php
// Correct
\array_map(static fn (OrderStatus $s) => $s->value, $statuses)
\sprintf('/api/stores/by-qr/%s', $token)

// Incorrect — CS Fixer ajoute \  |  fn( manque l'espace
array_map(static fn(OrderStatus $s) => $s->value, $statuses)
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

## 10. CS Fixer — ne supporte pas plusieurs fichiers en argument

`vendor/bin/php-cs-fixer fix --dry-run --diff file1.php file2.php` échoue avec
`For multiple paths config parameter is required`. Toujours lancer sur le projet entier :

```bash
# Correct — scan complet
vendor/bin/php-cs-fixer fix --dry-run --diff

# Incorrect — échoue avec plusieurs fichiers
vendor/bin/php-cs-fixer fix --dry-run --diff src/A.php src/B.php
```

## 11. CS Fixer — `/* @var */` simple étoile dans les corps de méthodes

CS Fixer transforme `/** @var list<Foo> */` en `/* @var list<Foo> */` (simple étoile)
quand le commentaire est à l'intérieur d'un corps de méthode. Les docblocks de classe/méthode
conservent `/**`.

```php
// Correct — à l'intérieur d'une méthode
/* @var list<ProductReference> */
return $qb->getQuery()->getResult();

// Incorrect — CS Fixer corrige automatiquement
/** @var list<ProductReference> */
return $qb->getQuery()->getResult();
```

## 12. Validation des filtres collection dans le provider, pas le repository

Valider les paramètres de filtre (UUID malformé, valeur enum invalide) dans le **provider**
et lancer `BadRequestHttpException` (→ HTTP 400). Ne pas ignorer silencieusement.

```php
// Correct — validation dans le provider
if (null !== $brandId && !Uuid::isValid($brandId)) {
    throw new BadRequestHttpException('ADMIN_RESOURCE_INVALID_BRAND_FILTER');
}
if (null !== $status && null === MyStatus::tryFrom($status)) {
    throw new BadRequestHttpException('ADMIN_RESOURCE_INVALID_STATUS_FILTER');
}

// Incorrect — filtre ignoré silencieusement, tous les résultats retournés
if (null !== $brandId && Uuid::isValid($brandId)) {
    $qb->andWhere('b.id = :brandId')->setParameter('brandId', $brandId, 'uuid');
}
```

## 13. PHPStan `nullCoalesce.expr` — ne pas ajouter `??` sur un type non-nullable

PHPStan rejette `$entity->getStr() ?? 'default'` si `getStr()` retourne `string`.
Ne pas ajouter de guards défensifs quand le type PHP garantit déjà la non-nullabilité.

```php
// Incorrect — PHPStan: "Expression on left side of ?? is not nullable"
country: $productReference->getCountry() ?? 'TN',

// Correct — getCountry() retourne string, pas besoin de ??
country: $productReference->getCountry(),
```

## 14. `Enum::from()` après `Assert\Choice` — pas besoin de `tryFrom()` + null guard

Quand un champ DTO est déjà validé par `#[Assert\Choice]`, le processor peut appeler
`Enum::from($value)` directement. Le `tryFrom()` + vérification null est redondant
et masque l'intention.

```php
// Correct — Assert\Choice garantit que la valeur est valide
$productReference->setUnit(ProductUnit::from($data->unit));
$productReference->setStatus(ProductReferenceStatus::from($data->status));

// Incorrect — tryFrom() + guard inutile après Assert\Choice
$unit = ProductUnit::tryFrom($data->unit ?? '');
if (null === $unit) { throw new UnprocessableEntityHttpException(...); }
$productReference->setUnit($unit);
```

## 15. `input: false` pour les PATCH d'action sans corps

Les endpoints d'action bodyless (`/archive`, `/suspend`, `/activate`) utilisent
`input: false` dans la définition de l'opération API Platform.

```php
new Patch(
    uriTemplate: '/admin/product-references/{productReferenceId}/archive',
    input: false,   // pas de désérialisation — le processor reçoit null comme $data
    provider: AdminProductReferenceItemProvider::class,
    processor: AdminArchiveProductReferenceProcessor::class,
)
```

Le processor ignore `$data` et opère directement sur l'entité chargée par le provider.

## 16. LIKE sur champ nullable en DQL — guard `IS NOT NULL` obligatoire

Un LIKE sur un champ nullable sans guard peut inclure des NULLs ou retourner 0 résultat
selon le driver. Toujours préfixer par `IS NOT NULL`.

```php
// Correct — guard explicite pour les champs nullable
->orWhere('pr.nameAr IS NOT NULL AND LOWER(pr.nameAr) LIKE LOWER(:q)')

// Incorrect — comportement variable selon le driver
->orWhere('LOWER(pr.nameAr) LIKE LOWER(:q)')
```

## 17. `Assert\Choice` — `choices:` (liste) vs `callback:` (enum complet)

- `callback: [MyEnum::class, 'values']` — accepte toutes les valeurs de l'enum.
- `choices: ['val1', 'val2']` — restreint à un sous-ensemble explicite.

Utiliser `choices:` quand une valeur d'enum doit être réservée à un endpoint dédié.

```php
// Restreint à un sous-ensemble — 'archived' exclu, réservé à PATCH /archive
#[Assert\Choice(choices: ['draft', 'pending_review', 'approved', 'rejected'])]
public ?string $status = null;

// Toutes les valeurs acceptées
#[Assert\Choice(callback: [ProductUnit::class, 'values'])]
public ?string $unit = null;
```

## 18. Propriétés nullable null exclues de la sérialisation JSON par API Platform

Par défaut, API Platform / Symfony Serializer n'inclut **pas** les propriétés dont la valeur est `null` dans la réponse JSON. Une assertion `assertArrayHasKey('foo', $payload)` échouera si `foo` est null.

Tester la présence uniquement quand la valeur est non-null, ou tester `assertArrayNotHasKey` quand null est attendu.

```php
// Correct — champ présent seulement après approbation
self::assertArrayNotHasKey('created_product_reference_id', $payload); // avant approbation
// après approbation : vérifier l'entité directement via entityManager->find(...)

// Incorrect — échoue si la valeur est null
self::assertArrayHasKey('created_product_reference_id', $payload);
```

## 19. `ConflictHttpException` pour les conflits métier → HTTP 409

Quand une action est refusée parce que la ressource est dans un état incompatible
(ex. proposition déjà traitée, commande déjà annulée), lancer `ConflictHttpException`.

```php
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

if (ProductReferenceProposalStatus::Pending !== $proposal->getStatus()) {
    throw new ConflictHttpException('ADMIN_PRODUCT_PROPOSAL_ALREADY_PROCESSED');
}
```

## 20. DTO imbriqué — `#[Assert\Valid]` obligatoire pour valider le sous-objet

Sans `#[Assert\Valid]`, les contraintes du DTO enfant sont ignorées silencieusement.

```php
// Correct — les contraintes de AdminApproveCanonicalData sont vérifiées
#[Assert\Valid]
public ?AdminApproveCanonicalData $canonicalData = null;

// Incorrect — validation du sous-objet ignorée même si les champs sont invalides
public ?AdminApproveCanonicalData $canonicalData = null;
```

Toujours extraire dans un fichier séparé (`AdminApproveCanonicalData.php`) même si elle n'est
utilisée que par un seul DTO parent — PSR-1 §3.

## 21. Supprimer le test quand l'endpoint est retiré

Un test qui cible un endpoint supprimé retourne 404 et fait échouer la suite
sans message d'erreur explicite sur la cause réelle. Toujours supprimer
(ou migrer) le test en même temps que l'endpoint.

## 22. `Assert\Url` — toujours spécifier `requireTld` et `protocols`

Sans options explicites : (1) Symfony 7.1 émet une dépréciation sur `requireTld` ; (2) `ftp://`
est accepté par défaut, ce qui expose un risque côté frontend (`<img src="ftp://...">`).

```php
// Correct — https/http seulement, pas ftp
#[Assert\Url(requireTld: true, protocols: ['https', 'http'])]
#[Assert\Length(max: 2048)]
public ?string $logoUrl = null;

// Incorrect — accepte ftp + déclenche une dépréciation Symfony 7.1
#[Assert\Url]
public ?string $logoUrl = null;
```

## 23. `StreamedResponse::getContent()` retourne `false` dans les tests

`getContent()` est une méthode de `Response`. Sur une `StreamedResponse`, elle retourne `false`.
Capturer le corps avec `ob_start()` + `sendContent()` + `ob_get_clean()`.

```php
// Correct — capture le corps d'une StreamedResponse
ob_start();
$response->sendContent();
$body = (string) ob_get_clean();
$body = ltrim($body, "\xEF\xBB\xBF"); // strip BOM si présent avant parsing

// Incorrect — retourne false sur StreamedResponse
(string) $response->getContent();
```

## 24. `fputcsv` — RFC 4180 : `$escape=''`, pas `'\\'`

`$escape='\\'` produit `\"` — Excel FR/TN parse incorrectement. RFC 4180 utilise le
double-quote doubling (`""`), obtenu avec `$escape=''`. Appliquer aux deux sens (production et test).

```php
// Correct — RFC 4180, Excel FR/TN compatible
fputcsv($stream, $row, ';', '"', '');
str_getcsv($line, ';', '"', '');

// Incorrect — Excel interprète \" comme une séquence d'échappement non standard
fputcsv($stream, $row, ';', '"', '\\');
```

## 25. `DateInterval::$days` toujours absolu — ne détecte pas une plage inversée

`$dateFrom->diff($dateTo)->days` est toujours ≥ 0 quel que soit l'ordre des dates.
La garde "plage trop grande" ne protège pas contre l'inversion — vérification explicite requise.

```php
// Correct — vérification avant la garde de durée maximale
if ($dateFrom > $dateTo) {
    throw new BadRequestHttpException('..._INVALID_DATE_RANGE');
}
$diffDays = (int) $dateFrom->diff($dateTo)->days;
if ($diffDays > self::MAX_RANGE_DAYS) { ... }

// Incorrect — diff()->days retourne la même valeur positive dans les deux sens
```
