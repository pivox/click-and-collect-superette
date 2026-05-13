# Backend Patterns — Pièges et solutions connus

Patterns découverts pendant les sprints #20–#47. À appliquer systématiquement.

## 1. API Platform — IRI generation avec un DTO comme ApiResource

Quand la classe annotée `#[ApiResource]` est un **DTO de sortie** (ex. `KadhiaOutput`) et non l'entité Doctrine, tous les `uriVariables` `Link` de ce resource doivent utiliser `fromClass: KadhiaOutput::class` et non `fromClass: Kadhia::class`.

Utiliser l'entité provoque `InvalidArgumentException: Unable to generate an IRI` → HTTP 400.

```php
// Correct
new Get(
    uriTemplate: '/me/kadhias/{kadhiaId}',
    uriVariables: ['kadhiaId' => new Link(fromClass: KadhiaOutput::class, identifiers: ['id'])],
)

// Incorrect — cause HTTP 400
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

## 6. Extension bcmath absente dans l'environnement de test

L'extension PHP `bcmath` n'est pas disponible dans l'environnement de test de ce projet. Un polyfill est défini dans `tests/bootstrap.php`. Ne pas ajouter de dépendance `ext-bcmath` dans `composer.json` sans vérifier la compatibilité CI.
