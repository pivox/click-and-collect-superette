# Ajouter le catalogue complet v1.0.0 en local

Le fichier complet `catalogue_superette_tunisie_v1.0.0.json` contient les 5000 produits.

Il n'est pas ajoute automatiquement ici car il pese environ 13 Mo. Il doit etre ajoute depuis ton poste local dans la branche deja ouverte par la PR #300.

## Branche

```bash
git fetch origin
git checkout data/catalogue-superette-tunisie-v1.0.0
git pull --rebase origin data/catalogue-superette-tunisie-v1.0.0
```

## Copier le fichier complet

Place le fichier telecharge dans :

```bash
docs/product-data/catalogue-superette-tunisie/v1.0.0/catalogue_superette_tunisie_v1.0.0.json
```

## Verifier le checksum

Checksum attendu :

```bash
aebce31752e106ea41106faf48d2cb9de0816308ba72a877cc3ea34a2045be82
```

Commande macOS / Linux :

```bash
shasum -a 256 docs/product-data/catalogue-superette-tunisie/v1.0.0/catalogue_superette_tunisie_v1.0.0.json
```

La sortie doit correspondre a :

```text
aebce31752e106ea41106faf48d2cb9de0816308ba72a877cc3ea34a2045be82  docs/product-data/catalogue-superette-tunisie/v1.0.0/catalogue_superette_tunisie_v1.0.0.json
```

## Commit

```bash
git add docs/product-data/catalogue-superette-tunisie/v1.0.0/catalogue_superette_tunisie_v1.0.0.json

git commit -m "data: add full catalogue superette tunisie v1.0.0"

git push origin data/catalogue-superette-tunisie-v1.0.0
```

## Verification rapide

```bash
python3 - <<'PY'
import json
from pathlib import Path
path = Path('docs/product-data/catalogue-superette-tunisie/v1.0.0/catalogue_superette_tunisie_v1.0.0.json')
data = json.loads(path.read_text(encoding='utf-8'))
print(data['meta']['catalog_version'])
print(len(data['products']))
PY
```

Resultat attendu :

```text
1.0.0
5000
```
