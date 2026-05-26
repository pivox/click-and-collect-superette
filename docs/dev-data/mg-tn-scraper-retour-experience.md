# Scraper mg.tn — retour d'expérience et correction de trajectoire

## Contexte

La PR de scraper mg.tn a ajouté un flux technique complet :

1. scraping conteneurisé depuis `mg.tn` ;
2. insertion dans la table de staging `product_import_raw` ;
3. promotion vers `ProductReference` en statut `pending_review` ;
4. conservation du lien d'origine via `ProductReference.sourceImportRaw`.

Ce flux fonctionne techniquement en environnement de développement. Le premier essai a été lancé dans le mauvais ordre produit : il ciblait la page d'accueil / blog de `mg.tn`, pas les pages catalogue produit exploitables pour une supérette.

La correction appliquée consiste à utiliser le mode `--site` :

- lecture du sitemap `https://mg.tn/sitemap.xml` ;
- crawl des URLs internes du site ;
- extraction uniquement des cartes produit PrestaShop ;
- exclusion des pages éditoriales sans bloc produit ;
- insertion en staging `product_import_raw`, puis promotion contrôlée vers `ProductReference`.

## Résultat observé en dev

Commandes exécutées :

```bash
make scraper-db ARGS="--pages 1 --delay 0"
make promote-raw-products ARGS="--limit=20"
```

Résultat SQL observé :

```text
product_import_raw source mg.tn : 9 lignes
ProductReference liés à product_import_raw mg.tn : 9 lignes
```

La promotion relancée une seconde fois n'a pas créé de doublon :

```text
processed: 0 | created: 0 | skipped: 0 | errors: 0
```

Les lignes créées dans `ProductReference` sont bien en `pending_review` et conservent :

- `source_import_raw_id` ;
- `source_name = mg.tn` ;
- `source_url`.

## Problème métier

Les données récupérées lors du premier essai n'étaient pas des produits de supérette. C'étaient des titres d'articles / pages éditoriales, par exemple :

- actualités de marque ;
- articles espace presse ;
- événements Magasin Général.

Ces titres ne permettent pas de construire un catalogue client utile pour préparer une **Kadhia**.

Il ne faut donc pas considérer ces anciennes lignes comme un seed produit fiable, même en `pending_review`.

Le mode corrigé doit être lancé avec :

```bash
make scraper-db ARGS="--max-urls 500 --delay 0"
```

`make scraper-db` ajoute désormais `--site --db` par défaut. Les anciennes options `--pages` / `--category` restent disponibles pour diagnostiquer une URL ou une section précise, mais ne doivent pas être utilisées pour alimenter `product_import_raw`.

## Ce qui reste valable

La partie infrastructure est réutilisable :

- conteneur Python isolé ;
- insertion PostgreSQL depuis le conteneur scraper ;
- table de staging `product_import_raw` ;
- promotion contrôlée vers `ProductReference` ;
- statut `pending_review` avant validation admin ;
- traçabilité de l'origine ;
- idempotence de la promotion.

Cette base est utile pour un futur import de données produit, mais pas avec la source URL actuelle.

## Ce qu'il faut éviter

Ne pas :

- promouvoir des contenus blog en produits validés ;
- utiliser ces lignes dans un catalogue marchand ;
- créer des `MerchantProduct` depuis ces données ;
- inventer des codes-barres ;
- utiliser des images, logos ou descriptions marketing récupérés depuis mg.tn ;
- relancer l'ingestion `product_import_raw` sans le mode site ;
- considérer le scraper mg.tn comme une source produit de production.

## Correction de trajectoire recommandée

Avant de relancer une ingestion utile, il faut repartir de la source produit :

1. utiliser le crawl site sur les pages catalogue mg.tn ;
2. vérifier que chaque bloc contient au minimum un nom produit ;
3. extraire uniquement les champs factuels autorisés :
   - nom produit ;
   - marque si visible ;
   - volume / poids si visible ;
   - unité ;
   - catégorie simple ;
   - source URL ;
   - date d'observation ;
4. insérer ces données dans `product_import_raw` ;
5. promouvoir en `ProductReference::PendingReview` ;
6. faire valider / nettoyer côté admin avant usage par les marchands.

## Position MVP

Pour le MVP Kadhia, la priorité reste un référentiel propre plutôt qu'un volume massif.

La meilleure prochaine étape est de privilégier l'une de ces sources :

- CSV préparé manuellement avec 200 à 500 produits fréquents ;
- données terrain de supérettes pilotes ;
- données fournies par marchands, fournisseurs ou marques ;
- open data produit quand les droits et champs sont compatibles.

Le scraping mg.tn peut rester un outil de développement, uniquement sur les pages produit réelles et en gardant les données non validées par défaut.
