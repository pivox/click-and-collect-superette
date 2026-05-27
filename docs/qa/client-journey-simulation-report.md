# Simulation parcours client — Click & Collect

> Rapport généré le 2026-05-26 par simulation Playwright automatisée (parcours bout-en-bout).

---

## 1. Environnement testé

| Paramètre | Valeur |
|---|---|
| Branche Git | `main` |
| Commit | `d2a16b2` (Merge PR #144 — feat/client-search-superettes) |
| Frontend | Next.js 14 — `http://localhost:3000` |
| Backend | Symfony 7 / API Platform — `http://localhost:8000` |
| Base de données | PostgreSQL 16 (Docker `cc_postgres` — base `clickcollect`) |
| Données en base | 1 supérette ("Supérette Test"), 2 produits (coca cola, fanta) |
| Compte client utilisé | `qa-journey-test@test.local` / `Test1234!` (créé manuellement via `POST /api/auth/register/customer`) |
| Mode mocks frontend | `NEXT_PUBLIC_USE_MOCKS=0` (confirmé par les appels API réels observés) |
| Navigateur | Chromium 148 (Playwright headless) — viewport 390×844 (iPhone 14 Pro) |
| Outil d'automatisation | Playwright 1.60.0 via Node.js (`/tmp/client-journey-test.mjs`) |
| Commandes exécutées | `cd apps/backend && symfony server:start` / `cd apps/frontend && npm run dev` |

---

## 2. Résumé exécutif

| Indicateur | Valeur |
|---|---|
| Parcours réussi | **NON** |
| Dernière étape atteinte | Étape 2 — Connexion (bloquée par endpoint incorrect) |
| Point de blocage principal | `POST /api/auth/register` → 404 (devrait être `/api/auth/register/customer`) |
| Issues détectées | **8** |
| Issues bloquantes MVP | **5** |
| Issues majeures | **2** |
| Issues mineures | **1** |
| Erreurs console navigateur | **17** |
| **Verdict MVP** | ❌ **Parcours client non fonctionnel** — le parcours complet ne peut pas être exécuté |

**Diagnostic de fond** : le frontend est lancé avec `NEXT_PUBLIC_USE_MOCKS=0` mais plusieurs URL d'endpoints et formats de réponse ne correspondent pas au contrat réel du backend. Les mocks masquaient ces désalignements jusqu'ici.

---

## 3. Timeline du test

| Étape | Statut | Observations | Erreurs API |
|---|---|---|---|
| 1. Ouverture de l'application | ✅ OK | Page chargée "Kadhia · Click & Collect". Design correct, navigation bas de page fonctionnelle. | — |
| 2. Connexion client | ❌ FAIL | `POST /api/auth/register` → 404. Le compte n'est pas créé, le login échoue avec 401. Message d'erreur brut visible dans le formulaire. | 401 |
| 3. Recherche supérette | ⚠️ PARTIEL | Combobox présent et fonctionnel (`/api/stores/search`). Liste statique vide car `GET /api/stores` → 404. | 404 |
| 4. Fiche supérette | ❌ FAIL | `/stores/shop-el-amel` → "Supérette introuvable" (ID mock non UUID). | 404 |
| 5. Catalogue | ❌ FAIL | `GET /api/stores/.../products` → 404. Catalogue vide, "3 errors" dans le dev overlay. | 404 × 5, 405 × 2 |
| 6. Kadhia / Panier | ⚠️ PARTIEL | Page kadhia accessible, état vide affiché correctement. Aucun produit ajouté (catalogue vide). | 405 × 4 |
| 7. Choix créneau | ❌ FAIL | `/kadhia/slot` redirige vers `/login` (guard auth attendu, mais login est bloqué par Issue 1). | — |
| 8. Validation commande | ❌ BLOQUÉ | Inaccessible (step 7 bloqué). | — |
| 9. Suivi commande | ❌ FAIL | `/orders/{id}` → HTTP 500, erreur "Une erreur est survenue. Le chargement a échoué." React crash. | 500 |

---

## 4. Issues détectées

---

## Issue 1 — Endpoint d'inscription client incorrect (404)

### Type
Bug · Backend · Contrat API

### Gravité
**Bloquant**

### Étape du parcours
Étape 2 — Connexion client (inscription)

### Reproduction
1. Aller sur `/register`
2. Remplir le formulaire et valider
3. Observer la requête réseau

### Comportement observé
`POST /api/auth/register` → HTTP 404 — "No route found"

```
Register returned 404 — will try to login anyway
POST /api/auth/login → HTTP 401 (compte inexistant)
```

Le compte n'est jamais créé. La tentative de login échoue immédiatement.

### Comportement attendu
L'inscription doit créer un compte client et retourner un JWT.

### Preuves

| Élément | Valeur |
|---|---|
| URL appelée | `POST http://localhost:8000/api/auth/register` |
| Statut HTTP | 404 |
| Endpoint backend réel | `POST /api/auth/register/customer` |
| Fichier frontend | `apps/frontend/src/lib/services/auth.service.ts:66` |

```typescript
// Ligne 66 — auth.service.ts
await apiClient.post('/api/auth/register', { email, password, name });
//                    ^^^^^^^^^^^^^^^^^^^^ INCORRECT
//                    devrait être: '/api/auth/register/customer'
```

### Analyse technique
Le backend Symfony expose la route via API Platform sous `/api/auth/register/customer` (confirmé via `php bin/console debug:router`). Le frontend appelle un chemin différent sans le suffixe `/customer`.

### Cause probable
Désalignement entre la définition de la route backend (ApiResource avec `uriTemplate`) et l'URL hardcodée côté frontend, probablement lors de la mise en place initiale du sprint Auth.

### Correction proposée
```typescript
// apps/frontend/src/lib/services/auth.service.ts
export async function clientRegister(
  email: string,
  password: string,
  name: string,
): Promise<void> {
  await apiClient.post('/api/auth/register/customer', { email, password, name });
  //                    ^^^^^^^^^^^^^^^^^^^^^^^^^^^ correction
}
```

---

## Issue 2 — Message d'erreur brut exposé lors de l'échec de connexion

### Type
UX · Frontend

### Gravité
**Majeur**

### Étape du parcours
Étape 2 — Connexion client

### Reproduction
1. Aller sur `/login`
2. Saisir des identifiants invalides (ou un compte inexistant)
3. Cliquer "Se connecter"
4. Observer le message d'erreur

### Comportement observé
Message affiché : **"Request failed with status code 401"**

![Screenshot login 401](../../.playwright-mcp/console-2026-05-23T14-27-48-653Z.log)

C'est un message interne Axios, illisible pour un client final.

### Comportement attendu
Afficher un message métier clair : "Email ou mot de passe incorrect."

### Preuves

| Élément | Valeur |
|---|---|
| Screenshot | `03-02-after-login.png` — message rouge visible dans le formulaire |
| Fichier | `apps/frontend/src/app/(client)/login/page.tsx:33` |

```typescript
// Ligne 33 — la gestion d'erreur propagée directement
setError(err instanceof Error ? err.message : 'Identifiants incorrects');
// err.message = "Request failed with status code 401" (message Axios brut)
```

### Analyse technique
L'intercepteur Axios (`api.ts:34-43`) laisse passer l'erreur 401 sans transformation quand on est sur la page `/login`. La page login la reçoit et l'affiche telle quelle via `err.message`.

### Cause probable
La gestion d'erreur ne distingue pas les erreurs Axios métier (401 = mauvais identifiants) des erreurs techniques.

### Correction proposée
```typescript
// apps/frontend/src/app/(client)/login/page.tsx
} catch (err) {
  const status = (err as { response?: { status?: number } }).response?.status;
  if (status === 401) {
    setError('Email ou mot de passe incorrect.');
  } else if (status === 429) {
    setError('Trop de tentatives. Réessaie dans quelques minutes.');
  } else {
    setError('Une erreur est survenue. Réessaie plus tard.');
  }
}
```

---

## Issue 3 — Liste des supérettes vide — `GET /api/stores` n'existe pas

### Type
Bug · API · Contrat

### Gravité
**Bloquant**

### Étape du parcours
Étape 3 — Recherche supérette

### Reproduction
1. Aller sur `/stores`
2. Observer la liste de supérettes sous le combobox de recherche

### Comportement observé
La liste statique est vide. Le composant `StoreCard` n'affiche rien.

```
GET http://localhost:8000/api/stores → 404
// listShops() attrape l'erreur silencieusement → shops = []
```

![Screenshot stores page](04-03-stores-page.png) — aucune supérette listée

### Comportement attendu
La liste doit afficher les supérettes proches ou récentes.

### Preuves

| Élément | Valeur |
|---|---|
| URL appelée | `GET /api/stores` |
| Statut HTTP | 404 |
| Endpoints backend disponibles | `GET /api/stores/search`, `GET /api/stores/{uuid}` |
| Fichier | `apps/frontend/src/lib/services/stores.service.ts:10` |

```typescript
// stores.service.ts:10 — appel incorrect
const { data } = await apiClient.get<Shop[]>("/api/stores");
// Le backend n'expose pas de collection GET /api/stores
// Seul GET /api/stores/search existe
```

### Analyse technique
Le backend API Platform n'a pas de `GetCollection` sur les stores publics. La route `/api/stores` n'est pas enregistrée. `listShops()` échoue silencieusement (try/catch absorbe l'erreur) et retourne `[]`.

### Cause probable
La fonctionnalité de listing des supérettes n'a pas été implémentée côté backend. La liste statique du frontend a été écrite en anticipant un endpoint qui n'existe pas encore.

### Correction proposée
**Option A (recommandée)** — Remplacer `listShops()` par un appel à `GET /api/stores/search` sans filtre pour afficher toutes les supérettes actives :
```typescript
// apps/frontend/src/lib/services/stores.service.ts
export async function listShops(): Promise<Shop[]> {
  if (USE_MOCKS) return mockDelay(MOCK_SHOPS);
  const { data } = await apiClient.get<StoreSearchResult>("/api/stores/search");
  return data.items.map(item => ({
    id: item.store_id,
    name: item.name,
    slug: item.slug,
    city: item.city,
    isActive: item.is_active,
    address: null, phone: null,
  }));
}
```

**Option B** — Ajouter un endpoint `GET /api/stores` au backend (GetCollection publique sur les shops actifs).

---

## Issue 4 — Catalogue vide — endpoint `/products` inexistant

### Type
Bug · API · Contrat

### Gravité
**Bloquant**

### Étape du parcours
Étape 5 — Catalogue et recherche produits

### Reproduction
1. Aller sur `/stores/{shopId}/catalog`
2. Observer la grille de produits

### Comportement observé
Grille vide. Badge "3 errors" dans le dev overlay Next.js.

```
GET http://localhost:8000/api/stores/shop-el-amel/products → 404
```

![Screenshot catalog](08-05-catalog.png) — produits absents, "3 errors" visibles

### Comportement attendu
La grille doit afficher les produits du catalogue de la supérette.

### Preuves

| Élément | Valeur |
|---|---|
| URL appelée | `GET /api/stores/${shopId}/products` |
| Statut HTTP | 404 |
| Endpoint backend réel | `GET /api/stores/{storeId}/catalog` |
| Fichier | `apps/frontend/src/lib/services/catalog.service.ts:29` |

```typescript
// catalog.service.ts:29 — URL incorrecte
const { data } = await apiClient.get<ProductOffer[]>(
  `/api/stores/${q.shopId}/products`,  // INCORRECT
  //                       ^^^^^^^^
  // devrait être: `/api/stores/${q.shopId}/catalog`
);
```

**Preuve backend** — L'endpoint `/catalog` fonctionne et retourne des données réelles :
```bash
curl http://localhost:8000/api/stores/f0289d35.../catalog
# → {"items":[{"id":"d5d897f9...","name_fr":"coca cola",...}]}  ✅
```

### Analyse technique
Le backend expose `/api/stores/{storeId}/catalog` (confirmé dans `debug:router`). Le frontend appelle `/products` qui n'existe pas.

### Cause probable
Renommage de l'endpoint backend sans répercussion sur le frontend.

### Correction proposée
```typescript
// apps/frontend/src/lib/services/catalog.service.ts
const { data } = await apiClient.get<{ items: CatalogProductApiItem[] }>(
  `/api/stores/${q.shopId}/catalog`,
  //                       ^^^^^^^ correction
);
```

---

## Issue 5 — Catalogue : format de réponse API incompatible (snake_case vs camelCase)

### Type
Bug · API · Contrat

### Gravité
**Bloquant**

### Étape du parcours
Étape 5 — Catalogue (corollaire de l'Issue 4)

### Reproduction
Même URL que l'Issue 4 (`/catalog` une fois corrigée). Observer le mapping des données.

### Comportement observé
Même si l'URL était correcte, les données ne se mapperaient pas correctement. Le backend retourne :

```json
{
  "items": [
    {
      "name_fr": "coca cola",
      "price_tnd": "1.500",
      "is_available": true,
      "product_reference_id": "..."
    }
  ]
}
```

Le frontend attend `ProductOffer[]` directement (pas de wrapper `items`) avec des champs camelCase :
```typescript
// types/index.ts:56-70
interface ProductOffer {
  nameFr: string;     // ≠ name_fr
  priceTnd: string;   // ≠ price_tnd
  isAvailable: boolean; // ≠ is_available
  // ...
}
```

### Comportement attendu
Les produits doivent s'afficher avec les bons labels et prix.

### Preuves

| Désalignement | Frontend attend | Backend retourne |
|---|---|---|
| Format réponse | `ProductOffer[]` | `{ items: ProductOffer[] }` |
| Nom | `nameFr` | `name_fr` |
| Prix | `priceTnd` | `price_tnd` |
| Disponibilité | `isAvailable` | `is_available` |
| Référence | `productReferenceId` | `product_reference_id` |
| Champs absents | `nameAr`, `emoji`, `photoUrl` | Non fournis |

### Correction proposée
Adapter le service pour mapper les champs :

```typescript
// catalog.service.ts
interface CatalogApiItem {
  id: string;
  name_fr: string;
  brand: string;
  category: string;
  category_slug: string;
  volume: string | null;
  unit: string | null;
  price_tnd: string;
  is_available: boolean;
  product_reference_id: string;
}

export async function listCatalog(q: CatalogQuery): Promise<ProductOffer[]> {
  if (USE_MOCKS) { /* ... */ }
  const { data } = await apiClient.get<{ items: CatalogApiItem[] }>(
    `/api/stores/${q.shopId}/catalog`,
  );
  return (data.items ?? []).map(item => ({
    id: item.id,
    productReferenceId: item.product_reference_id,
    nameFr: item.name_fr,
    nameAr: null,
    brand: item.brand,
    volume: item.volume ? parseFloat(item.volume) : null,
    unit: item.unit,
    priceTnd: item.price_tnd,
    isAvailable: item.is_available,
    photoUrl: null,
    category: (item.category_slug ?? 'other') as ProductOffer['category'],
  }));
}
```

---

## Issue 6 — Page commande crash HTTP 500 — "Une erreur est survenue"

### Type
Bug · Frontend · Server Component

### Gravité
**Majeur**

### Étape du parcours
Étape 9 — Suivi commande

### Reproduction
1. Accéder à `/orders/{orderId}` sans être connecté
2. Observer l'écran

### Comportement observé
Écran noir avec message "**Une erreur est survenue. Le chargement a échoué. Réessayez.**"

HTTP 500 côté serveur. React error boundary activé.

```
Console error: Failed to load resource: 500 (Internal Server Error)
Console error: The above error occurred in the <NotFoundErrorBoundary> component
```

![Screenshot order detail](15-09-order-detail.png)

### Comportement attendu
Rediriger vers `/login` ou afficher une page 404/erreur propre.

### Preuves

| Élément | Valeur |
|---|---|
| URL | `http://localhost:3000/orders/order-demo-4821` |
| Statut | HTTP 500 |
| Fichier | `apps/frontend/src/app/(client)/orders/[orderId]/page.tsx:18` |

```typescript
// orders/[orderId]/page.tsx:18 — non protégé contre erreurs API
export default async function OrderTrackingPage({ params }) {
  const order = await getOrder(params.orderId); // ← peut lancer une exception non catchée
  if (!order) notFound();                        // ← ne couvre pas les exceptions
  // ...
}
```

```typescript
// orders.service.ts:16 — en mode réel, lance si 401/500
const { data } = await apiClient.get<Order>(`/api/me/orders/${orderId}`);
// Axios lance une exception sur 401, le serveur Next.js ne la catch pas
```

### Analyse technique
`OrderTrackingPage` est un server component async. Si `getOrder()` lance une exception (401 non authentifié, 500 serveur), Next.js ne la catch pas dans `notFound()` et produit un 500 que l'error boundary attrape avec le message générique.

### Cause probable
Le serveur Next.js effectue l'appel `getOrder()` sans authentification (pas de token JWT côté serveur) et Axios lance sur le 401, qui n'est pas intercepté.

### Correction proposée
```typescript
// orders/[orderId]/page.tsx
export default async function OrderTrackingPage({ params }) {
  let order: Order | null = null;
  try {
    order = await getOrder(params.orderId);
  } catch {
    notFound();
  }
  if (!order) notFound();
  // ...
}
```

Et dans `orders.service.ts`, convertir les 401 en retour null côté serveur :
```typescript
export async function getOrder(orderId: string): Promise<Order | null> {
  if (USE_MOCKS) { /* ... */ }
  try {
    const { data } = await apiClient.get<Order>(`/api/me/orders/${orderId}`);
    return data;
  } catch {
    return null;
  }
}
```

---

## Issue 7 — Recherche supérette insensible aux accents (résultats 0 pour "super")

### Type
Bug · Backend · Recherche

### Gravité
**Majeur**

### Étape du parcours
Étape 3 — Recherche supérette

### Reproduction
1. Sur `/stores`, saisir "super" dans le combobox de recherche
2. Observer les résultats

### Comportement observé
0 résultats pour "super", malgré la présence de "Supérette Test" en base.

```sql
SELECT LOWER('Supérette Test') LIKE LOWER('%super%');
-- → false
-- Raison : 'supérette' contient le caractère 'é' (≠ 'e')
-- La séquence 's-u-p-é-r' ≠ 's-u-p-e-r'
```

Vérification — la recherche fonctionne avec un terme sans accent :
```bash
GET /api/stores/search?query=Test → {"items":[{"name":"Supérette Test",...}], "total":1}  ✅
GET /api/stores/search?query=super → {"items":[], "total":0}  ❌
```

### Comportement attendu
"super" doit trouver "Supérette Test". La recherche doit être insensible aux accents.

### Preuves

| Fichier | `apps/backend/src/Repository/ShopRepository.php` |
|---|---|
| Méthode | `findActiveBySearchCriteria()` |
| Requête | `LOWER(s.name) LIKE LOWER(:query)` |

### Analyse technique
`LOWER()` ne normalise pas les accents. PostgreSQL fournit l'extension `unaccent` pour cela.

### Correction proposée
```php
// ShopRepository.php — activer unaccent
$qb->andWhere('LOWER(unaccent(s.name)) LIKE LOWER(unaccent(:query)) OR LOWER(unaccent(s.city)) LIKE LOWER(unaccent(:query))')
   ->setParameter('query', '%'.$query.'%');
```

Activer l'extension dans PostgreSQL :
```sql
CREATE EXTENSION IF NOT EXISTS unaccent;
```

---

## Issue 8 — Absence d'état de chargement (`skeleton`) sur la page catalogue

### Type
UX · Frontend

### Gravité
**Mineur**

### Étape du parcours
Étape 5 — Catalogue

### Reproduction
1. Aller sur `/stores/{id}/catalog`
2. Observer le temps de chargement initial

### Comportement observé
La grille est immédiatement vide, sans skeleton ni spinner pendant le fetch des produits. Si les données tardent, le client perçoit une page cassée.

### Comportement attendu
Afficher un skeleton de grille pendant le chargement, puis l'état vide ou les produits.

### Preuves
Screenshot `08-05-catalog.png` — grille vide visible dès le chargement, avant même que les erreurs soient connues.

### Correction proposée
Ajouter un état `isLoading` dans `CatalogPage` :
```tsx
// catalog/page.tsx
const [isLoading, setIsLoading] = useState(true);

useEffect(() => {
  setIsLoading(true);
  void listCatalog({ shopId, category, search })
    .then(setProducts)
    .finally(() => setIsLoading(false));
}, [shopId, category, search]);

// Dans le rendu :
{isLoading ? (
  <div className="grid grid-cols-2 gap-2.5">
    {Array.from({ length: 6 }).map((_, i) => (
      <div key={i} className="h-40 animate-pulse rounded-lg bg-product-tile" />
    ))}
  </div>
) : products.length === 0 ? (
  <p className="text-sm text-muted">Aucun produit disponible.</p>
) : (
  products.map(p => <ProductCard key={p.id} product={p} onAdd={onAdd} />)
)}
```

---

## 5. Point de blocage principal

**Le parcours client est bloqué à l'étape 2 — Connexion.**

La racine du blocage est **double** :

1. **Inscription impossible** : `clientRegister()` appelle `/api/auth/register` (404) au lieu de `/api/auth/register/customer`.
2. **Catalogue inaccessible même avec une session valide** : `listCatalog()` appelle `/api/stores/${shopId}/products` (404) au lieu de `/api/stores/${shopId}/catalog`, et le format de réponse backend (`{ items: [...] }` snake_case) ne correspond pas aux types TypeScript frontend (`ProductOffer[]` camelCase).

Ces deux bloquants suffisent à rendre le parcours complet non testable.

---

## 6. Priorisation

### Bloquants MVP — à corriger avant tout test client

| # | Fichier | Correction |
|---|---|---|
| Issue 1 | `auth.service.ts:66` | `/api/auth/register` → `/api/auth/register/customer` |
| Issue 3 | `stores.service.ts:10` | `GET /api/stores` → `GET /api/stores/search` (avec mapping) |
| Issue 4 | `catalog.service.ts:29` | `/products` → `/catalog` |
| Issue 5 | `catalog.service.ts` | Mapper `{ items }` + snake_case → `ProductOffer[]` camelCase |
| Issue 6 | `orders/[orderId]/page.tsx` | try/catch autour de `getOrder()` dans le server component |

### Correctifs majeurs — UX dégradée

| # | Fichier | Correction |
|---|---|---|
| Issue 2 | `login/page.tsx:33` | Intercepter l'erreur Axios, afficher message métier |
| Issue 7 | `ShopRepository.php` | Ajouter `unaccent()` dans la requête LIKE |

### Améliorations UX — non bloquantes

| # | Fichier | Correction |
|---|---|---|
| Issue 8 | `stores/{shopId}/catalog/page.tsx` | Ajouter skeleton pendant le chargement du catalogue |

---

## 7. Verdict final

| Question | Réponse |
|---|---|
| Le parcours client MVP est-il utilisable ? | **Non** — 5 bloquants empêchent le parcours complet |
| Peut-on passer une commande complète ? | **Non** — impossible sans login fonctionnel et catalogue accessible |
| Le suivi commande est-il testable ? | **Non** — page crash avec HTTP 500 |
| Quelles corrections sont nécessaires avant validation PO ? | Issues 1, 3, 4, 5, 6 (5 corrections de code ciblées, ~2h de travail estimé) |

**Bonne nouvelle** : les composants visuels (layout, navigation, formulaires, états vides) sont correctement implémentés et cohérents avec les maquettes. Le design mobile-first est propre. Les bloquants sont tous des désalignements de contrat API, pas des problèmes d'architecture.

---

## 8. Annexes techniques

### Fichiers lus pendant l'analyse

```
apps/frontend/src/lib/services/auth.service.ts
apps/frontend/src/lib/services/catalog.service.ts
apps/frontend/src/lib/services/stores.service.ts
apps/frontend/src/lib/services/kadhia.service.ts
apps/frontend/src/lib/services/orders.service.ts
apps/frontend/src/lib/services/slots.service.ts
apps/frontend/src/lib/services/store-search.service.ts
apps/frontend/src/lib/services/index.ts
apps/frontend/src/lib/api.ts
apps/frontend/src/types/index.ts
apps/frontend/src/app/(client)/login/page.tsx
apps/frontend/src/app/(client)/stores/page.tsx
apps/frontend/src/app/(client)/stores/[shopId]/catalog/page.tsx
apps/frontend/src/app/(client)/orders/[orderId]/page.tsx
apps/frontend/src/app/(client)/kadhia/slot/page.tsx
apps/frontend/src/components/store/StoreCard.tsx
apps/frontend/src/components/store/StoreSearchCombobox.tsx
apps/frontend/src/components/product/ProductCard.tsx
apps/frontend/src/lib/mock/shops.mock.ts
apps/frontend/src/lib/mock/products.mock.ts
apps/frontend/src/lib/mock/orders.mock.ts
apps/backend/src/Repository/ShopRepository.php
```

### Fichiers créés

```
/tmp/client-journey-test.mjs          — Script Playwright du parcours
/tmp/qa-test-results.json             — Résultats JSON bruts
/tmp/qa-screenshots/*.png             — 16 screenshots (01 à 16)
docs/qa/client-journey-simulation-report.md  — Ce rapport
```

### Commandes exécutées

```bash
# Vérification serveurs
curl -s http://localhost:8000/api/docs.json   # → backend actif
curl -s -o /dev/null -w "%{http_code}" http://localhost:3000/  # → 200

# Création compte test
curl -X POST http://localhost:8000/api/auth/register/customer \
  -H "Content-Type: application/json" \
  -d '{"email":"qa-journey-test@test.local","password":"Test1234!","name":"Test QA"}'
# → 201, token JWT

# Vérification endpoints
curl http://localhost:8000/api/stores/search?query=Test  # → {items:[...], total:1}
curl http://localhost:8000/api/stores/f0289d35.../catalog  # → {items:[coca cola, fanta]}

# Debug base de données
docker exec cc_postgres: SELECT id, name, active FROM shops;
# → 1 ligne: "Supérette Test", active=true

# Playwright
cd /tmp && node client-journey-test.mjs
```

### Routes frontend testées

| Route | Statut |
|---|---|
| `/` | ✅ Accessible |
| `/login` | ✅ Accessible, formulaire fonctionnel |
| `/register` | Non testé directement (bloc Issue 1) |
| `/stores` | ⚠️ Page chargée, liste vide |
| `/stores/shop-el-amel` | ❌ "Supérette introuvable" (ID mock) |
| `/stores/shop-el-amel/catalog` | ❌ Catalogue vide, erreurs API |
| `/kadhia` | ✅ Accessible, état vide correct |
| `/kadhia/slot` | ❌ Redirige vers `/login` (auth guard) |
| `/orders` | ✅ "Connecte-toi pour voir tes commandes" |
| `/orders/order-demo-4821` | ❌ HTTP 500 — "Une erreur est survenue" |

### Endpoints API testés

| Endpoint | Méthode | Statut | Commentaire |
|---|---|---|---|
| `/api/auth/register` | POST | 404 | Endpoint incorrect |
| `/api/auth/register/customer` | POST | 201 | Endpoint correct ✅ |
| `/api/auth/login` | POST | 200/401 | Fonctionne ✅ |
| `/api/stores` | GET | 404 | N'existe pas |
| `/api/stores/search?query=Test` | GET | 200 | Fonctionne ✅ |
| `/api/stores/{uuid}` | GET | 200 | Fonctionne ✅ |
| `/api/stores/{uuid}/catalog` | GET | 200 | Fonctionne, 2 produits ✅ |
| `/api/stores/{uuid}/products` | GET | 404 | Endpoint incorrect |
| `/api/me/stores` | GET | 200 (tableau vide) | Auth nécessaire |

### Screenshots réalisés

| Fichier | Étape |
|---|---|
| `01-01-homepage.png` | Page d'accueil |
| `02-02-login-page.png` | Page de connexion |
| `03-02-after-login.png` | Échec connexion — message 401 brut |
| `04-03-stores-page.png` | Page stores — liste vide |
| `07-04-store-detail.png` | Fiche supérette — "Supérette introuvable" |
| `08-05-catalog.png` | Catalogue — vide, "3 errors" |
| `11-06-kadhia-page.png` | Kadhia vide — état vide correct |
| `12-07-slot-page.png` | Créneaux — redirige vers login |
| `14-09-orders-list.png` | Liste commandes — "Connecte-toi" |
| `15-09-order-detail.png` | Détail commande — "Une erreur est survenue" |
