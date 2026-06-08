# Documentation centrale — Images produits

Statut : **livré** — S13-005 / US-041 / Issue #391.

PRs de référence :

- #394 — `feat(s13-005): pipeline images produits web/mobile (US-041 / #391)`
- #399 — `feat(admin): images produits visibles et gérables dans le référentiel`

Cette page sert de synthèse centrale pour éviter de chercher l'information uniquement dans les PRs ou la roadmap détaillée.

## Périmètre livré

Le module gère l'image officielle d'un produit du référentiel (`ProductReference`) affichée dans les catalogues client, marchand et admin.

Livré :

- upload admin d'une image produit officielle ;
- suppression admin de l'image officielle ;
- entité dédiée `ProductImage` ;
- table `product_images` ;
- conservation de l'original ;
- variantes WebP `200 / 400 / 800 / 1200` ;
- fallback JPEG ;
- placeholder catégorie côté frontend si aucune image ;
- exposition de l'objet `image` dans le catalogue public ;
- exposition de l'objet `image` dans la liste et le détail admin ;
- affichage et gestion dans l'admin référentiel produits.

## Endpoints admin

```http
POST   /api/admin/product-references/{productReferenceId}/image
DELETE /api/admin/product-references/{productReferenceId}/image
```

### Upload

```http
POST /api/admin/product-references/{productReferenceId}/image
Content-Type: multipart/form-data
```

Champs :

- `image` : fichier obligatoire ;
- `alt` : texte alternatif optionnel.

Règles :

- `ROLE_ADMIN` requis ;
- le produit référentiel doit exister ;
- formats acceptés : JPEG, PNG, WebP ;
- taille max : 2 Mo ;
- dimensions minimales : 400×400 px ;
- validation du contenu réel, pas seulement de l'extension ;
- remplacement : l'ancienne image officielle est supprimée avec ses fichiers.

Réponse indicative :

```json
{
  "image": {
    "original_url": "/uploads/products/{image_id}/original.jpg",
    "thumbnail_url": "/uploads/products/{image_id}/200.webp",
    "card_url": "/uploads/products/{image_id}/400.webp",
    "detail_url": "/uploads/products/{image_id}/800.webp",
    "zoom_url": "/uploads/products/{image_id}/1200.webp",
    "fallback_jpeg_url": "/uploads/products/{image_id}/fallback.jpg",
    "alt": "Lait demi-écrémé",
    "status": "verified"
  }
}
```

Erreurs principales :

- `400 PRODUCT_IMAGE_FILE_REQUIRED` ;
- `404` produit introuvable ;
- `422 PRODUCT_IMAGE_TOO_LARGE` ;
- `422 PRODUCT_IMAGE_UNSUPPORTED_MIME` ;
- `422 PRODUCT_IMAGE_TOO_SMALL` ;
- `422 PRODUCT_IMAGE_UNREADABLE` ;
- `422 PRODUCT_IMAGE_VARIANT_GENERATION_FAILED`.

### Suppression

```http
DELETE /api/admin/product-references/{productReferenceId}/image
```

Réponse succès : `204 No Content`.

Erreur si aucune image officielle : `404 PRODUCT_IMAGE_NOT_FOUND`.

## Exposition API

L'objet `image` est exposé quand une image officielle existe.

Exposé dans :

- `GET /api/stores/{storeId}/catalog` ;
- `GET /api/admin/product-references` ;
- `GET /api/admin/product-references/{productReferenceId}`.

Quand aucune image officielle n'existe, `image` est absent ou non sérialisé selon le contexte. Le frontend doit afficher un placeholder catégorie. Une image manquante ne bloque jamais un produit.

## Gouvernance image

| Source | Statut par défaut | Officielle automatiquement |
| --- | --- | --- |
| `admin_upload` | `verified` | Oui |
| `ai_enrichment` | `needs_review` | Non |
| `merchant_contribution` | `needs_review` | Non |
| `open_source` / `external_authorized_source` | `needs_review` | Non |

Seule une image `verified` est exposée dans les catalogues.

Une image IA ou marchand ne devient jamais officielle automatiquement.

## Stockage et configuration

Stockage local actuel :

```text
public/uploads/products/
```

Les uploads sont ignorés par Git.

La base URL publique peut être préfixée par :

```text
PRODUCT_IMAGE_BASE_URL
```

Cela permet une bascule future vers CDN ou stockage objet sans changer le contrat API.

## Hors périmètre actuel

Non livré dans S13-005 :

- image sur proposition produit ;
- image sur catégorie ;
- image sur marque ;
- modération admin des images candidates `needs_review` ;
- branchement réel d'image IA dans le runner OpenAI ;
- stockage objet/CDN obligatoire ;
- AVIF ;
- retouche ou détourage automatique.

## Documentation détaillée

Voir aussi :

- `docs/roadmap/product-images-web-mobile.md` ;
- `docs/product/user-stories/US-041-photos-produits.md` ;
- `docs/architecture/api-contract.md`.
