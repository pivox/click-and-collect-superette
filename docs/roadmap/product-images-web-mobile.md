# Images produits web/mobile — S13-005 / US-041 / Issue #391

Statut : **livré** (backend + frontend).

Pipeline de **stockage, transformation et diffusion** des images finales affichées
dans les catalogues client, marchand et admin. À ne pas confondre avec l'import
catalogue par photo IA (détection de produits depuis un ticket/rayon), hors périmètre.

## Décision technique

- Image **originale conservée** + variantes **WebP** `200 / 400 / 800 / 1200` px.
- **Fallback JPEG** (800 px) pour les navigateurs sans WebP (`<picture>`).
- Ratio carré 1:1 recommandé ; les variantes **préservent le ratio** (pas de déformation)
  et **n'agrandissent jamais** au-delà de l'original ; le rendu se fait dans une boîte
  carrée en `object-contain`.
- **Placeholder catégorie** (emoji / initiale) quand aucune image — une image manquante
  **ne bloque jamais** le produit.
- Génération via l'extension **GD** (aucun service externe, aucun binaire). `ext-gd`
  est déclarée dans `apps/backend/composer.json`.

## Pipeline image commun (réutilisable)

Services dans `apps/backend/src/Service/ProductImage/` :

| Service | Rôle |
|---|---|
| `ProductImageApplicationService` | **Point d'entrée unique** : valide la source, génère, stocke, applique la règle de statut, remplace l'ancienne image officielle. |
| `ProductImageVariantGenerator` | Validation contenu (MIME réel, dimensions min) + génération WebP + fallback JPEG (GD). |
| `ProductImageStorage` | Écriture/suppression des fichiers sous une racine maîtrisée, renvoie les chemins publics relatifs. |
| `ProductImageUrlBuilder` | Construit le payload responsive (`thumbnail/card/detail/zoom/fallback_jpeg`) ; base URL configurable (CDN futur). |
| `ProductImageStoreCommand` | DTO d'entrée du pipeline (contenu, source, cible, alt, statut éventuel). |

Le même pipeline est appelable depuis :

1. l'endpoint admin d'upload (`AdminProductReferenceImageController`) ;
2. l'enrichissement IA (`ProductAiEnrichmentResultApplier::attachCandidateImage()`) ;
3. les futurs imports catalogue par photo et contributions marchands.

```
AdminProductReferenceImageController ─┐
ProductAiEnrichmentResultApplier ─────┼─▶ ProductImageApplicationService
(futurs imports / contributions) ─────┘        ├─▶ ProductImageVariantGenerator
                                               ├─▶ ProductImageStorage
                                               └─▶ ProductImageUrlBuilder
```

## Image officielle vs candidate IA vs contribution marchand

Règle de gouvernance appliquée dans `ProductImageApplicationService` :

| Source (`ProductImageSource`) | Statut par défaut (`ProductImageStatus`) | Devient officielle ? |
|---|---|---|
| `admin_upload` | `verified` | ✅ oui (image officielle du référentiel) |
| `ai_enrichment` | `needs_review` | ❌ jamais automatiquement |
| `merchant_contribution` | `needs_review` | ❌ jamais automatiquement |
| `open_source` / `external_authorized_source` | `needs_review` | ❌ jamais automatiquement |

**Garde dure** : une source non-admin demandant explicitement `verified` est rétrogradée
en `needs_review`. Seule une image `verified` est exposée dans les catalogues. L'image
officielle appartient au `ProductReference` partagé ; un marchand ne remplace pas l'image
référentiel dans ce lot.

## Modèle de données

Entité `ProductImage` (table `product_images`, migration `Version20260604120000`) :

`id`, `product_reference_id` (nullable), `product_reference_proposal_id` (nullable),
`original_path`, `mime_type`, `width`, `height`, `variants` (JSON), `source`, `status`,
`alt_text`, `created_at`, `updated_at`.

`variants` (JSON) :

```json
{
  "200": "/uploads/products/{id}/200.webp",
  "400": "/uploads/products/{id}/400.webp",
  "800": "/uploads/products/{id}/800.webp",
  "1200": "/uploads/products/{id}/1200.webp",
  "fallback_jpeg": "/uploads/products/{id}/fallback.jpg"
}
```

> Évolution : colonnes `merchant_product_id` / `product_candidate_id` ajoutables plus tard
> quand ces flux seront livrés (volontairement hors table MVP pour éviter des colonnes orphelines).

## Endpoint admin

```
POST   /api/admin/product-references/{id}/image   (multipart/form-data, champ "image", "alt" optionnel)
DELETE /api/admin/product-references/{id}/image
```

- `ROLE_ADMIN` requis ; produit référentiel existant requis.
- Formats acceptés : JPEG, PNG, WebP. Taille max : **2 Mo** (configurable). Dimensions min : **400×400**.
- Validation du **contenu réel** (sniff binaire), pas de l'extension.
- Codes d'erreur : `PRODUCT_IMAGE_FILE_REQUIRED` (400), `PRODUCT_IMAGE_TOO_LARGE`,
  `PRODUCT_IMAGE_UNSUPPORTED_MIME`, `PRODUCT_IMAGE_TOO_SMALL`, `PRODUCT_IMAGE_UNREADABLE`,
  `PRODUCT_IMAGE_VARIANT_GENERATION_FAILED` (422), produit introuvable (404).
- Remplacement : l'ancienne image officielle (ligne + fichiers) est nettoyée.

## Exposition API

Objet `image` imbriqué (ou absent si aucune image — `null` exclu de la sérialisation) :

```json
"image": {
  "original_url": "…",
  "thumbnail_url": "…/200.webp",
  "card_url": "…/400.webp",
  "detail_url": "…/800.webp",
  "zoom_url": "…/1200.webp",
  "fallback_jpeg_url": "…/fallback.jpg",
  "alt": "…",
  "status": "verified"
}
```

Exposé dans :
- catalogue public client : `GET /api/stores/{storeId}/catalog` (items) ;
- détail/liste admin : `GET /api/admin/product-references/{id}` et la collection.

Les URL sont **relatives** par défaut (`/uploads/...`) ; le frontend les résout contre
l'origine API (`mediaUrl()`). Un `PRODUCT_IMAGE_BASE_URL` (param `app.product_image.public_base_url`)
permet de préfixer une origine CDN/stockage objet sans changer le code.

## Frontend

- `ProductThumbnail` (`<picture>` WebP + fallback JPEG, `loading="lazy"`, `object-contain`,
  placeholder emoji/initiale à l'erreur ou en l'absence d'image) — utilisé par `ProductCard`
  et `KadhiaLineRow`.
- `mediaUrl()` résout les chemins relatifs.
- Admin : section upload dans `ProductReferenceDrawer` (aperçu, garde type/taille côté client,
  messages d'erreur clairs, suppression).

## Limites restantes / prochaines PR

- Branchement **réel** de l'image IA non actif : le payload d'enrichissement OpenAI ne renvoie
  pas encore d'image ; le point d'extension `attachCandidateImage()` est prêt et testé.
- Pas d'écran d'administration de modération des images `candidate` / `needs_review`.
- Pas d'AVIF, pas de CDN externe obligatoire, pas de retouche/détourage (hors périmètre).
- Stockage local (`public/uploads/products/`) ; migration vers stockage objet à prévoir avant
  forte charge (le `ProductImageStorage` isole déjà cette responsabilité).
- Contribution marchand et `ProductCandidate` : structure prête (statuts/source), flux UI non livrés.
- **Unicité de l'image officielle non garantie en base** (limite acceptée, admin-only / faible
  risque — même posture que la race condition slug documentée dans `AI_CONTEXT.md`). Deux uploads
  admin simultanés sur le **même** `ProductReference` peuvent lire l'ancienne/absence d'image
  officielle avant le flush de l'autre, laissant brièvement deux lignes `verified` (la lecture
  retient la plus récente par `updatedAt`). Durcissement recommandé avant forte charge : index
  unique partiel `product_reference_id WHERE status = 'verified'` + remplacement transactionnel
  (suppression de l'ancienne avant insertion) avec gestion du conflit (409). Hors périmètre de ce lot.
