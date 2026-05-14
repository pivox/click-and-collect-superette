# US-050 — Afficher la photo et le logo de la supérette

**Epic** : EPIC-001 — Onboarding par QR code
**Sprint** : Sprint 5 — Administration minimale
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **voir la photo de devanture et/ou le logo de la supérette**,
afin de **reconnaître immédiatement la bonne enseigne et avoir confiance avant de commander**.

---

## Préconditions

- La supérette est active.
- L'administrateur ou le marchand a téléversé une photo/logo.

---

## Scénario nominal

1. Le client scanne le QR code d'une supérette.
2. La fiche supérette affiche : logo (si disponible), photo de devanture (si disponible), nom, adresse.
3. Si aucun visuel n'est disponible : placeholder générique avec les initiales de la supérette.

---

## Règles métier

- Deux types de visuels : **logo** (ratio 1:1, fond blanc recommandé) et **photo de devanture** (ratio 16:9).
- Les visuels sont téléversables par l'administrateur (lors de la création) et par le marchand depuis son backoffice.
- Formats acceptés : JPEG, PNG, WebP. Taille max : 5 Mo.
- Le logo est redimensionné en 200×200 et 400×400.
- La photo de devanture est redimensionnée en 800×450 (thumbnail) et 1600×900 (full).
- Un marchand ne peut modifier que les visuels de sa propre supérette.

---

## Critères d'acceptation

- [ ] Le logo de la supérette est affiché dans la fiche et dans la liste des supérettes du client.
- [ ] La photo de devanture est affichée en haut de la fiche supérette.
- [ ] Un placeholder s'affiche si aucun visuel n'est disponible.
- [ ] L'administrateur peut téléverser logo et photo depuis l'interface admin.
- [ ] Le marchand peut téléverser logo et photo depuis son backoffice.

---

## Notes techniques

**Champs à ajouter sur `Shop` :**
```php
#[ORM\Column(length: 500, nullable: true)]
private ?string $logoUrl = null;

#[ORM\Column(length: 500, nullable: true)]
private ?string $coverUrl = null;
```

**Endpoints upload :**
```http
POST /api/admin/stores/{storeId}/logo
POST /api/admin/stores/{storeId}/cover
POST /api/merchant/stores/{storeId}/logo
POST /api/merchant/stores/{storeId}/cover
Content-Type: multipart/form-data
```

**Exposition dans `StorePublicOutput` et `StoreByQrOutput` :**
```json
{
  "logo_url": "https://cdn.superette.tn/stores/ezzahra-logo-200.jpg",
  "cover_url": "https://cdn.superette.tn/stores/ezzahra-cover-800.jpg"
}
```

**Stockage :** Flysystem (même service que les photos produits US-041).
