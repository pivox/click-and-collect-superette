# US-041 — Afficher les photos des produits dans le catalogue

**Epic** : EPIC-011 — Référentiel produit et catalogue marchand
**Sprint** : Sprint 1 — Référentiel produit et catalogue marchand
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **voir la photo de chaque produit dans le catalogue**,
afin de **reconnaître immédiatement le produit que je cherche sans lire le nom**.

---

## Préconditions

- Le produit est présent dans le référentiel (`ProductReference`).
- La supérette propose ce produit dans son catalogue (`MerchantProduct`).

---

## Scénario nominal

1. Le client consulte le catalogue d'une supérette.
2. Chaque produit affiche : photo (vignette), nom, marque, format, prix en TND.
3. La photo est hébergée sur un CDN ou un stockage objet.
4. Si aucune photo n'est disponible, un placeholder catégorie est affiché (ex : icône « Produits laitiers »).

---

## Scénario nominal — Ajout de photo (admin)

1. L'administrateur sélectionne un produit du référentiel.
2. Il téléverse une photo (JPEG ou PNG, max 2 Mo, ratio 1:1 recommandé).
3. La photo est redimensionnée côté serveur (thumbnail 200×200, medium 600×600).
4. L'URL est stockée dans `ProductReference.imageUrl`.
5. Elle est immédiatement visible dans tous les catalogues marchands utilisant ce produit.

---

## Règles métier

- L'image appartient au référentiel partagé, pas au marchand individuel.
- Un marchand ne peut pas remplacer la photo d'un produit — il peut seulement activer/désactiver le produit.
- Formats acceptés : JPEG, PNG, WebP. Taille max : 2 Mo.
- Dimensions minimales : 400×400 px.
- La photo est optionnelle : un produit sans photo reste consultable.
- La photo par défaut est un placeholder générique par catégorie.

---

## Critères d'acceptation

- [ ] Chaque produit du catalogue affiche sa photo ou un placeholder catégorie.
- [ ] L'administrateur peut téléverser une photo depuis l'interface d'administration.
- [ ] La photo est redimensionnée en thumbnail et en medium côté serveur.
- [ ] Un produit sans photo affiche le placeholder sans erreur.
- [ ] La photo est visible dans le catalogue de toutes les supérettes proposant ce produit.
- [ ] Le catalogue reste lisible en cas d'échec de chargement des images (texte alternatif).

---

## Notes techniques

**Champ à ajouter sur `ProductReference` :**
```php
#[ORM\Column(length: 500, nullable: true)]
private ?string $imageUrl = null;
```

**Migration :** `ALTER TABLE product_references ADD COLUMN image_url VARCHAR(500) DEFAULT NULL`

**Endpoint upload (admin) :**
```http
POST /api/admin/product-references/{id}/image
Content-Type: multipart/form-data
```

**Réponse :**
```json
{ "image_url": "https://cdn.superette.tn/products/vitalait-demi-ecreme-1l.jpg" }
```

**Stockage :** AWS S3 ou stockage local compatible (Flysystem Symfony). Le service de stockage est injecté dans le processor.

**Exposition dans le catalogue public :**
```json
{
  "id": "<uuid>",
  "name_fr": "Lait demi-écrémé",
  "image_url": "https://cdn.../vitalait-200.jpg",
  "image_url_medium": "https://cdn.../vitalait-600.jpg"
}
```
