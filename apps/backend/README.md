# Backend — Click & Collect Supérette

API centrale du MVP.

## Périmètre

Le backend porte la logique métier, la sécurité et la persistance de l'application.

## Responsabilités principales

- authentification ;
- autorisation par rôles ;
- gestion des clients ;
- gestion des marchands ;
- gestion des supérettes ;
- référentiel produits Tunisie ;
- offres produits par marchand ;
- prix courants et historique de prix ;
- commandes ;
- statuts de commande ;
- créneaux de retrait ;
- QR codes magasin ;
- QR codes de retrait ;
- audit et supervision.

## Source de vérité

Le backend est la source de vérité pour :

- le prix au moment de la commande ;
- les transitions de statut ;
- les autorisations ;
- la validation d'une commande ;
- la validation d'un retrait ;
- les données du référentiel produit.

## Architecture attendue

L'implémentation cible peut être réalisée avec Symfony et API Platform.

Structure indicative :

```text
apps/backend/
├── config/
├── migrations/
├── public/
├── src/
│   ├── Entity/
│   ├── Repository/
│   ├── Application/
│   ├── Domain/
│   ├── Infrastructure/
│   └── Controller/
├── tests/
├── composer.json
└── README.md
```

## Règle importante

Aucune règle métier critique ne doit dépendre uniquement du frontend.

## Documentation API locale

API Platform scanne explicitement :

- `apps/backend/src/Entity`
- `apps/backend/src/ApiResource`

Routes de documentation locales :

- Swagger UI / API Platform docs : `http://127.0.0.1:8001/api/docs`
- OpenAPI JSON : `http://127.0.0.1:8001/api/docs.jsonopenapi`
- OpenAPI YAML : `http://127.0.0.1:8001/api/docs.yamlopenapi`

Le projet expose aussi la route Symfony `api_doc` sur `/api/docs.{_format}`.

Statut actuel vérifié sur cette branche :

- `/api/docs.jsonopenapi` répond en `200 OK`
- `/api/docs.yamlopenapi` répond en `200 OK`
- `/api/docs` répond en `200 OK` pour une requête générique, mais l'affichage HTML Swagger UI échoue en `500` avec `Accept: text/html` car `symfony/twig-bundle` n'est pas installé

## Commandes de vérification

Depuis `apps/backend/` :

```bash
composer validate --no-check-publish
find src tests migrations -name '*.php' -exec php -l {} \;
bin/console debug:router
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=512M
vendor/bin/php-cs-fixer fix --dry-run --diff
```
