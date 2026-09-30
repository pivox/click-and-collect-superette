# Audit des sources tunisiennes — identification produit et droits d'image

> PRODUCT-IMAGE-005 (#585) · Audit réalisé le 30 septembre 2026.
> Complète `docs/product/image-library-registry.md` (registre) et l'ADR-0006.
> Les statuts juridiques ci-dessous sont des **constats d'audit**, pas des avis
> juridiques ; toute décision de réutilisation d'image reste soumise au registre
> de provenance (#584) : `UNKNOWN` ne devient jamais une image officielle.

## Règle obligatoire (rappel)

Une URL d'image retail peut être **enregistrée comme observation/source à
qualifier** (`source_url` + `permission_reference` du registre #584). Elle ne
doit **jamais** être téléchargée dans notre stockage ni publiée comme image
officielle tant que le droit d'usage n'est pas clair. En l'absence de mention
explicite de réutilisation, la posture par défaut est **tous droits réservés →
observation seulement**.

## Matrice de couverture et de droits

| Source | URL catalogue | Couverture | Marque | Format | GTIN visible | Images | CGU accessibles | Droit de réutilisation d'image | Contact partenariat | **Décision** |
|---|---|---|---|---|---|---|---|---|---|---|
| Carrefour Tunisie | carrefour.tn (plateforme e-commerce complète : alimentaire, PGC, drive + livraison) | Large (alimentaire + non-alimentaire) | Oui | Oui (fiches produit) | À vérifier fiche par fiche | Oui | Page CGV présente mais non consultable automatiquement lors de l'audit (redirection/contenu vide) — à vérifier manuellement | **Inconnu** (aucune mention publique de libre réutilisation) | Service e-commerce Carrefour Tunisie (groupe UTIC) | **Observation seulement** |
| Géant Tunisie | geant.tn (catalogue consultable, appli mobile, liste de courses) | Large (hypermarché) | Oui | Oui | À vérifier | Oui | Site à certificat TLS invalide lors de l'audit (`unable to verify certificate`) — fiabilité d'URL faible | **Inconnu** | Direction marketing Géant (Tunis City) | **Observation seulement** |
| Magasin Général (MG) / Founa | founa.com (≈11 000–15 000 références, drive MG Maxi La Marsa ; Founa rachetée par MG) | Large (PGC + frais) | Oui | Oui | À vérifier | Oui | À vérifier manuellement | **Inconnu** | MG a déjà une culture e-commerce (rachat Founa) → piste de partenariat la plus crédible | **À négocier** |
| Monoprix Tunisie | Appli mobile M'Monoprix (catalogue digital complet, retrait 2h) ; site vitrine | Large (~80 magasins, groupe Mabrouk) | Oui | Oui | À vérifier | Oui | À vérifier manuellement | **Inconnu** | Groupe Mabrouk / SNMVT | **Observation seulement** |
| Aziza | Pas de catalogue produit en ligne détecté lors de l'audit (site institutionnel ; ~250 magasins discount) | Faible en ligne (forte au sol) | Marques propres surtout | Non exposé en ligne | Non | Non | N/A | N/A | Groupe Slama / Mediterrania Capital | **Exclure (pas de source en ligne)** |
| Producteurs / distributeurs tunisiens (Délice, SOTUBI, Vitalait, SICAM, Saïda…) | Sites de marque individuels | Ciblée (leurs gammes) | Oui | Oui | Parfois | Oui (packshots officiels) | Variable | **À négocier** — un packshot fourni par le fabricant sous autorisation écrite = `manufacturer_authorized` | Services marketing des marques | **À négocier (voie privilégiée)** |
| Open Food Facts | world.openfoodfacts.org (+ sous-base produits tunisiens) | Moyenne pour la Tunisie (couverture partielle, croissante) | Oui | Oui | **Oui (GTIN structuré)** | Oui | Oui (publiques) | **Oui, sous conditions** : base ODbL 1.0, **images CC-BY-SA** → attribution obligatoire + partage à l'identique | Communauté OFF (ouverte) | **Réutilisation autorisée sous conditions** (`cc_by_sa`, `attribution_text` obligatoire) |
| GS1 Tunisia (ex-TUNICODE) | gs1tn.org — pas un catalogue d'images : référentiel GTIN | Identification (pas d'images) | N/A | N/A | **Oui (source d'autorité GTIN)** | Non | Oui | N/A (données d'identification) | Adhésion via gs1tn.org (seule entité habilitée GS1 en Tunisie depuis 1992) | **À négocier (partenariat validation GTIN)** |

## Lecture de la matrice

- **Aucune enseigne retail tunisienne n'autorise explicitement la réutilisation
  de ses images produit.** Toutes les images observées sur carrefour.tn,
  geant.tn, founa.com ou l'appli Monoprix servent uniquement à *identifier* une
  référence (nom, marque, format, GTIN éventuel) — décision `observation
  seulement`, à consigner via `source_name`/`source_url`/`permission_reference`
  du registre, licence `unknown`.
- **Open Food Facts est la seule source d'images légalement réutilisable dès
  aujourd'hui**, au prix des obligations CC-BY-SA : `license_code = cc_by_sa`,
  `attribution_text` renseigné (photographe/OFF + lien licence), et partage à
  l'identique des dérivés de la photo. La qualité et la couverture tunisienne
  sont hétérogènes — utile en complément, pas comme source principale.
- **La voie durable est l'autorisation directe** : packshots fournis par les
  producteurs/distributeurs tunisiens (`manufacturer_authorized` /
  `distributor_authorized` + `permission_reference` = référence de l'accord
  écrit), photos marchands (#583, `merchant_authorized`) et prises de vue
  internes (`platform_owned`).
- **GS1 Tunisia** ne fournit pas d'images mais fiabilise l'identification GTIN ;
  une adhésion/partenariat vaut surtout pour dédupliquer le référentiel produit.

## Stabilité des URLs observées

- carrefour.tn : plateforme Magento, URLs produit propres mais réécritures
  fréquentes lors des refontes ; robots.txt standard (media autorisé, recherche
  et comptes interdits) — la consultation manuelle reste conforme, le scraping
  massif n'est pas dans le périmètre (règle obligatoire ci-dessus).
- geant.tn : certificat TLS invalide au moment de l'audit — ne jamais faire
  dépendre quoi que ce soit de la disponibilité de ces URLs (règle déjà actée :
  l'accès catalogue ne dépend d'aucune URL externe volatile).
- founa.com : la plus ancienne plateforme (2013), URLs produit relativement
  stables, adossée à MG depuis le rachat.

## Prochaines actions recommandées

1. Vérifier manuellement (navigateur) les CGU de carrefour.tn, founa.com et de
   l'appli Monoprix, et archiver la mention de propriété intellectuelle dans
   `permission_reference` des observations concernées.
2. Prendre contact avec 2–3 producteurs tunisiens à forte présence en supérette
   (ex. Délice, SOTUBI, Vitalait) pour obtenir des packshots autorisés — modèle
   d'accord écrit à faire valider (décision humaine, hors périmètre agent).
3. Étudier l'adhésion GS1 Tunisia (gs1tn.org) pour la validation GTIN du
   référentiel.
4. Alimenter le fichier de travail `data/product-image-source-observations.csv`
   au fil des observations terrain (jamais d'images téléchargées).

## Fichier de travail

`data/product-image-source-observations.csv` — colonnes :

```text
source,product_name,brand,format,product_page_url,image_url,observed_at,rights_status,notes
```

`rights_status` ∈ `observation_only | authorized | to_negotiate | excluded`
(aligné sur les décisions de la matrice ; le passage à `authorized` exige une
`permission_reference` dans le registre #584).
