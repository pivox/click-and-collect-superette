# Référentiel produit Tunisie — Click & Collect Supérette

## 1. Objectif

L'application doit permettre à un marchand de retrouver des produits déjà référencés, puis de les ajouter à son magasin avec ses propres informations commerciales : prix, stock, disponibilité et délai de préparation.

Le marchand ne doit pas recréer chaque produit à la main. L'utilisateur final doit aussi pouvoir rechercher des produits déjà connus dans l'application.

Le modèle cible est donc :

```text
ProductMasterTunisia
        ↓
MerchantProductOffer
        ↓
Catalogue utilisateur
```

Le référentiel produit global contient l'identité du produit. L'offre marchand contient les informations propres au vendeur.

---

## 2. Principe fondamental

Un produit global ne doit pas être confondu avec une offre marchand.

Exemple :

```text
Produit global :
- Lait demi-écrémé 1 L — Vitalait

Offres marchands :
- Supérette A : 1.950 TND, stock 12
- Supérette B : 2.000 TND, stock 5
- Enseigne C : 1.890 TND, stock 30
```

Le produit est unique dans `ProductMasterTunisia`, mais chaque marchand peut avoir son propre prix, son propre stock et sa propre disponibilité.

---

## 3. Pourquoi conserver les marques

La marque est une donnée fonctionnelle indispensable.

Elle permet de :

- distinguer des produits similaires ;
- améliorer la recherche utilisateur ;
- accélérer la recherche côté marchand ;
- éviter les doublons ;
- préparer un modèle marketplace multi-marchands ;
- comparer les offres de plusieurs vendeurs sur un même produit.

Sans marque, cette donnée est trop vague :

```text
Lait demi-écrémé 1 L
```

Avec marque, le produit devient identifiable :

```text
Lait demi-écrémé 1 L — Vitalait
Lait demi-écrémé 1 L — Délice
Lait demi-écrémé 1 L — Candia
```

La marque ne doit pas être utilisée pour faire croire à un partenariat officiel. Elle sert ici uniquement à identifier un produit réel vendu par un marchand.

---

## 4. Donnée minimale d'identification produit

Le référentiel produit tunisien doit au minimum contenir :

```text
- marque
- nom générique
- volume ou poids
- unité
- catégorie simple
- code-barres si connu
- source de la donnée
- statut de validation
```

Exemples :

```text
Vitalait — Lait demi-écrémé — 1 L
Safia — Eau minérale — 1,5 L
Délice — Yaourt nature — 110 g
Jouda — Harissa — 135 g
Randa — Spaghetti — 500 g
```

Ces données correspondent à une identification factuelle minimale du produit, sans image, sans logo et sans description marketing.

---

## 5. Sources de données possibles

Il n'existe pas nécessairement un catalogue tunisien complet, gratuit et prêt à l'emploi. Le référentiel doit être construit progressivement.

Sources recommandées :

| Source | Usage |
|---|---|
| Open Food Facts | Alimentaire, code-barres, marques, quantités, catégories |
| Open Products Facts | Produits non alimentaires |
| Open Beauty Facts | Hygiène, beauté, cosmétique |
| Scans marchands | Source terrain principale |
| Imports fournisseurs | Source professionnelle pour codes-barres, prix d'achat, colisage |
| GS1 Tunisia | Vérification des codes-barres / GTIN |
| Validation interne | Nettoyage, dédoublonnage, correction |

### 5.1 Open Food Facts

Open Food Facts peut servir de seed initial, mais il ne couvrira pas tout le marché tunisien.

À importer en priorité :

```text
- produits avec pays Tunisie ;
- produits dont le code-barres commence par 619 ;
- produits scannés ensuite par les marchands ;
- produits alimentaires courants vendus localement.
```

Attention : ne pas filtrer uniquement sur `619`, car beaucoup de produits vendus en Tunisie sont importés et peuvent avoir des préfixes étrangers.

Export utile :

```bash
wget https://static.openfoodfacts.org/data/openfoodfacts-products.jsonl.gz
```

API produit par code-barres :

```http
GET https://world.openfoodfacts.org/api/v2/product/{barcode}
```

### 5.2 Open Products Facts

À utiliser pour les produits non alimentaires :

```text
- entretien maison ;
- papier toilette ;
- sacs poubelle ;
- piles ;
- accessoires ;
- petit bazar.
```

Export utile :

```bash
wget https://static.openproductsfacts.org/data/openproductsfacts-products.jsonl.gz
```

### 5.3 Open Beauty Facts

À utiliser pour :

```text
- shampoing ;
- savon ;
- gel douche ;
- dentifrice ;
- déodorant ;
- crème ;
- produits cosmétiques.
```

### 5.4 Scan marchand

C'est la source la plus importante pour construire un vrai catalogue tunisien.

Workflow :

```text
1. Le marchand scanne un produit.
2. L'application cherche le code-barres dans ProductIdentifier.
3. Si trouvé, elle affiche le produit.
4. Si absent, elle cherche dans Open Food Facts / Products / Beauty.
5. Si toujours absent, elle crée une fiche minimale.
6. Le marchand confirme ou complète la donnée.
7. La fiche passe en validation interne.
```

### 5.5 Import fournisseur

Les fournisseurs ou enseignes partenaires peuvent fournir des fichiers CSV, Excel ou API.

Exemple :

```csv
barcode,brand,name,quantity,unit,category
6191234567890,Vitalait,Lait demi-écrémé,1,L,Lait
6191234567891,Vitalait,Lait entier,1,L,Lait
```

Cette source devient prioritaire en production car elle est plus fiable que le scraping dev.

---

## 6. Scraping pour le développement

Pour faire tourner l'application en développement, il est acceptable de créer un seed minimal à partir de pages publiques, à condition de limiter strictement les champs collectés.

Champs autorisés pour le seed dev :

```text
- nom produit ;
- marque ;
- volume / poids ;
- unité ;
- catégorie simple ;
- code-barres si visible publiquement ;
- source_url ;
- date d'observation.
```

Champs à exclure :

```text
- image ;
- logo ;
- description marketing ;
- slogan ;
- avis client ;
- arborescence complète d'un catalogue ;
- contenu derrière login ;
- données récupérées via contournement anti-bot.
```

Chaque donnée collectée en dev doit être tracée :

```text
source_type = dev_public_observation
production_usable = false
```

Le but est de tester l'application, pas de constituer directement une base de production.

---

## 7. Codes-barres / GTIN

Il ne faut pas essayer de deviner les vrais codes-barres à partir du nom du produit.

```text
Nom + marque + volume ≠ code-barres certain
```

Exemple :

```text
Lait demi-écrémé 1 L — Vitalait
```

Ce produit peut exister dans le référentiel sans code-barres au début. Le vrai GTIN sera ajouté plus tard via scan marchand, import fournisseur ou source fiable.

### 7.1 Préfixe tunisien

Le préfixe GS1 `619` est associé à la Tunisie. Cela ne signifie pas automatiquement que le produit est fabriqué en Tunisie ; cela indique que le préfixe a été attribué via GS1 Tunisia.

Il ne faut jamais inventer de faux GTIN publics.

### 7.2 Produits sans code-barres

Certains produits n'auront pas de GTIN officiel :

```text
- pain local ;
- fruits et légumes ;
- vrac ;
- pâtisserie ;
- plats préparés ;
- produits artisanaux.
```

Pour eux, il faut utiliser un identifiant interne :

```text
TN-LOCAL-000001
```

Si besoin, l'application peut générer un QR code ou un Code128 interne, mais il ne doit pas être présenté comme un EAN/GTIN officiel.

---

## 8. Modèle de données recommandé

### 8.1 ProductMaster

```sql
CREATE TABLE product_master (
    id UUID PRIMARY KEY,
    brand VARCHAR(120) NOT NULL,
    generic_name VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    quantity_value NUMERIC(10, 3) NULL,
    quantity_unit VARCHAR(20) NULL,
    category_code VARCHAR(80) NULL,
    country_market CHAR(2) NOT NULL DEFAULT 'TN',
    validation_status VARCHAR(40) NOT NULL DEFAULT 'draft',
    source_type VARCHAR(60) NOT NULL,
    production_usable BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);
```

Exemple :

```json
{
  "brand": "Vitalait",
  "generic_name": "Lait demi-écrémé",
  "display_name": "Lait demi-écrémé 1 L — Vitalait",
  "quantity_value": 1,
  "quantity_unit": "L",
  "category_code": "dairy.milk",
  "country_market": "TN",
  "source_type": "merchant_scan",
  "production_usable": true
}
```

### 8.2 ProductIdentifier

Les identifiants doivent être séparés du produit principal.

```sql
CREATE TABLE product_identifier (
    id UUID PRIMARY KEY,
    product_id UUID NOT NULL REFERENCES product_master(id),
    type VARCHAR(40) NOT NULL,
    value VARCHAR(100) NOT NULL,
    source VARCHAR(80) NOT NULL,
    confidence_score INT NOT NULL DEFAULT 100,
    created_at TIMESTAMP NOT NULL,
    UNIQUE(type, value)
);
```

Types possibles :

```text
- gtin
- internal_sku
- supplier_ref
- merchant_ref
```

Exemple :

```text
ProductMaster:
Lait demi-écrémé 1 L — Vitalait

ProductIdentifier:
type = internal_sku
value = TN-SEED-000001

Plus tard :
type = gtin
value = 619xxxxxxxxxx
source = merchant_scan
```

### 8.3 ProductImportRaw

Cette table conserve les données brutes importées ou collectées.

```sql
CREATE TABLE product_import_raw (
    id UUID PRIMARY KEY,
    source_name VARCHAR(100) NOT NULL,
    source_url TEXT NULL,
    raw_title TEXT NOT NULL,
    raw_brand VARCHAR(120) NULL,
    raw_quantity VARCHAR(80) NULL,
    raw_category VARCHAR(120) NULL,
    raw_payload JSONB NULL,
    imported_at TIMESTAMP NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending'
);
```

### 8.4 MerchantProductOffer

Cette table représente le produit réellement vendu par un marchand.

```sql
CREATE TABLE merchant_product_offer (
    id UUID PRIMARY KEY,
    merchant_id UUID NOT NULL,
    product_id UUID NOT NULL REFERENCES product_master(id),
    price_tnd_millimes INT NOT NULL,
    stock_quantity NUMERIC(10, 3) NOT NULL DEFAULT 0,
    reserved_quantity NUMERIC(10, 3) NOT NULL DEFAULT 0,
    is_available BOOLEAN NOT NULL DEFAULT TRUE,
    click_collect_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    preparation_time_minutes INT DEFAULT 30,
    updated_at TIMESTAMP NOT NULL
);
```

Les prix doivent être stockés en millimes pour éviter les erreurs de flottants :

```text
12.900 TND = 12900 millimes
```

### 8.5 MerchantProductPriceHistory

Le prix courant d'un produit marchand reste stocké dans `merchant_product_offer.price_tnd_millimes` pour permettre une lecture rapide du catalogue.

Chaque changement de prix doit aussi être historisé dans une table dédiée afin de conserver une trace exploitable pour l'audit, l'analyse commerciale, les promotions et la compréhension de l'évolution des prix dans le temps.

```sql
CREATE TABLE merchant_product_price_history (
    id UUID PRIMARY KEY,

    merchant_product_offer_id UUID NOT NULL
        REFERENCES merchant_product_offer(id),

    merchant_id UUID NOT NULL,
    product_id UUID NOT NULL REFERENCES product_master(id),

    old_price_tnd_millimes INT NULL,
    new_price_tnd_millimes INT NOT NULL,

    currency CHAR(3) NOT NULL DEFAULT 'TND',

    change_type VARCHAR(40) NOT NULL DEFAULT 'manual',
    source_type VARCHAR(60) NOT NULL DEFAULT 'merchant_admin',

    reason VARCHAR(255) NULL,
    changed_by_user_id UUID NULL,

    valid_from TIMESTAMP NOT NULL,
    valid_to TIMESTAMP NULL,

    created_at TIMESTAMP NOT NULL,

    metadata JSONB NULL
);
```

Le couple `merchant_product_offer_id` + `valid_to IS NULL` représente le prix actuellement actif dans l'historique.

Même si `merchant_id` et `product_id` peuvent être retrouvés via `merchant_product_offer_id`, ils sont conservés dans l'historique pour faciliter les requêtes, les exports, les statistiques et les audits.

Types de changement recommandés :

```text
manual              → changement manuel par le marchand
bulk_import         → import CSV / Excel
supplier_sync       → synchronisation fournisseur
promotion_start     → début d'une promotion
promotion_end       → fin d'une promotion
correction          → correction d'une erreur
system              → changement automatique futur
```

Règle métier :

```text
Si le prix ne change pas :
    ne pas créer d'historique

Si le prix change :
    fermer l'historique actif avec valid_to
    créer une nouvelle ligne avec new_price_tnd_millimes
    mettre à jour merchant_product_offer.price_tnd_millimes
```

Exemple :

```text
Produit : Vitalait — Lait demi-écrémé — 1 L
Marchand : Supérette A

2026-05-01 : création à 1.950 TND → 1950 millimes
2026-05-04 : passage à 2.000 TND → 2000 millimes
2026-05-06 : promotion à 1.890 TND → 1890 millimes
```

Point critique pour les commandes : une commande ne doit jamais dépendre du prix courant après validation.

Au moment de la commande, le prix doit être copié dans la ligne de commande :

```sql
CREATE TABLE order_item (
    id UUID PRIMARY KEY,
    order_id UUID NOT NULL,
    merchant_product_offer_id UUID NOT NULL,
    product_id UUID NOT NULL,

    product_display_name VARCHAR(255) NOT NULL,

    unit_price_tnd_millimes INT NOT NULL,
    quantity NUMERIC(10, 3) NOT NULL,
    total_price_tnd_millimes INT NOT NULL,

    created_at TIMESTAMP NOT NULL
);
```

Décision retenue :

```text
Le prix courant est stocké dans MerchantProductOffer.price_tnd_millimes.
Chaque modification de prix crée une entrée immutable dans MerchantProductPriceHistory.
Chaque commande copie le prix au moment de l'achat dans OrderItem.unit_price_tnd_millimes.
Le prix est toujours stocké en millimes tunisiens, jamais en float.
```

---

## 9. Déduplication

Un code-barres valide devient l'identifiant prioritaire quand il existe.

```text
Si GTIN existe :
    produit = produit associé au GTIN
Sinon :
    matching par fingerprint
```

Fingerprint recommandé :

```text
normalized_brand | normalized_generic_name | quantity_value | quantity_unit
```

Exemple :

```text
vitalait|lait-demi-ecreme|1|l
```

Cela permet de rapprocher :

```text
Lait demi écrémé 1L Vitalait
Vitalait lait demi-écrémé 1 litre
Lait demi-écrémé Vitalait 1 L
```

---

## 10. Validation EAN-13

Une validation mathématique EAN-13 permet de détecter les erreurs de saisie, mais ne garantit pas que le produit existe réellement.

```php
function isValidEan13(string $ean): bool
{
    if (!preg_match('/^\d{13}$/', $ean)) {
        return false;
    }

    $sum = 0;

    for ($i = 0; $i < 12; $i++) {
        $digit = (int) $ean[$i];
        $sum += ($i % 2 === 0) ? $digit : $digit * 3;
    }

    $checkDigit = (10 - ($sum % 10)) % 10;

    return $checkDigit === (int) $ean[12];
}
```

Limites :

```text
EAN valide mathématiquement ≠ produit réel
EAN valide mathématiquement ≠ bonne marque
EAN valide mathématiquement ≠ code officiel exploitable
```

---

## 11. Workflow dev

```text
1. Scraper ou importer un seed minimal.
2. Créer des produits sans obligation de code-barres.
3. Générer des internal_sku : TN-SEED-000001, TN-SEED-000002, etc.
4. Tester la recherche produit.
5. Tester l'ajout marchand.
6. Tester prix, stock, panier et click & collect.
```

Exemples de seed :

```text
Vitalait — Lait demi-écrémé — 1 L
Délice — Yaourt nature — 110 g
Safia — Eau minérale — 1,5 L
Randa — Spaghetti — 500 g
Jouda — Harissa — 135 g
```

---

## 12. Workflow prod

```text
1. Scan marchand.
2. Recherche exacte par GTIN.
3. Si absent, lookup Open Food Facts / Products / Beauty.
4. Si absent, matching par marque + nom + volume.
5. Si toujours absent, création d'une fiche minimale.
6. Ajout prix + stock par marchand.
7. Validation interne.
8. Passage production_usable = true.
```

---

## 13. API recommandée

### Recherche catalogue côté marchand

```http
GET /api/merchant/catalog/search?q=vitalait%201l
GET /api/merchant/catalog/barcode/{barcode}
```

### Ajouter un produit au magasin

```http
POST /api/merchant/products
```

```json
{
  "product_id": "uuid",
  "price_tnd_millimes": 1950,
  "stock_quantity": 12,
  "click_collect_enabled": true
}
```

### Recherche utilisateur

```http
GET /api/catalog/products?q=lait&merchant_id={merchantId}
```

Ou en mode marketplace :

```http
GET /api/catalog/products?q=lait&lat=36.8065&lng=10.1815
```

---

## 14. Règles de publication côté utilisateur

Un produit ne doit être visible côté utilisateur que si :

```text
- le ProductMaster est validé ou de qualité suffisante ;
- le marchand est actif ;
- l'offre marchand est active ;
- un prix est défini ;
- le click & collect est activé ;
- le stock disponible est positif ou la commande différée est acceptée.
```

---

## 15. Position juridique prudente

Le référentiel ne doit pas copier de fiches marketing.

Approche acceptable pour le dev :

```text
- collecter des données factuelles minimales ;
- ne pas copier images, logos ou textes commerciaux ;
- ne pas contourner login, captcha ou anti-bot ;
- garder la source ;
- marquer la donnée comme non utilisable en production tant qu'elle n'est pas validée.
```

Approche production :

```text
- scan marchand ;
- import fournisseur ;
- partenariat enseigne ;
- validation interne ;
- sources ouvertes autorisées.
```

---

## 16. Sources utiles

- GS1 — GTIN : https://www.gs1.org/standards/id-keys/gtin
- GS1 — préfixes pays / organisations membres : https://www.gs1.org/standards/id-keys/company-prefix
- GS1 Tunisia : https://www.gs1tn.org/
- Open Food Facts API : https://openfoodfacts.github.io/openfoodfacts-server/api/
- Open Food Facts exports : https://wiki.openfoodfacts.org/Reusing_Open_Food_Facts_Data
- Open Products Facts : https://world.openproductsfacts.org/
- Open Beauty Facts : https://world.openbeautyfacts.org/

---

## 17. Décision d'architecture

Décision retenue :

```text
ProductMasterTunisia
= identité produit globale

ProductIdentifier
= GTIN, internal_sku, supplier_ref, merchant_ref

MerchantProductOffer
= prix, stock, disponibilité, délai de préparation

ProductImportRaw
= données brutes collectées ou importées
```

Le produit peut exister sans code-barres au début. Le vrai GTIN est ajouté progressivement par scan marchand, import fournisseur ou source fiable.

Le développement peut utiliser un seed minimal collecté publiquement, mais la production doit s'appuyer sur des données validées, traçables et/ou fournies par les marchands, fournisseurs ou partenaires.
