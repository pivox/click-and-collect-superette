# Supérette demo

Cette commande prépare une supérette exploitable pour tester le parcours client avant la Kadhia.

## Commandes

Petit catalogue de démonstration :

```bash
make seed-demo-store
```

Tout le référentiel approuvé :

```bash
make seed-demo-store-all
```

Commandes Symfony équivalentes :

```bash
php bin/console app:dev:seed-demo-store --catalog=demo
php bin/console app:dev:seed-demo-store --catalog=all
```

## Données créées ou réutilisées

Marchand demo :

- email : `merchant.demo@kadhia.local`
- rôle : `ROLE_MERCHANT`
- actif : oui

Supérette demo :

- nom : `Supérette El Amen`
- slug : `superette-el-amen`
- ville : `Tunis`
- pays : `TN`
- QR token : `demo-superette-el-amen`
- active : oui

## Catalogue

`--catalog=demo` rattache uniquement une sélection courte de produits utiles pour tester le catalogue public.

`--catalog=all` rattache toutes les `ProductReference` avec le statut `Approved`.

Les produits catalogue sont créés ou mis à jour avec :

- `isVisible = true`
- `isAvailable = true`
- un prix TND à 3 décimales

La commande est idempotente : elle peut être relancée sans créer de doublons.

## Vérification

La commande affiche l'identifiant de la supérette et l'URL publique :

```http
GET /api/stores/{storeId}/catalog
```

Recherche :

```http
GET /api/stores/{storeId}/catalog?query=lait
```

Catégorie :

```http
GET /api/stores/{storeId}/catalog?category=lait
```
