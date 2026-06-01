# Validation du catalogue complet v1.0.0

Ce document sert a valider le fichier complet `catalogue_superette_tunisie_v1.0.0.json` une fois ajoute localement dans la PR #300.

## Fichier attendu

```text
docs/product-data/catalogue-superette-tunisie/v1.0.0/catalogue_superette_tunisie_v1.0.0.json
```

## Checksum attendu

```text
aebce31752e106ea41106faf48d2cb9de0816308ba72a877cc3ea34a2045be82
```

## Commandes de verification

### 1. Verification du checksum

```bash
shasum -a 256 docs/product-data/catalogue-superette-tunisie/v1.0.0/catalogue_superette_tunisie_v1.0.0.json
```

### 2. Verification JSON + version + volume

```bash
python3 - <<'PY'
import json
from pathlib import Path

path = Path('docs/product-data/catalogue-superette-tunisie/v1.0.0/catalogue_superette_tunisie_v1.0.0.json')
data = json.loads(path.read_text(encoding='utf-8'))

assert data['meta']['catalog_name'] == 'catalogue_superette_tunisie'
assert data['meta']['catalog_version'] == '1.0.0'
assert data['meta']['schema_version'] == 'product_catalog_verification_ready.v1'
assert len(data['products']) == 5000

print('OK catalogue_superette_tunisie v1.0.0')
print('products:', len(data['products']))
PY
```

### 3. Verification des champs minimum

```bash
python3 - <<'PY'
import json
from pathlib import Path

required = {
    'sku',
    'name_fr',
    'category',
    'subcategory',
    'unit',
    'brand',
    'brand_candidates',
    'commercial_identity',
    'brand_verification',
    'price_verification',
    'data_quality',
}

path = Path('docs/product-data/catalogue-superette-tunisie/v1.0.0/catalogue_superette_tunisie_v1.0.0.json')
data = json.loads(path.read_text(encoding='utf-8'))

missing = []
for product in data['products']:
    missing_fields = required - set(product.keys())
    if missing_fields:
        missing.append((product.get('sku'), sorted(missing_fields)))

assert not missing, missing[:10]
print('OK required fields')
PY
```

### 4. Verification des SKU uniques

```bash
python3 - <<'PY'
import json
from pathlib import Path

path = Path('docs/product-data/catalogue-superette-tunisie/v1.0.0/catalogue_superette_tunisie_v1.0.0.json')
data = json.loads(path.read_text(encoding='utf-8'))
skus = [p['sku'] for p in data['products']]

assert len(skus) == len(set(skus))
print('OK unique SKU:', len(skus))
PY
```

## Regles de validation metier

- Ne pas remplir `brand` depuis `brand_candidates` sans preuve.
- Ne pas passer `commerce_ready` a `true` sans marque ou reference fiable.
- Ne pas utiliser `estimated_price_tnd` en production tant qu'une source de prix n'est pas renseignee.
- Importer les produits en `draft` par defaut.
