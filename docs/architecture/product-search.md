# Architecture — Recherche produit instantanée FR/AR avec Meilisearch (#636)

Date : 2026-10-06
Statut : **étude d'architecture**, aucune implémentation, aucun déploiement.
Issue de cadrage : [#636](https://github.com/pivox/click-and-collect-superette/issues/636)
(priorité P1 proposée, suivi global #527, lien UX client #205).
Protocole de recette et de performance : [`docs/qa/product-search-performance.md`](../qa/product-search-performance.md).

Ce document continue l'étude ouverte dans #636. Il part d'un audit du code réel
(branche `main` au 2026-10-06, backend, frontend web et application mobile), puis mappe
l'architecture cible de l'issue sur le modèle existant, tranche les points laissés
ouverts et propose un découpage en PRs. Les éléments marqués **à valider** demandent
une vérification ou une décision avant développement.

## 1. Rappel du périmètre

Quatre parcours de recherche **de produits**, un seul socle :

| Parcours | Index logique | Contexte d'autorisation | Existant à remplacer |
| --- | --- | --- | --- |
| Client : catalogue d'une supérette et composition de la Kadhia | `merchant_products` | public, supérette active | `GET /api/stores/{storeId}/catalog?query=` |
| Marchand : son catalogue | `merchant_products` | `ROLE_MERCHANT` + `MerchantShopAccessChecker` | `GET /api/merchant/stores/{storeId}/catalog?q=` |
| Marchand : ajout depuis le référentiel | `product_references` | `ROLE_MERCHANT` + `MerchantShopAccessChecker` | `GET /api/merchant/stores/{storeId}/product-references?q=&barcode=` |
| Admin : référentiel produit | `product_references` | `ROLE_ADMIN` | `GET /api/admin/product-references?q=` |

Contraintes non négociables reprises de #636 : aucune IA (ni LLM, ni embeddings, ni
hybride, ni traduction distante) ; PostgreSQL reste la source de vérité ; distinction
stricte `ProductReference` / `MerchantProduct` ; aucun ajout, fusion ou changement de
quantité sur une correspondance approximative ; isolation par supérette et autorisations
par membership conservées.

## 2. État des lieux (audit du 2026-10-06)

### 2.1 Backend — mécanique réelle des quatre recherches

| Parcours | Partie SQL | Partie PHP (mémoire) | Champs cherchés | Non cherchés |
| --- | --- | --- | --- | --- |
| Client `StoreCatalogProvider` → `MerchantProductRepository::findPublicCatalogForShop` (`apps/backend/src/Repository/MerchantProductRepository.php:194-242`) | `findBy(shop, isVisible, isAvailable)` sans jointure | prix > 0, référence approuvée, catégorie, **recherche `str_contains`**, tri `usort`, puis `count` + `array_slice` dans le provider (`StoreCatalogProvider.php:55-58`) | nom FR, nom AR, marque, unité, volume, variantes FR/AR, format compact `1l`/`500ml` | code-barres, alias, catégorie, note marchand |
| Marchand catalogue `MerchantCatalogProductCollectionProvider` → `filterCatalogForShop` (`MerchantProductRepository.php:40-117`) | `findBy(shop [, isAvailable, isVisible])` | catégorie, `q`, `needs_price`, `promotion`, tri, `count` + `array_slice` (`MerchantCatalogProductCollectionProvider.php:78-82`) | nom FR, marque, catégorie, note marchand, catégorie marchand | nom AR, code-barres, volume, alias |
| Marchand référentiel `ProductReferenceSearchProvider` → `ProductReferenceRepository::search` (`ProductReferenceRepository.php:26-66`) | DQL `LOWER(...) LIKE LOWER('%q%')` sur `nameFr`, `Brand.canonicalName`, `barcode = :exact`, `LIMIT/OFFSET`, `COUNT` exact | **N+1** : un `findOneForShopAndProductReference` par résultat pour `already_in_catalog` (`ProductReferenceSearchProvider.php:80`) | nom FR, marque, code-barres exact | nom AR, variantes, alias, volume ; aucun `unaccent` |
| Admin `AdminProductReferenceCollectionProvider` → `AdminProductReferenceRepository::findPaginated` (`AdminProductReferenceRepository.php:54-91`) | DQL `LIKE` sur `nameFr`, `nameAr`, `barcode = :exact`, `LIMIT/OFFSET`, `COUNT` | relations chargées paresseusement par ligne | nom FR, nom AR, code-barres exact | marque, alias, variantes ; aucun `unaccent` |

Constats transverses :

- **Aucune recherche produit n'utilise `UNACCENT`, `ILIKE`, le full-text PostgreSQL ni
  `pg_trgm`.** La fonction DQL `UNACCENT` (`src/Doctrine/Function/UnaccentFunction.php`)
  et la migration `Version20260526100000` ne servent qu'à la recherche de supérettes
  (`ShopRepository.php:58-63`).
- **Les deux catalogues (client et marchand) chargent tout le catalogue de la supérette
  en mémoire** avant de filtrer, trier et paginer en PHP. Le total est exact mais le coût
  est linéaire au catalogue, à chaque frappe.
- **Normalisation client** (`MerchantProductRepository.php:351-378`) : `strtolower`
  octet par octet (les majuscules accentuées ne sont pas abaissées), table d'accents FR,
  puis `iconv(ASCII//TRANSLIT//IGNORE)`. Un terme **mixte** arabe + latin perd ses
  caractères arabes ; un terme **purement arabe** est conservé tel quel, sans
  normalisation (hamza, diacritiques, chiffres arabes-indiens). Le test
  `PublicStoreCatalogApiTest::testPublicStoreCatalogCanFilterByQueryAndCategory`
  couvre `حليب` uniquement en égalité de sous-chaîne brute.
- **Alias** : `ProductReference.aliases` et `Brand.aliases` (JSON) existent mais
  **aucune** recherche ne les lit.
- **Code-barres** : `product_references.barcode` n'a plus d'index depuis
  `migrations/Version20260606120000.php:20` (l'unique a été supprimé pour permettre
  le nettoyage des doublons, sans index non unique de remplacement).
- **Caractères `%` et `_`** non échappés dans les `LIKE` marchand et admin.
- **Aucune politique de rate limiting** (`config/services.yaml`, `app.rate_limit.policies`)
  ne couvre une route de catalogue ou de recherche.
- **Messenger** : transport `async` Doctrine durable, `failure_transport: failed`,
  retry 3 × (1 s, 2 s, 4 s), un worker (`docker-compose.yml` service `worker`,
  `docker/supervisor/messenger-worker.conf`). Aucun message d'indexation, aucune outbox.
- **Autorisation** : `MerchantShopAccessChecker::canOperateShop` gère déjà les
  organisations et memberships (#568) avec repli propriétaire ; la recherche doit le
  réutiliser tel quel.

### 2.2 Frontend web (`apps/frontend/src`)

| Écran | Déclenchement | Course / annulation | Remarques |
| --- | --- | --- | --- |
| Catalogue client `app/(client)/stores/[shopId]/catalog/page.tsx` | **chaque frappe, sans temporisation** (`:287`, dépendance `search` de l'effet `:94`) | drapeau `cancelled` sur la première page ; **aucune garde sur « Charger plus »** (`:212-235`) : une page de l'ancienne recherche peut être ajoutée à la nouvelle | `nameAr` jamais affiché (`ProductCard.tsx:62`) ; pastilles catégories toujours en FR ; `AbortController` absent ; mock mémoire ne cherche que `nameFr`/`brand` |
| Catalogue marchand `app/merchant/catalogue/page.tsx` + `MerchantCatalogFilters.tsx` | **soumission du formulaire uniquement** | `requestId` séquentiel | `filterMerchantCatalogProducts` (service `:154-214`) est du code mort hors tests ; libellés FR codés en dur |
| Tiroir référentiel `components/merchant/catalogue/ProductReferenceSearchDrawer.tsx` | soumission uniquement, `limit: 20`, page 1 fixe | `sessionRef` + `searchRequestRef` | aucun message « aucun résultat » ; `name_ar` et `barcode` non affichés |
| Code-barres `MerchantCatalogOnboardingTools.tsx` | soumission, `≥ 8` chiffres, `BarcodeDetector` caméra ne déclenche pas la recherche | aucune garde | normalisation `\D` + 14 chiffres max |
| Admin référentiel `app/admin/referentiel/produits/page.tsx` | temporisation 400 ms | **aucune garde** (`load` `:146-170`), en contradiction avec le pattern `requestSeq` de `CLAUDE.md` | listes marques/catégories plafonnées à 50 |
| Admin propositions `propositions/page.tsx` | filtre **mémoire sur la page courante** (`:52-54`) | aucune | la recherche n'atteint pas le serveur |
| Admin groupements `groupements/page.tsx` | chaque frappe, sans temporisation | drapeau `cancelled` côté panneau détail seulement | |

`GlobalSearchBar` et `StoreSearchCombobox` (React Query, 400 ms, ≥ 2 caractères)
cherchent des **supérettes**, pas des produits ; ils ne sont pas dans le périmètre mais
constituent le seul usage de React Query du frontend.

### 2.3 Application mobile (`click-and-collect-mobile`, Expo SDK 57)

- Une seule recherche produit : écran catalogue client
  `src/app/(client)/stores/[storeId]/catalog.tsx`, temporisation 300 ms (`:71`), paramètre
  serveur `query`, `items_per_page` 30, garde `requestSeq` sur première page et pages
  suivantes (`:78-97`), **aucune annulation** (`apiFetch` accepte `signal`, aucun appel ne
  le passe), **aucune déduplication** des pages concaténées.
- `nameAr` affiché quand `i18n.locale === 'ar'` (`:33-35`) ; aucune normalisation arabe côté client.
- Aucun écran catalogue, référentiel ni scan code-barres produit côté marchand.
- Aucun test ne couvre `fetchCatalog` ni l'écran catalogue.

### 2.4 Conclusion de l'audit

Le diagnostic initial de #636 est **confirmé et élargi** : la recherche, le tri et la
pagination des deux catalogues sont en mémoire PHP ; le web client déclenche une requête
par frappe sans annulation ; « Charger plus » n'est pas protégé ; l'arabe n'est pas
normalisé ; les alias existants ne sont pas exploités ; le référentiel marchand souffre
d'un N+1. Un moteur ne corrige pas à lui seul ces points : la lecture SQL groupée,
le repli SQL paginé et les gardes frontend font partie du chantier.

## 3. Décisions d'architecture

| # | Décision | Justification |
| --- | --- | --- |
| D1 | **Meilisearch Community auto-hébergé** comme projection reconstruisible ; PostgreSQL seule source de vérité | Tolérance aux fautes, préfixes, FR/AR natifs, filtres avant classement ; conforme au cadrage #636 |
| D2 | **Un service commun derrière une interface** `ProductSearchEngineInterface` avec trois implémentations : `MeilisearchProductSearchEngine`, `SqlProductSearchEngine` (repli), `InMemoryProductSearchEngine` (tests) | Permet CI sans moteur, repli explicite, bascule par configuration |
| D3 | **Deux index logiques** `merchant_products` et `product_references`, préfixés par environnement (`MEILISEARCH_INDEX_PREFIX`, ex. `prod_`, `staging_`) | Un seul moteur pour toutes les supérettes ; isolation par filtre `shop_id` imposé côté Symfony |
| D4 | **Routes de recherche additives** `/search` par parcours ; les routes de listing existantes restent exhaustives et exactes | Évite de tronquer le catalogue à `maxTotalHits`, de présenter `estimatedTotalHits` comme exact ou de casser les contrats testés |
| D5 | **Synchronisation par messages d'identifiants** sur un transport Messenger Doctrine dédié `search`, émis **dans la transaction métier** (même connexion Doctrine) ; le handler reconstruit la projection depuis l'état courant | Atomicité outbox sans table dédiée, idempotence, suppression sans résurrection |
| D6 | **Dictionnaire d'alias persisté** (`SearchAlias`) alimenté par un jeu YAML versionné et relu humainement ; alias propagés dans les documents à l'indexation | Préfixes arabes dès la frappe ; aucune génération automatique |
| D7 | **Analyse déterministe de la requête** dans un composant PHP testable (`SearchQueryAnalyzer`), règles + dictionnaires, ambiguïtés remontées à l'interface | Volume, masse, pack, conditionnement, marque ; jamais de conversion silencieuse |
| D8 | **SDK officiel `meilisearch/meilisearch-php` en branche stable 1.x**, pas `meilisearch/search-bundle` | Le bundle indexe via des listeners Doctrine synchrones et des normalizers d'entités ; incompatible avec D5 et avec la projection calculée. **Dépendance de production à approuver** (`composer require`). |
| D9 | **Pas de Redis ni de cache applicatif de résultats** dans la première livraison | Mesure d'abord (protocole QA) ; Redis existe dans le Compose mais n'est pas câblé au backend |
| D10 | **Mode dégradé explicite** : coupe-circuit dans l'adaptateur, repli `SqlProductSearchEngine` borné et paginé en SQL, `meta.engine` dans la réponse | Ne jamais renvoyer « aucun produit » pour masquer une panne |

### 3.1 Versions — à valider avant tout `composer require` ou `docker pull`

| Composant | Observation du 2026-10-06 | Source | Action |
| --- | --- | --- | --- |
| Meilisearch serveur | dernière version stable publiée : `v1.54.3` (2026-10-01) ; `v1.53.3` et `v1.52.4` publiées le même jour (correctifs de stabilité) | [Releases GitHub](https://github.com/meilisearch/meilisearch/releases) | Figer un tag `v1.5x.y` **après** exécution du corpus de recette ; documenter mise à niveau et retour arrière (dump) |
| SDK PHP | branche `v1.x` stable (compatible « Meilisearch v1.x », PHP ≥ 8.1, client PSR-18 au choix) ; `v2.0.0-beta.7` (2026-07-22) **non retenue** en production | [README](https://github.com/meilisearch/meilisearch-php), [Packagist](https://packagist.org/packages/meilisearch/meilisearch-php) | `composer require meilisearch/meilisearch-php:^1` + client PSR-18 déjà présent ou `symfony/http-client` avec `nyholm/psr7` — **à valider** sur `composer.json` |
| `meilisearch/search-bundle` | `v0.16.1` (2026-02-10), requiert `meilisearch-php ^1.16 \|\| 2.0.0-beta.5` | [Packagist](https://packagist.org/packages/meilisearch/search-bundle) | Non retenu (D8) |

La documentation officielle `meilisearch.com/docs` n'était pas accessible depuis
l'environnement de rédaction ; les réglages de la section 6 reprennent ceux de #636 et
les noms de clés connus (`typoTolerance`, `prefixSearch`, `localizedAttributes`,
`searchCutoffMs`, `pagination.maxTotalHits`). **Chaque clé est à confirmer contre la
documentation de la version figée** avant d'écrire le fichier de réglages versionné.

## 4. Projections : mapping sur le modèle existant

### 4.1 Index `merchant_products`

Source : `MerchantProduct` et ses getters d'affichage (`getDisplayNameFr`, `getDisplayNameAr`,
`getDisplayBrandName`, `getDisplayCategorySlug`, `getDisplayVolume`, `getDisplayUnit`,
`MerchantProduct.php:171-234`) qui retombent sur `MerchantLocalProduct` quand la référence
est absente.

| Champ document | Source réelle | Remarque |
| --- | --- | --- |
| `id` | `MerchantProduct.id` (UUID RFC 4122) | clé primaire du document |
| `shop_id` | `MerchantProduct.shop.id` | filtre obligatoire client/marchand |
| `product_reference_id` | `productReference?.id` ou `null` | produits locaux : `null` |
| `local_product_id` | `localProduct?.id` | utile à la réconciliation |
| `name_fr`, `name_ar` | getters d'affichage | `name_ar` peut être `null` |
| `variant_terms` | `ProductReference.variantFr`, `variantAr` | `null` pour un produit local |
| `brand`, `brand_id` | `Brand.canonicalName` / `Brand.id` ou `MerchantLocalProduct.brandName` (`brand_id` `null`) | |
| `category_id`, `category_terms` | `Category.id` ; termes = `Category.nameFr`, `nameAr`, `MerchantCategory.nameFr`, `nameAr` quand actif | les deux hiérarchies sont indexées, pas fusionnées |
| `family_id` | `ProductReference.productFamily?.id` | |
| `aliases` | union des alias validés ciblant la marque, la catégorie, la famille et la référence (section 5) + `ProductReference.aliases` + `Brand.aliases` existants | tableau de chaînes |
| `volume_ml` | `volume` × 1000 si `unit = litre`, `volume` si `millilitre`, sinon absent | `ProductUnit` : `litre`, `millilitre`, `kilogramme`, `gramme`, `piece`, `paquet`, `botte` |
| `mass_g` | `volume` × 1000 si `kilogramme`, `volume` si `gramme`, sinon absent | le modèle n'a pas de champ masse distinct : `volume` porte la quantité quelle que soit l'unité |
| `pack_quantity` | `ProductReference.packQuantity` ou `MerchantLocalProduct.packQuantity` | |
| `packaging`, `packaging_terms` | **absent du modèle** | voir 4.3 |
| `barcode` | `ProductReference.barcode` ou `MerchantLocalProduct.barcode` | chaîne, zéros initiaux conservés, filtre exact uniquement |
| `is_visible`, `is_available` | champs directs | |
| `is_publicly_searchable` | `shop.isActive()` ∧ `isVisible` ∧ `isAvailable` ∧ `priceTnd > 0` ∧ (`productReference` null ∨ `status = approved`) | reprend exactement `findPublicCatalogForShop` (`MerchantProductRepository.php:205-216`) ; **ne remplace pas la revalidation en base** |
| `price_millimes` | `priceTnd` × 1000, entier | tri/filtre ; **prix affiché toujours relu en base** |
| `promotion_ends_on` | `promotionEndsOn` en `Y-m-d` ou absent | ne pas projeter `promotion_active` : il dépend de l'heure (Africa/Tunis, `MerchantProduct.php:339-351`) ; calculé à l'hydratation |
| `name_sort` | `name_fr` normalisé (section 5.1) | tri alphabétique stable |
| `version` | `max(updatedAt)` des sources en microsecondes | diagnostic de fraîcheur, pas de contrôle de concurrence côté moteur (section 7.4) |

### 4.2 Index `product_references`

| Champ | Source | Remarque |
| --- | --- | --- |
| `id`, `name_fr`, `name_ar`, `variant_terms`, `brand`, `brand_id`, `category_id`, `category_terms`, `family_id`, `aliases`, `volume_ml`, `mass_g`, `pack_quantity`, `barcode`, `name_sort`, `version` | comme 4.1, sans les champs d'offre | |
| `kind` | `industrial` / `generic` (ADR-0006) | filtrable ; les génériques n'ont pas de marque |
| `status` | `ProductReferenceStatus` | filtre `approved` imposé pour le contexte marchand ; libre pour l'admin |
| `quality_score` | `ProductReferenceQualityScorer` | **à valider** : utile si l'admin veut trier par qualité depuis la recherche ; sinon ne pas projeter |

Aucun prix, stock, note marchand, donnée personnelle ni URL d'image dans les deux index.
Les images restent chargées par `StoreCatalogProductBatchMapper` et équivalents sur la
page de résultats.

### 4.3 Écarts de modèle et propositions

| Écart | Proposition | Portée |
| --- | --- | --- |
| Pas de champ conditionnement (`verre`, `PET`, `canette`, `brique`) | **Première livraison** : `packaging` non projeté ; l'analyseur détecte le conditionnement, l'affiche comme contrainte « non filtrable, conservée dans le texte ». **Suite** : champ nullable `packaging` (enum) sur `ProductReference` et `MerchantLocalProduct` + migration + saisie admin/CSV | hors première PR, à valider |
| Pas d'index sur `product_references.barcode` | Ajouter un index non unique `IDX_PRODUCT_REFERENCES_BARCODE` (migration dédiée) | immédiat, indépendant du moteur |
| `%`/`_` non échappés dans les `LIKE` | Échapper dans `SqlProductSearchEngine` ; corriger au passage les deux repositories | repli SQL |
| Jointure interne `pr.brand` dans `AdminProductReferenceRepository::findPaginated` (`:66-68`) alors que `brand` est nullable depuis `Version20260930140000` | Les références génériques sont absentes du listing admin et de son total. **Bug préexistant à ouvrir séparément**, hors #636 | issue dédiée |
| Paramètre `promotion` lu mais non déclaré sur `MerchantCatalogListOutput` | Déclarer le `QueryParameter` | correctif annexe |

## 5. Dictionnaire, normalisation et analyse de requête

### 5.1 Normalisation commune (`SearchTextNormalizer`)

Appliquée à l'indexation (`name_sort`, alias) et à la requête, avant Meilisearch :

1. Unicode NFKC, suppression des caractères de contrôle, espaces multiples → un espace.
2. Minuscules **multi-octets** (`mb_strtolower`), correction du `strtolower` actuel.
3. Chiffres arabes-indiens `٠١٢٣٤٥٦٧٨٩` → `0-9` ; virgule décimale FR `1,5` → `1.5`.
4. Pliage des accents **latins uniquement** (`é → e`, `ç → c`), via `Transliterator` ICU
   `Latin-ASCII` ou la table existante ; **l'arabe n'est jamais translittéré**.
5. Arabe : suppression du tatweel `ـ` et des diacritiques (fatha, damma, kasra, shadda,
   sukun, tanwin) ; unification des alifs `أ إ آ → ا`. **À valider** : Meilisearch
   applique sa propre normalisation arabe ; n'appliquer côté Symfony que ce qui est
   nécessaire au `name_sort`, aux alias et à l'analyse, pour ne pas diverger du moteur.
6. Codes-barres : toute suite de 8 à 14 chiffres isolée est conservée **telle quelle**,
   zéros initiaux compris.

### 5.2 Dictionnaire d'alias (`SearchAlias`)

Entité proposée (`apps/backend/src/Entity/SearchAlias.php`, table `search_aliases`) :

```yaml
id: uuid
term: string(120)              # tel que saisi, ex. "كوكا", "coka"
normalized_term: string(120)   # section 5.1, indexé
language: fr|ar|mixed
target_type: brand|category|family|reference
target_id: uuid
scope_shop_id: uuid|null       # null = global ; sinon alias propre à une supérette
status: validated|proposed|rejected
source: seed|admin|merchant
created_at, updated_at
```

Index : `(normalized_term)`, `(target_type, target_id)`, unique `(normalized_term, target_type, target_id, scope_shop_id)`.

Règles :

- **Seed versionné** `apps/backend/config/search/aliases.seed.yaml` (FR, AR, mixte),
  relu humainement, chargé par `app:search:aliases:import` (idempotent, `status: validated`,
  `source: seed`). Jeu initial : marques courantes (`coca`, `coka`, `كوكا`, `كوكا كولا`),
  types de produit (`lait`/`حليب`, `eau`/`ماء`, `pain`/`خبز`, `huile`/`زيت`, `sucre`/`سكر`,
  `tomate`/`طماطم`…), familles. Liste exacte à construire avec le PO.
- `ProductReference.aliases` et `Brand.aliases` existants sont **importés** comme alias
  `reference`/`brand` validés au premier import, puis la source de vérité des alias devient
  `search_aliases`. **À valider** : conserver ou déprécier les colonnes JSON ensuite.
- Un alias `scope_shop_id` non nul n'est propagé que dans les documents de cette supérette.
  Aucun alias marchand n'enrichit automatiquement le dictionnaire global.
- Les synonymes Meilisearch (`synonyms`) ne portent que des **équivalences de concept
  validées** (`حليب ↔ lait`) ; tout le reste passe par le champ `aliases` des documents, ce
  qui rend la recherche par préfixe possible sur l'alias lui-même.
- Toute modification d'alias déclenche une réindexation ciblée (section 7.3).
- Administration : pas d'écran complet en première livraison ; l'import YAML et une
  commande d'export suffisent. Un écran admin « dictionnaire » est une suite possible.

### 5.3 Analyse déterministe (`SearchQueryAnalyzer`)

Entrée : chaîne brute. Sortie : objet immuable

```php
final readonly class AnalyzedSearchQuery
{
    public function __construct(
        public string $rawQuery,
        public string $normalizedQuery,
        public string $residualText,          // texte envoyé en `q`
        public ?string $barcode,              // mode exact si non null
        public ?int $volumeMl,
        public ?int $massG,
        public ?int $packQuantity,
        public ?string $packaging,            // détecté, non filtrable tant que 4.3 n'est pas livré
        public ?string $brandId,              // uniquement si l'alias cible une marque sans ambiguïté
        /** @var list<SearchAmbiguity> */
        public array $ambiguities,
    ) {}
}
```

Règles (toutes testées unitairement, voir protocole QA §5.1) :

| Motif | Interprétation | Retrait du texte |
| --- | --- | --- |
| `^\d{8,14}$` | code-barres → filtre exact `barcode`, `q` vide | oui |
| `(\d+(\.\d+)?)\s*(l\|lt\|litre\|litres\|لتر)` | `volume_ml` = n × 1000 | oui |
| `(\d+(\.\d+)?)\s*(ml\|مل)` | `volume_ml` = n | oui |
| `(\d+(\.\d+)?)\s*(cl)` | `volume_ml` = n × 10 | oui |
| `(\d+(\.\d+)?)\s*(kg\|كغ\|كيلو)` / `(g\|gr\|غ)` | `mass_g` | oui |
| `pack\s*(\d+)`, `(\d+)\s*[x×]\s*(volume)`, `lot de (\d+)` | `pack_quantity` (+ volume unitaire si présent) | oui |
| `verre\|pet\|canette\|brique\|bouteille\|sachet` **sans** chiffre accolé | `packaging` détecté, texte **conservé** | non |
| `(\d+)\s*verre(s)?` | **ambiguïté** `portion_or_glass_bottle` ; aucune contrainte posée | non |
| `(\d+)` isolé sans unité (`coca 2`) | **ambiguïté** `bare_number` ; aucune contrainte ; chiffre conservé | non |
| `sans sucre`, `zéro`, `zero`, `light`, `entier`, `demi-écrémé`… | texte conservé, jamais retiré | non |
| alias marque validé, un seul candidat | `brand_id` posé, terme **conservé** dans `q` (classement) | non |

`matchingStrategy: all` est demandé au moteur : les mots du texte résiduel doivent tous
correspondre. Les ambiguïtés sont renvoyées dans `meta.query.ambiguities` pour que
l'interface propose une clarification légère (« 1 verre : portion ou bouteille en verre ? »).

## 6. Réglages d'index versionnés

Fichiers proposés : `apps/backend/config/search/merchant_products.settings.json` et
`product_references.settings.json`, appliqués par `app:search:settings:apply` avant tout
import, avec attente des tâches `succeeded`. Base pour `merchant_products` (reprise de
#636, clés **à confirmer** contre la documentation de la version figée) :

```json
{
  "searchableAttributes": ["name_fr", "name_ar", "aliases", "brand", "variant_terms", "category_terms"],
  "displayedAttributes": ["id", "product_reference_id", "shop_id"],
  "filterableAttributes": ["shop_id", "is_visible", "is_available", "is_publicly_searchable",
    "product_reference_id", "category_id", "brand_id", "family_id",
    "volume_ml", "mass_g", "pack_quantity", "barcode", "price_millimes", "promotion_ends_on"],
  "sortableAttributes": ["name_sort", "price_millimes", "id"],
  "typoTolerance": {
    "enabled": true,
    "minWordSizeForTypos": { "oneTypo": 5, "twoTypos": 9 },
    "disableOnNumbers": true,
    "disableOnAttributes": ["barcode"]
  },
  "synonyms": { "حليب": ["lait"], "lait": ["حليب"] },
  "stopWords": [],
  "prefixSearch": "indexingTime",
  "pagination": { "maxTotalHits": 1000 },
  "searchCutoffMs": 150
}
```

Points de vigilance :

- `displayedAttributes` limité aux identifiants : le moteur ne renvoie jamais un nom ou un
  prix ; tout est relu en base (section 7.2).
- `searchCutoffMs` : coupe la recherche côté moteur et renvoie des résultats partiels
  (`degraded`) au-delà du budget. Valeur initiale 150 ms, **à valider** ; en dessous de la
  cible API p95 ≤ 150 ms cela signifie que le moteur ne peut pas consommer tout le budget.
- `localizedAttributes` : **à valider** si l'auto-détection de langue se révèle instable
  sur des documents mixtes FR/AR ; sinon ne pas le configurer.
- `maxTotalHits: 1000` borne la pagination d'une **recherche**, jamais le catalogue :
  les routes de listing exhaustif restent SQL (D4).
- Aucun `embedders`, aucun `chat`, aucune recherche hybride.

## 7. Intégration Symfony

### 7.1 Composants et emplacements

```text
apps/backend/src/Search/
  Engine/ProductSearchEngineInterface.php     search(SearchRequest): SearchResult (ids ordonnés + estimatedTotal + degraded)
  Engine/MeilisearchProductSearchEngine.php   client, timeouts 500 ms inactivité / 1 s total, coupe-circuit
  Engine/SqlProductSearchEngine.php           LIKE échappé, LIMIT/OFFSET SQL, borné à 50
  Engine/InMemoryProductSearchEngine.php      tests fonctionnels (when@test)
  Query/SearchTextNormalizer.php
  Query/SearchQueryAnalyzer.php
  Query/AnalyzedSearchQuery.php
  Policy/CustomerCatalogSearchPolicy.php      shop actif + filtres publics
  Policy/MerchantCatalogSearchPolicy.php      MerchantShopAccessChecker + filtres marchand
  Policy/MerchantReferentialSearchPolicy.php  status=approved + already_in_catalog groupé
  Policy/AdminReferentialSearchPolicy.php     filtres statut/marque/catégorie/kind
  Projection/MerchantProductDocumentBuilder.php
  Projection/ProductReferenceDocumentBuilder.php
  Hydration/MerchantProductSearchHydrator.php lecture groupée + revalidation + ordre
  Hydration/ProductReferenceSearchHydrator.php
  Sync/ (messages, handlers, commandes — section 7.3)
src/Provider/StoreCatalogSearchProvider.php, MerchantCatalogSearchProvider.php,
    MerchantProductReferenceSearchProvider.php, AdminProductReferenceSearchProvider.php
src/ApiResource/*SearchOutput.php
```

Le **provider** ne fait que : valider les paramètres (longueur de `q` ≤ 120, `limit` ≤ 50,
`page` ≤ 50), résoudre le contexte via la policy, appeler le service de recherche,
hydrater, sérialiser. Aucune expression de filtre, nom d'index ou champ de tri n'est
accepté du client : la policy construit la liste de filtres, les valeurs sont échappées
(`"` et `\` dans les chaînes, UUID validés par `Uuid::isValid`).

### 7.2 Flux d'une recherche

1. Policy : autorisation (`MerchantShopAccessChecker::denyUnlessMerchantOwnsShop`, shop
   actif, `ROLE_ADMIN`), filtres obligatoires (`shop_id = "<uuid>"`,
   `is_publicly_searchable = true`…), filtres optionnels validés (`category_id`, `brand_id`).
2. Analyse : `SearchQueryAnalyzer` → texte résiduel + contraintes + ambiguïtés. Un
   code-barres bascule en filtre exact avec `q` vide.
3. Moteur : `limit = demandé + marge` (20 demandés → 30 récupérés) pour compléter la page
   après revalidation ; `attributesToRetrieve: ["id"]`.
4. Hydratation : **une** requête `findBy(['id' => $ids])` avec jointures `productReference`,
   `brand`, `category`, `localProduct`, `merchantCategory` ; **une** requête images via le
   batch mapper existant ; pour le référentiel marchand, **une** requête
   `findBy(['shop' => $shop, 'productReference' => $ids])` pour `already_in_catalog`
   (supprime le N+1 actuel).
5. Revalidation : réapplication des règles de la policy sur l'état base (appartenance,
   visibilité, disponibilité, prix > 0, statut, promotion active calculée maintenant).
   Les documents invalides sont retirés ; la page est tronquée à `limit`. S'il reste moins
   de `limit` résultats alors que `estimated_total` en annonçait plus, `has_more` reste
   `true` et la page suivante est demandée avec `offset + reçus_moteur`.
6. Réponse : ordre de pertinence préservé ; `estimated_total` nommé ainsi ; `meta.engine`.

### 7.3 Synchronisation PostgreSQL → Meilisearch

**Émission dans la transaction (D5).** Un listener Doctrine `onFlush` collecte les
entités concernées (`MerchantProduct`, `MerchantLocalProduct`, `ProductReference`,
`Brand`, `Category`, `MerchantCategory`, `ProductFamily`, `SearchAlias`, `Shop` pour
`archivedAt`) et dispatch des messages **d'identifiants** sur un transport `search`
(Doctrine, même DSN, `queue_name: search`). Comme le transport Doctrine écrit avec la
connexion de l'application, l'`INSERT` dans `messenger_messages` est commité ou annulé
avec la transaction métier : un rollback n'émet rien, un commit émet toujours. Aucun
appel réseau vers Meilisearch n'a lieu pendant la requête HTTP. Vérifié le 2026-10-06 :
`config/packages/doctrine.yaml` ne déclare qu'une connexion DBAL ; le transport Doctrine
la réutilise. **À valider** en test fonctionnel (rollback → aucune ligne dans
`messenger_messages`).

Messages (`src/Search/Sync/Message/`) :

| Message | Charge | Effet du handler |
| --- | --- | --- |
| `ReindexMerchantProductsMessage` | `list<string> $ids` (≤ 500) | relit les offres ; document ajouté/mis à jour ; **absent en base → `deleteDocuments`** |
| `ReindexProductReferencesMessage` | `list<string> $ids` | idem sur `product_references`, puis fan-out `ReindexMerchantProductsByReferenceMessage` |
| `ReindexMerchantProductsByReferenceMessage` | `string $referenceId` | itère les offres de la référence par lots de 500 |
| `ReindexMerchantProductsByShopMessage` | `string $shopId` | réactivation/archivage de supérette, alias local |
| `ReindexByBrandMessage`, `ReindexByCategoryMessage`, `ReindexByFamilyMessage` | id | fan-out par lots |

Propriétés :

- **Idempotence** : le handler ne porte aucun état ; il reconstruit depuis la base. Un
  message rejoué, dupliqué ou tardif produit le même document ou la même suppression.
- **Suppression sans résurrection** : un ancien message pour une entité supprimée trouve
  `null` en base et émet une suppression ; jamais un ajout.
- **Suivi des tâches** : le handler appelle `waitForTask(taskUid, timeout 5 s)` ; `failed`
  ou timeout lèvent une exception → retry Messenger (3) → transport `failed`, visible dans
  `GET /api/admin/ops/messenger` (#420). Un HTTP 202 n'est jamais considéré comme un succès.
- **Lots** : `BatchHandlerInterface` Symfony pour regrouper jusqu'à 100 messages d'offres
  en un seul `addDocuments`.

Commandes (`src/Command/Search*`), toutes idempotentes :

| Commande | Rôle |
| --- | --- |
| `app:search:settings:apply [--index=]` | crée l'index si absent, applique les réglages versionnés, attend `succeeded` |
| `app:search:aliases:import [--file=]` | charge le seed YAML dans `search_aliases` |
| `app:search:import --index= [--swap]` | import complet par lots de 1 000 via itération paginée (`toIterable`, `clear()`), vers `index_tmp_<timestamp>` ; avec `--swap`, bascule atomique `swapIndexes` puis suppression différée de l'ancien |
| `app:search:reindex --shop= \| --reference= \| --since=` | réindexation ciblée |
| `app:search:reconcile [--fix]` | compare identifiants et `version` base ↔ moteur, rapporte les écarts, répare avec `--fix` |
| `app:search:health` | `/health`, nombre de documents par index, tâches en attente/échouées, âge de la plus ancienne tâche |

Reconstruction complète : `settings:apply` sur l'index temporaire → `import` → les
messages émis pendant l'import continuent d'être appliqués **sur l'index courant** ; après
`swap`, rejouer `reindex --since=<début import>` pour rattraper les écritures concurrentes
→ `reconcile`. L'ordre est documenté dans le runbook (section 9).

### 7.4 Concurrence des projections

Meilisearch ne rejette pas une écriture obsolète. Deux handlers traitant la même offre en
parallèle peuvent écrire dans le désordre. Décision : **un seul process worker** consomme
le transport `search` (`numprocs=1`), ce qui sérialise les écritures par construction ;
le champ `version` sert au diagnostic (`reconcile`) et non au contrôle. Si le débit exige
plusieurs workers, l'évolution est un partitionnement par `shop_id` (une file par
partition), à décider sur mesure.

### 7.5 Mode dégradé et protection

- **Coupe-circuit** dans `MeilisearchProductSearchEngine` : 5 échecs (timeout, 5xx,
  connexion) sur 30 s → ouvert 60 s → demi-ouvert. État conservé dans `cache.app`
  (même mécanisme que `FixedWindowCacheRateLimiter`).
- **Repli** `SqlProductSearchEngine` : `LIKE` échappé sur `name_fr`, `name_ar`, marque,
  `UNACCENT` côté PostgreSQL, **`LIMIT`/`OFFSET` en SQL**, `limit ≤ 50`, pas de
  tolérance aux fautes. La réponse porte `meta.engine: "sql_fallback"`,
  `meta.degraded: true`. Si le repli échoue aussi : HTTP 503 `SEARCH_UNAVAILABLE`,
  jamais une liste vide.
- **Rate limiting** via les politiques existantes (`app.rate_limit.policies`) :
  `catalog_search_ip` (`GET`, `path_prefix: /api/stores/`, suffixe `/catalog/search`,
  120/min par IP), `merchant_search_user` (240/min), `admin_search_user` (240/min).
  **À valider** : le `RateLimitSubscriber` filtre aujourd'hui par `path`/`path_prefix` ;
  un motif de suffixe peut nécessiter une petite extension.
- Le chemin de recherche n'injecte aucun client IA et fonctionne sans variable `OPENAI_*`.

## 8. Contrat API (évolution additive, D4)

Nouvelles routes, les routes existantes **ne changent pas** :

```http
GET /api/stores/{storeId}/catalog/search?q=coka&category_id=&page=1&limit=20
GET /api/merchant/stores/{storeId}/catalog/search?q=&visibility=&availability=&page=&limit=
GET /api/merchant/stores/{storeId}/product-references/search?q=&barcode=&brand_id=&category_id=&page=&limit=
GET /api/admin/product-references/search?q=&status=&brand_id=&category_id=&kind=&page=&limit=
```

Réponse commune :

```json
{
  "items": [ /* même DTO item que la route de listing correspondante */ ],
  "page": 1,
  "limit": 20,
  "estimated_total": 42,
  "has_more": true,
  "meta": {
    "engine": "meilisearch",
    "degraded": false,
    "query": {
      "residual_text": "coca",
      "constraints": { "volume_ml": 1000, "packaging": "glass" },
      "ambiguities": [
        { "code": "portion_or_glass_bottle", "term": "1 verre",
          "options": ["volume_ml:1000", "packaging:glass"] }
      ]
    }
  }
}
```

Règles :

- `estimated_total` n'est **jamais** renommé `total` ; les interfaces affichent « environ ».
- `items` réutilise `StoreCatalogProductOutput`, `MerchantCatalogProductOutput`,
  `ProductReferenceItemOutput` et `AdminProductReferenceOutput` : aucun nouveau DTO item.
- Les routes de listing (`/catalog`, `/product-references`, `/admin/product-references`)
  restent la navigation exhaustive, exacte et paginée. Leur paramètre `query`/`q` continue
  de fonctionner en SQL ; **une PR indépendante** doit faire passer les deux catalogues de la
  pagination mémoire à une pagination SQL (section 10, PR 0).
- Erreurs : 400 `SEARCH_QUERY_TOO_LONG`, 400 `SEARCH_INVALID_FILTER`, 403
  `MERCHANT_CATALOG_FORBIDDEN` (code existant), 404 `STORE_NOT_FOUND`, 429 `RATE_LIMITED`,
  503 `SEARCH_UNAVAILABLE`.
- Mise à jour de `docs/architecture/api-contract.md` dans la PR d'intégration API.

## 9. Déploiement, Docker et sécurité

Service à ajouter au `docker-compose.yml` existant (réseau par défaut, nommage `cc_*`) :

```yaml
  meilisearch:
    image: "${MEILISEARCH_IMAGE:?Définir une image Meilisearch Community versionnée et testée}"
    container_name: cc_meilisearch
    restart: unless-stopped
    environment:
      MEILI_ENV: "${MEILI_ENV:-development}"
      MEILI_MASTER_KEY: "${MEILI_MASTER_KEY:?Définir MEILI_MASTER_KEY (openssl rand -hex 32)}"
      MEILI_HTTP_ADDR: "0.0.0.0:7700"
      MEILI_DB_PATH: /meili_data/data.ms
      MEILI_NO_ANALYTICS: "true"
      MEILI_MAX_INDEXING_MEMORY: "${MEILI_MAX_INDEXING_MEMORY:-1073741824}"
      MEILI_MAX_INDEXING_THREADS: "2"
      MEILI_DUMP_DIR: /meili_data/dumps
      MEILI_SNAPSHOT_DIR: /meili_data/snapshots
      MEILI_SCHEDULE_SNAPSHOT: "86400"
    mem_limit: 2g
    volumes:
      - meilisearch_data:/meili_data
    ports:
      - "127.0.0.1:7700:7700"
    healthcheck:
      test: ["CMD-SHELL", "curl -fsS http://localhost:7700/health || exit 1"]
      interval: 10s
      timeout: 3s
      retries: 10
```

- Valeurs mémoire : 1 Gio d'indexation / 2 Gio de conteneur en **développement** ;
  production option A : 2 Gio / 4 Gio comme dans #636, à ajuster après mesure.
  **À valider** : présence de `curl` dans l'image officielle pour le healthcheck ; sinon
  utiliser `wget` ou un healthcheck côté `backend`.
- `backend` et `worker` reçoivent `MEILISEARCH_URL=http://meilisearch:7700`,
  `MEILISEARCH_SEARCH_KEY`, `MEILISEARCH_WORKER_KEY`, `MEILISEARCH_INDEX_PREFIX`.
  La clé principale n'est **jamais** fournie à `backend` ; seule la commande de déploiement
  (`settings:apply`, `import --swap`, création des clés) l'utilise, via `MEILISEARCH_ADMIN_KEY`
  injectée au moment de l'exécution.
- Création des clés au déploiement (`app:search:keys:ensure`, idempotente) :
  `search` → actions `search` sur `<prefix>merchant_products`, `<prefix>product_references` ;
  `worker` → `documents.add`, `documents.delete`, `tasks.get`, `indexes.get` sur les mêmes
  index. Expiration 12 mois et rotation documentées dans le runbook.
- Fichier d'exemple sans secret : `.env.meilisearch.dist` ; `.env.meilisearch` ignoré par git.
- Aucun accès navigateur au moteur ; port 7700 limité au loopback hôte.
- Sauvegarde : snapshot quotidien sur le volume **plus** copie hors serveur ; la
  restauration se teste (`--import-snapshot`) ; PostgreSQL reste sauvegardé séparément.
  Un index se reconstruit toujours depuis PostgreSQL (`app:search:import --swap`).
- Option B (moteur séparé) : même service sur un second VPS, `MEILISEARCH_URL` en HTTPS
  privé/VPN, pare-feu limité au VPS applicatif. Déclenchée par les mesures, pas par un
  nombre de marchands.
- Runbook à créer : `docs/ops/meilisearch.md` (démarrage, réglages, import, swap,
  reconcile, rotation des clés, snapshot/restauration, mise à niveau et retour arrière).

## 10. Découpage proposé en PRs

Chaque PR est indépendante, testée (PHPStan niveau 6, CS Fixer, PHPUnit ; vitest ; jest-expo),
documentée, sans IA. Les numéros sont un ordre, pas des sous-issues : #636 précise qu'elle
n'en crée pas automatiquement ; leur ouverture reste une décision PO.

| PR | Contenu | Dépend de | Tests clés |
| --- | --- | --- | --- |
| **0 — Durcissement SQL immédiat** | pagination/tri **SQL** des deux catalogues (`findPublicCatalogForShop`, `filterCatalogForShop`) avec `UNACCENT` + jointures `fetch`, index `IDX_PRODUCT_REFERENCES_BARCODE`, échappement `%`/`_`, suppression du N+1 `already_in_catalog`, déclaration du paramètre `promotion` | — | non-régression `PublicStoreCatalogApiTest`, `MerchantCatalogApiTest`, `ProductReferenceSearchApiTest` ; comptage de requêtes SQL |
| **1 — Socle moteur** | dépendance SDK (approbation `composer require`), service Compose, `.env.meilisearch.dist`, `MeilisearchProductSearchEngine` + `InMemory` + interface, réglages versionnés, `settings:apply`, `health`, `keys:ensure`, runbook | — | unitaires client/coupe-circuit ; CI sans moteur |
| **2 — Dictionnaire, normalisation, analyse** | `SearchAlias` + migration, seed YAML + `aliases:import`, `SearchTextNormalizer`, `SearchQueryAnalyzer`, builders de documents | 1 | unitaires exhaustifs (QA §5.1) |
| **3 — Synchronisation** | transport `search`, listener `onFlush`, messages/handlers, `import --swap`, `reindex`, `reconcile`, worker dédié (Compose + Supervisor) | 1, 2 | rollback, doublons, hors ordre, suppression, tâche échouée, propagation alias |
| **4 — Intégration API** | 4 providers `/search`, policies, hydrateurs, repli SQL, rate limiting, contrat API | 0, 2, 3 | fonctionnels par parcours (QA §5.2) + sécurité |
| **5 — Interfaces web** | catalogue client (temporisation 100 ms, `AbortController`, garde première page **et** « Charger plus », Entrée immédiate, dédup, états distincts, `nameAr`, RTL), tiroir référentiel (recherche instantanée, « aucun résultat », `name_ar`/`barcode`), admin référentiel (`requestSeq`), clarification d'ambiguïté | 4 | vitest horloge contrôlée (QA §5.3) |
| **6 — Mobile** | `fetchCatalog` sur `/catalog/search` avec `signal`, temporisation 100 ms, dédup des pages, `meta.degraded` affiché, tests jest-expo | 4 | QA §5.4 |
| **7 — Recette et exploitation** | jeu synthétique, scripts de charge, rapport avant/après, arbitrage des écarts, mise à jour `AI_CONTEXT.md` et roadmap | 4, 5, 6 | protocole QA complet |

La PR 0 apporte un gain immédiat indépendamment du moteur et constitue le repli de D10.

## 11. Risques et points à valider

| Risque / question | Impact | Position proposée |
| --- | --- | --- |
| Clés de réglage Meilisearch non vérifiées contre la documentation de la version figée | `settings:apply` rejeté | vérifier à la PR 1, documentation accessible depuis le poste de développement |
| Normalisation arabe Symfony divergente de celle du moteur | résultats incohérents entre `name_sort`/alias et le classement | limiter la normalisation Symfony au strict nécessaire, tester le corpus AR |
| Un seul worker `search` | retard d'indexation sous import massif | mesurer ; partitionner par `shop_id` si nécessaire |
| Dispatch dans la transaction via transport Doctrine | dépend d'une connexion unique | vérifier `doctrine.yaml` ; sinon table outbox dédiée + relais |
| `packaging` absent du modèle | `coca 1l verre` ne filtre pas le conditionnement | détecté et affiché, filtrage après extension du modèle |
| Promotions à échéance | `price_millimes` projeté peut être le prix barré | ne jamais afficher un prix du moteur ; recalcul à l'hydratation ; réindexation nocturne des offres dont `promotion_ends_on` est passé (`reindex --since`) |
| Référentiel admin : jointure interne `brand` exclut les génériques | bug préexistant | issue dédiée hors #636 |
| Dépendance de production nouvelle (SDK + client PSR-18) | revue sécurité, approbation `composer require` | justifiée dans la PR 1 |
| Coût d'hébergement | option A 15–20 € HT/mois estimés dans #636 | revalider sur offre réelle avant achat |

## 12. Hors périmètre

IA sous toute forme, recherche sémantique/vectorielle, achats ou provisionnement
automatique, refonte commandes/Kadhias, panier multi-supérette, import d'images,
traduction exhaustive automatique, création/fusion automatique de références, haute
disponibilité, Redis imposé, recherche globale sur commandes/clients/supérettes.

## Références

- Issue [#636](https://github.com/pivox/click-and-collect-superette/issues/636) et suivi [#527](https://github.com/pivox/click-and-collect-superette/issues/527)
- `docs/architecture/api-contract.md`, `docs/architecture/data-model.md`, `docs/adr/0006-generic-product-references.md`
- `docs/ops/messenger-worker.md` (worker existant), `docs/qa/product-search-performance.md` (protocole)
- Meilisearch : [releases](https://github.com/meilisearch/meilisearch/releases), [SDK PHP](https://github.com/meilisearch/meilisearch-php), documentation officielle citée dans #636
- [Symfony Messenger](https://symfony.com/doc/current/messenger.html), [Doctrine events `onFlush`](https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/events.html)
