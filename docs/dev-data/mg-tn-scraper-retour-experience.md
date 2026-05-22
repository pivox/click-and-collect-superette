# Scraper mg.tn — retour d'expérience et correction de trajectoire

## Contexte

La PR de scraper mg.tn a ajouté un flux technique complet :

1. scraping conteneurisé depuis `mg.tn` ;
2. insertion dans la table de staging `product_import_raw` ;
3. promotion vers `ProductReference` en statut `pending_review` ;
4. conservation du lien d'origine via `ProductReference.sourceImportRaw`.

Ce flux fonctionne techniquement en environnement de développement, mais il a été lancé dans le mauvais ordre produit : le scraper cible actuellement la page d'accueil / blog de `mg.tn`, pas des pages catalogue produit exploitables pour une supérette.

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

Les données récupérées ne sont pas des produits de supérette. Ce sont des titres d'articles / pages éditoriales, par exemple :

- actualités de marque ;
- articles espace presse ;
- événements Magasin Général.

Ces titres ne permettent pas de construire un catalogue client utile pour préparer une **Kadhia**.

Il ne faut donc pas considérer ces lignes comme un seed produit fiable, même en `pending_review`.

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
- considérer le scraper actuel comme une source produit de production.

## Correction de trajectoire recommandée

Avant de relancer une ingestion utile, il faut repartir de la source produit :

1. identifier une page catalogue ou une API publique qui expose réellement des produits ;
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

Le scraping mg.tn peut rester un outil de développement, mais uniquement après avoir ciblé des pages produit réelles et en gardant les données non validées par défaut.
